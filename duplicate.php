<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Duplicate a book chapter (and its subchapters, when a top-level chapter is selected).
 *
 * @package    booktool_duplicate
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../locallib.php');

$id        = required_param('id', PARAM_INT);         // Course module id.
$chapterid = required_param('chapterid', PARAM_INT);  // Source chapter id.

$cm      = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book    = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
$source  = $DB->get_record('book_chapters', ['id' => $chapterid, 'bookid' => $book->id], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('booktool/duplicate:duplicate', $context);

$PAGE->set_url('/mod/book/tool/duplicate/duplicate.php', ['id' => $id, 'chapterid' => $chapterid]);

// Build the set of chapters to duplicate: the source, plus contiguous following
// subchapters when the source is a top-level chapter.
$tocopy = [$source];
if (!$source->subchapter) {
    $rs = $DB->get_recordset_select(
        'book_chapters',
        'bookid = :bookid AND pagenum > :pagenum',
        ['bookid' => $book->id, 'pagenum' => $source->pagenum],
        'pagenum'
    );
    foreach ($rs as $ch) {
        if (!$ch->subchapter) {
            break;
        }
        $tocopy[] = $ch;
    }
    $rs->close();
}

$n = count($tocopy);
$lastpagenum = $source->pagenum + $n - 1;

$fs = get_file_storage();
$newids = [];

$transaction = $DB->start_delegated_transaction();

// Shift the pagenums of every chapter after the block we are duplicating.
$DB->execute(
    "UPDATE {book_chapters} SET pagenum = pagenum + :n WHERE bookid = :bookid AND pagenum > :last",
    ['n' => $n, 'bookid' => $book->id, 'last' => $lastpagenum]
);

// Insert a copy of each chapter in order, immediately after the source block.
foreach ($tocopy as $index => $src) {
    $new = clone $src;
    unset($new->id);
    $new->pagenum       = $src->pagenum + $n;
    $new->timecreated   = time();
    $new->timemodified  = time();
    $new->importsrc     = '';

    $newid = $DB->insert_record('book_chapters', $new);
    $newids[$src->id] = $newid;

    // Copy the chapter file area so embedded images and attachments carry over.
    $files = $fs->get_area_files($context->id, 'mod_book', 'chapter', $src->id, 'id', false);
    foreach ($files as $file) {
        $fs->create_file_from_storedfile(['itemid' => $newid], $file);
    }

    // Copy tags.
    $tags = core_tag_tag::get_item_tags_array('mod_book', 'book_chapters', $src->id);
    if (!empty($tags)) {
        core_tag_tag::set_item_tags('mod_book', 'book_chapters', $newid, $context, $tags);
    }
}

$DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);

$transaction->allow_commit();

// Trigger chapter_created events once the transaction is committed.
foreach ($newids as $oldid => $newid) {
    $newchapter = $DB->get_record('book_chapters', ['id' => $newid], '*', MUST_EXIST);
    \mod_book\event\chapter_created::create_from_chapter($book, $context, $newchapter)->trigger();
}

// Normalise book structure (pagenum, subchapter flags on first row, etc.).
book_preload_chapters($book);

$newfirstid = $newids[$source->id];
$a = (object) [
    'title' => format_string($source->title),
    'subchapters' => $n - 1,
];
$message = $n > 1
    ? get_string('chapterduplicatedwithsubs', 'booktool_duplicate', $a)
    : get_string('chapterduplicated', 'booktool_duplicate', $a);

redirect(
    new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $newfirstid]),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
