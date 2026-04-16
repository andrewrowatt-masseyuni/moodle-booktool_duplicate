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
 * Injects a "duplicate chapter" action into each book TOC row in edit mode.
 *
 * @module     booktool_duplicate/inject
 * @copyright  2026 Massey University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Read a query parameter from an absolute or relative URL.
     * @param {string} url
     * @param {string} name
     * @return {string|null}
     */
    function getParam(url, name) {
        if (!url) {
            return null;
        }
        var q = url.indexOf('?');
        if (q === -1) {
            return null;
        }
        var pairs = url.substring(q + 1).split('&');
        for (var i = 0; i < pairs.length; i++) {
            var kv = pairs[i].split('=');
            if (decodeURIComponent(kv[0]) === name) {
                return decodeURIComponent(kv[1] || '');
            }
        }
        return null;
    }

    /**
     * Find the chapter id for an action-list node by inspecting sibling action links.
     * @param {Element} actionList
     * @return {string|null}
     */
    function chapterIdFor(actionList) {
        var links = actionList.querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            if (href.indexOf('delete.php') !== -1 || href.indexOf('move.php') !== -1) {
                var id = getParam(href, 'chapterid');
                if (id) {
                    return id;
                }
            }
        }
        return null;
    }

    /**
     * Build the duplicate anchor.
     * @param {string} href
     * @param {string} label
     * @param {string} iconUrl
     * @return {HTMLAnchorElement}
     */
    function buildLink(href, label, iconUrl) {
        var a = document.createElement('a');
        a.setAttribute('href', href);
        a.setAttribute('title', label);
        a.className = 'booktool_duplicate-action';
        var img = document.createElement('img');
        img.setAttribute('src', iconUrl);
        img.setAttribute('alt', label);
        img.className = 'icon';
        img.setAttribute('aria-hidden', 'false');
        a.appendChild(img);
        return a;
    }

    return {
        /**
         * @param {number} cmid
         * @param {string} sesskey
         * @param {string} label
         * @param {string} iconUrl
         */
        init: function(cmid, sesskey, label, iconUrl) {
            var run = function() {
                var lists = document.querySelectorAll('.book_toc .action-list');
                if (!lists.length) {
                    return;
                }
                for (var i = 0; i < lists.length; i++) {
                    var list = lists[i];
                    if (list.querySelector('.booktool_duplicate-action')) {
                        continue;
                    }
                    var chapterid = chapterIdFor(list);
                    if (!chapterid) {
                        continue;
                    }
                    var href = M.cfg.wwwroot + '/mod/book/tool/duplicate/duplicate.php'
                        + '?id=' + encodeURIComponent(cmid)
                        + '&chapterid=' + encodeURIComponent(chapterid)
                        + '&sesskey=' + encodeURIComponent(sesskey);
                    list.appendChild(buildLink(href, label, iconUrl));
                }
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        }
    };
});
