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
 * Behat steps for booktool_duplicate.
 *
 * @package    booktool_duplicate
 * @category   test
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for booktool_duplicate.
 */
class behat_booktool_duplicate extends behat_base {

    /**
     * Attach a file to a book chapter's file area and rewrite its content to reference it.
     *
     * @Given /^the book chapter "(?P<chapter>(?:[^"]|\\")*)" in "(?P<book>(?:[^"]|\\")*)" has an embedded image "(?P<filepath>(?:[^"]|\\")*)"$/
     *
     * @param string $chaptertitle
     * @param string $bookname
     * @param string $filepath Path to fixture file relative to Moodle dirroot.
     */
    public function the_book_chapter_has_embedded_image(string $chaptertitle, string $bookname, string $filepath): void {
        global $CFG, $DB;

        $book = $DB->get_record('book', ['name' => $bookname], '*', MUST_EXIST);
        $chapter = $DB->get_record('book_chapters', ['bookid' => $book->id, 'title' => $chaptertitle], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, $book->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $sourcepath = "{$CFG->dirroot}/{$filepath}";
        if (!file_exists($sourcepath)) {
            throw new coding_exception("File '{$sourcepath}' does not exist");
        }

        $filename = basename($sourcepath);
        $fs = get_file_storage();
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_book',
            'filearea'  => 'chapter',
            'itemid'    => $chapter->id,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $sourcepath);

        $content = '<p>' . htmlspecialchars($chapter->content, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><img src="@@PLUGINFILE@@/' . $filename . '" alt="Embedded image"></p>';
        $DB->set_field('book_chapters', 'content', $content, ['id' => $chapter->id]);
        $DB->set_field('book_chapters', 'contentformat', FORMAT_HTML, ['id' => $chapter->id]);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
    }
}
