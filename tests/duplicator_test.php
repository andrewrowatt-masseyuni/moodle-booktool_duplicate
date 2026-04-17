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
 * Unit tests for booktool_duplicate\duplicator.
 *
 * @package    booktool_duplicate
 * @category   test
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace booktool_duplicate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/book/locallib.php');

/**
 * Unit tests for the chapter duplicator.
 *
 * @package    booktool_duplicate
 * @covers     \booktool_duplicate\duplicator
 */
final class duplicator_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $book;

    /** @var \context_module */
    private $context;

    /** @var \mod_book_generator */
    private $bookgen;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->book    = $this->getDataGenerator()->create_module('book', ['course' => $this->course->id]);
        $this->context = \context_module::instance($this->book->cmid);
        $this->bookgen = $this->getDataGenerator()->get_plugin_generator('mod_book');
    }

    /**
     * Helper: return chapters ordered by pagenum.
     *
     * @return \stdClass[]
     */
    private function get_chapters(): array {
        global $DB;
        return array_values($DB->get_records('book_chapters', ['bookid' => $this->book->id], 'pagenum'));
    }

    public function test_duplicate_single_chapter_with_no_subchapters(): void {
        $chapter = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $chapter);

        $this->assertCount(1, $newids);
        $this->assertArrayHasKey($chapter->id, $newids);

        $chapters = $this->get_chapters();
        $this->assertCount(2, $chapters);
        $this->assertEquals($chapter->id, $chapters[0]->id);
        $this->assertEquals(1, $chapters[0]->pagenum);
        $this->assertEquals($newids[$chapter->id], $chapters[1]->id);
        $this->assertEquals(2, $chapters[1]->pagenum);
        // The duplicated chapter is the new one and gets the "Copy of" prefix.
        $this->assertEquals(get_string('chaptercopytitle', 'booktool_duplicate', $chapter->title), $chapters[1]->title);
        $this->assertEquals(0, $chapters[1]->subchapter);
    }

    public function test_duplicate_top_level_chapter_carries_contiguous_subchapters(): void {
        // Layout: C1, C1.a, C1.b, C2.
        $c1 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        $sub1 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 2, 'subchapter' => 1, 'title' => 'Sub A',
        ]);
        $sub2 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 3, 'subchapter' => 1, 'title' => 'Sub B',
        ]);
        $c2 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 4, 'title' => 'Chapter Two',
        ]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $c1);

        // Source + 2 subchapters => 3 new rows.
        $this->assertCount(3, $newids);
        $this->assertEquals([$c1->id, $sub1->id, $sub2->id], array_keys($newids));

        $chapters = $this->get_chapters();
        $this->assertCount(7, $chapters);

        // Order: C1, sub1, sub2, copy(C1), copy(sub1), copy(sub2), C2.
        $expectedorder = [$c1->id, $sub1->id, $sub2->id, $newids[$c1->id], $newids[$sub1->id], $newids[$sub2->id], $c2->id];
        $this->assertEquals($expectedorder, array_map(fn($c) => (int)$c->id, $chapters));

        // Pagenums are contiguous 1..7.
        foreach ($chapters as $i => $ch) {
            $this->assertEquals($i + 1, (int)$ch->pagenum);
        }

        // C2 was shifted from pagenum 4 to 7.
        $this->assertEquals(7, (int)$chapters[6]->pagenum);

        // Only the source copy gets the "Copy of" prefix; subchapter copies keep their titles.
        $sourcecopy = $chapters[3];
        $this->assertEquals(get_string('chaptercopytitle', 'booktool_duplicate', $c1->title), $sourcecopy->title);
        $this->assertEquals(0, (int)$sourcecopy->subchapter);
        $this->assertEquals('Sub A', $chapters[4]->title);
        $this->assertEquals(1, (int)$chapters[4]->subchapter);
        $this->assertEquals('Sub B', $chapters[5]->title);
        $this->assertEquals(1, (int)$chapters[5]->subchapter);
    }

    public function test_duplicate_subchapter_only_copies_itself(): void {
        $c1 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        $sub1 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 2, 'subchapter' => 1, 'title' => 'Sub A',
        ]);
        $sub2 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 3, 'subchapter' => 1, 'title' => 'Sub B',
        ]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $sub1);

        $this->assertCount(1, $newids);
        $this->assertArrayHasKey($sub1->id, $newids);

        $chapters = $this->get_chapters();
        $this->assertCount(4, $chapters);

        // Order: c1, sub1, copy(sub1), sub2.
        $this->assertEquals(
            [(int)$c1->id, (int)$sub1->id, (int)$newids[$sub1->id], (int)$sub2->id],
            array_map(fn($c) => (int)$c->id, $chapters)
        );
        // The copy keeps subchapter=1 and gets the copy prefix.
        $copy = $chapters[2];
        $this->assertEquals(1, (int)$copy->subchapter);
        $this->assertEquals(get_string('chaptercopytitle', 'booktool_duplicate', $sub1->title), $copy->title);
    }

    public function test_duplicate_last_chapter_does_not_shift_anything(): void {
        $c1 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        $c2 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 2]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $c2);

        $chapters = $this->get_chapters();
        $this->assertCount(3, $chapters);
        $this->assertEquals([(int)$c1->id, (int)$c2->id, (int)$newids[$c2->id]], array_map(fn($c) => (int)$c->id, $chapters));
        $this->assertEquals([1, 2, 3], array_map(fn($c) => (int)$c->pagenum, $chapters));
    }

    public function test_duplicate_top_level_chapter_with_no_following_subchapters(): void {
        // C1 followed by C2 (no subchapters in between).
        $c1 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        $c2 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 2]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $c1);

        $this->assertCount(1, $newids);
        $chapters = $this->get_chapters();
        $this->assertCount(3, $chapters);
        // Order: C1, copy(C1), C2; C2 gets pushed to pagenum 3.
        $this->assertEquals([(int)$c1->id, (int)$newids[$c1->id], (int)$c2->id], array_map(fn($c) => (int)$c->id, $chapters));
        $this->assertEquals([1, 2, 3], array_map(fn($c) => (int)$c->pagenum, $chapters));
    }

    public function test_duplicate_increments_book_revision(): void {
        global $DB;
        $chapter = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        // Refresh $book — the generator bumped revision in the DB after our setUp() snapshot.
        $book = $DB->get_record('book', ['id' => $this->book->id], '*', MUST_EXIST);
        $before = (int)$book->revision;

        duplicator::duplicate_chapter($book, $this->context, $chapter);

        $after = (int)$DB->get_field('book', 'revision', ['id' => $book->id]);
        $this->assertEquals($before + 1, $after);
    }

    public function test_duplicate_copies_tags(): void {
        $chapter = $this->bookgen->create_chapter([
            'bookid' => $this->book->id,
            'pagenum' => 1,
            'tags' => ['Cats', 'Dogs'],
        ]);

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $chapter);
        $newid = $newids[$chapter->id];

        $newtags = \core_tag_tag::get_item_tags_array('mod_book', 'book_chapters', $newid);
        sort($newtags);
        $this->assertEquals(['Cats', 'Dogs'], $newtags);
    }

    public function test_duplicate_copies_chapter_files(): void {
        $chapter = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);

        // Add a file to the chapter file area.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_book',
            'filearea'  => 'chapter',
            'itemid'    => $chapter->id,
            'filepath'  => '/',
            'filename'  => 'image.png',
        ], 'fake-image-content');

        $newids = duplicator::duplicate_chapter($this->book, $this->context, $chapter);
        $newid = $newids[$chapter->id];

        $newfiles = $fs->get_area_files($this->context->id, 'mod_book', 'chapter', $newid, 'id', false);
        $this->assertCount(1, $newfiles);
        $newfile = reset($newfiles);
        $this->assertEquals('image.png', $newfile->get_filename());
        $this->assertEquals('fake-image-content', $newfile->get_content());
    }

    public function test_duplicate_triggers_chapter_created_event_per_new_chapter(): void {
        $c1 = $this->bookgen->create_chapter(['bookid' => $this->book->id, 'pagenum' => 1]);
        $sub1 = $this->bookgen->create_chapter([
            'bookid' => $this->book->id, 'pagenum' => 2, 'subchapter' => 1,
        ]);

        $sink = $this->redirectEvents();
        $newids = duplicator::duplicate_chapter($this->book, $this->context, $c1);
        $events = array_filter($sink->get_events(), function ($e) {
            return $e instanceof \mod_book\event\chapter_created;
        });
        $sink->close();

        $this->assertCount(2, $events);
        $eventchapterids = array_map(fn($e) => (int)$e->objectid, array_values($events));
        sort($eventchapterids);
        $expected = array_values(array_map('intval', $newids));
        sort($expected);
        $this->assertEquals($expected, $eventchapterids);
    }

    public function test_duplicate_chapter_from_other_book_throws(): void {
        $otherbook = $this->getDataGenerator()->create_module('book', ['course' => $this->course->id]);
        $foreign = $this->bookgen->create_chapter(['bookid' => $otherbook->id, 'pagenum' => 1]);

        $this->expectException(\coding_exception::class);
        duplicator::duplicate_chapter($this->book, $this->context, $foreign);
    }
}
