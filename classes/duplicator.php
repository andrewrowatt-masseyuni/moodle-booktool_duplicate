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
 * Chapter duplication helper for booktool_duplicate.
 *
 * @package    booktool_duplicate
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace booktool_duplicate;

use context_module;
use core_tag_tag;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/mod/book/locallib.php');

/**
 * Duplicate a book chapter (and contiguous subchapters when the source is top-level).
 *
 * @package booktool_duplicate
 */
class duplicator {
    /**
     * Duplicate a chapter and its contiguous subchapters when applicable.
     *
     * Returns a map of [oldchapterid => newchapterid] in the order the new
     * chapters were inserted, with the source chapter first.
     *
     * @param stdClass $book The book record.
     * @param context_module $context The book module context.
     * @param stdClass $source The source chapter record (must belong to $book).
     * @return array<int, int> Map of source chapter id to newly created chapter id.
     */
    public static function duplicate_chapter(stdClass $book, context_module $context, stdClass $source): array {
        global $DB;

        if ((int)$source->bookid !== (int)$book->id) {
            throw new \coding_exception('Source chapter does not belong to the supplied book.');
        }

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

        foreach ($tocopy as $src) {
            $new = clone $src;
            unset($new->id);
            $new->pagenum      = $src->pagenum + $n;
            $new->timecreated  = time();
            $new->timemodified = time();
            $new->importsrc    = '';
            if ((int)$src->id === (int)$source->id) {
                $new->title = get_string('chaptercopytitle', 'booktool_duplicate', $src->title);
            }

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
        foreach ($newids as $newid) {
            $newchapter = $DB->get_record('book_chapters', ['id' => $newid], '*', MUST_EXIST);
            \mod_book\event\chapter_created::create_from_chapter($book, $context, $newchapter)->trigger();
        }

        // Normalise book structure (pagenum, subchapter flags on first row, etc.).
        book_preload_chapters($book);

        return $newids;
    }
}
