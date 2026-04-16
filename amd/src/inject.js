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

/**
 * Read a query parameter from an absolute or relative URL.
 * @param {string} url
 * @param {string} name
 * @return {string|null}
 */
const getParam = (url, name) => {
    if (!url) {
        return null;
    }
    const q = url.indexOf('?');
    if (q === -1) {
        return null;
    }
    const pairs = url.substring(q + 1).split('&');
    for (const pair of pairs) {
        const kv = pair.split('=');
        if (decodeURIComponent(kv[0]) === name) {
            return decodeURIComponent(kv[1] || '');
        }
    }
    return null;
};

/**
 * Find the chapter id for an action-list node by inspecting sibling action links.
 * @param {Element} actionList
 * @return {string|null}
 */
const chapterIdFor = (actionList) => {
    const links = actionList.querySelectorAll('a[href]');
    for (const link of links) {
        const href = link.getAttribute('href') || '';
        if (href.indexOf('delete.php') !== -1 || href.indexOf('move.php') !== -1) {
            const id = getParam(href, 'chapterid');
            if (id) {
                return id;
            }
        }
    }
    return null;
};

/**
 * Build the duplicate anchor.
 * @param {string} href
 * @param {string} label
 * @return {HTMLAnchorElement}
 */
const buildLink = (href, label) => {
    const a = document.createElement('a');
    a.setAttribute('href', href);
    a.setAttribute('title', label);
    a.className = 'booktool_duplicate-action';
    const icon = document.createElement('i');
    icon.className = 'icon fa fa-clone fa-fw';
    icon.setAttribute('aria-hidden', 'true');
    a.appendChild(icon);
    return a;
};

/**
 * @param {number} cmid
 * @param {string} sesskey
 * @param {string} label
 */
export const init = (cmid, sesskey, label) => {
    const run = () => {
        const lists = document.querySelectorAll('.book_toc .action-list');
        if (!lists.length) {
            return;
        }
        for (const list of lists) {
            if (list.querySelector('.booktool_duplicate-action')) {
                continue;
            }
            const chapterid = chapterIdFor(list);
            if (!chapterid) {
                continue;
            }
            const href = M.cfg.wwwroot + '/mod/book/tool/duplicate/duplicate.php'
                + '?id=' + encodeURIComponent(cmid)
                + '&chapterid=' + encodeURIComponent(chapterid)
                + '&sesskey=' + encodeURIComponent(sesskey);
            const link = buildLink(href, label);
            const deleteLink = list.querySelector('a[href*="delete.php"]');
            if (deleteLink) {
                list.insertBefore(link, deleteLink);
            } else {
                list.appendChild(link);
            }
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
};
