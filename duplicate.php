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

$newids = \booktool_duplicate\duplicator::duplicate_chapter($book, $context, $source);

$newfirstid = $newids[$source->id];
$a = (object) [
    'title' => format_string($source->title),
    'subchapters' => count($newids) - 1,
];
$message = count($newids) > 1
    ? get_string('chapterduplicatedwithsubs', 'booktool_duplicate', $a)
    : get_string('chapterduplicated', 'booktool_duplicate', $a);

redirect(
    new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $newfirstid]),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
