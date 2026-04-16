# booktool_duplicate — Duplicate book chapter

A Moodle `mod_book` subplugin that adds a "duplicate chapter" action to the
book's table of contents while editing is on. Duplicating a top-level chapter
also duplicates its subchapters; the copy is inserted immediately after the
original, with subsequent chapters renumbered.

## How it works

- A hook callback on `\core\hook\output\before_standard_top_of_body_html_generation`
  detects when the current page is `mod/book/view.php` in edit mode and the user
  has `booktool/duplicate:duplicate`, then queues a small AMD module.
- The AMD module (`booktool_duplicate/inject`) finds each `.book_toc
  .action-list` on the page and appends a copy icon linked to
  `mod/book/tool/duplicate/duplicate.php`.
- `duplicate.php` validates the request, clones the chapter row(s) (and any
  contiguous subchapters of a top-level source), copies the chapter file area,
  shifts subsequent pagenums, copies tags, triggers `chapter_created` events,
  and redirects back to `view.php`.

No core Moodle files are modified.

## Installation

Place the plugin at `mod/book/tool/duplicate/` in your Moodle codebase and run
the upgrade (visit site admin or `php admin/cli/upgrade.php`).

## Capability

`booktool/duplicate:duplicate` — granted to `editingteacher` and `manager` by
default; cloned from `mod/book:edit`.

## Requirements

- Moodle 4.5+ (uses the 4.4 hook API).

## License

GPL-3.0-or-later.
