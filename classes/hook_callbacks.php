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
 * Hook callbacks for booktool_duplicate.
 *
 * @package    booktool_duplicate
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace booktool_duplicate;

/**
 * Class hook_callbacks.
 *
 * @package booktool_duplicate
 */
class hook_callbacks {
    /**
     * Inject the duplicate-chapter AMD module on book view pages when editing is on.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;

        if (!$PAGE->cm || $PAGE->cm->modname !== 'book' || $PAGE->pagetype !== 'mod-book-view') {
            return;
        }

        if (!$PAGE->user_is_editing()) {
            return;
        }

        if (!has_capability('booktool/duplicate:duplicate', $PAGE->context)) {
            return;
        }

        $PAGE->requires->js_call_amd('booktool_duplicate/inject', 'init', [
            (int)$PAGE->cm->id,
            sesskey(),
            (string)get_string('duplicatechapter', 'booktool_duplicate'),
        ]);
    }
}
