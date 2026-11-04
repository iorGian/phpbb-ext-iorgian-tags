# Iorgian Tags (phpBB extension)

Topic tags for phpBB 3.3.x, built on [Iorgian Core](https://github.com/iorGian/phpbb-ext-iorgian-core) and compatible with [Iorgian Multi Prefixes](https://github.com/iorGian/phpbb-ext-iorgian-multiprefixes). A modern replacement for RH Topic Tags.

**Status: 1.0.0-rc1 (release candidate).** Feature complete and tested on MySQL. The PostgreSQL and Sphinx search filters have not been tested on real installations yet. Back up your database before installing.

## Contents

- `ext/iorgian/tags/` - the extension (vendor `iorgian`, name `tags`)
  - `README.md` and `changelog.md` - requirements, installation, full feature list, known limitations, to do, roadmap and history

## Requirements

- phpBB 3.3.0 or newer, PHP 7.4+
- [Iorgian Core](https://github.com/iorGian/phpbb-ext-iorgian-core) 0.2.4-a1 or newer, installed and enabled (it is a separate package)
- Optional: Iorgian Multi Prefixes

## Installation

1. Download the release zip and copy `ext/iorgian/tags` to the `ext/iorgian/tags` folder of your forum.
2. ACP > Customise > Manage extensions > enable **Iorgian Tags**.
3. ACP > Extensions > Tags > Forums: enable tags in the forums you want.
4. Give the permission "Can use Tags" to the groups that may tag topics, and "Can search by tags" to those that may search by tags (the standard registered-user roles get both on install).

Upgrading: update Iorgian Core first, then replace the folder. When a release adds a migration, disable and re-enable the extension once (see `changelog.md`).

## Highlights

- Tag field when posting, with autocomplete; colored tags; per-forum settings; blacklist and whitelist mode
- Per-tag group restrictions
- Tag pages (`/tag/a,b` and `/tag/a,b/OR`), tag cloud, tags in forum lists, UCP and MCP lists
- phpBB search integration (native, MySQL, PostgreSQL and Sphinx backends) with a user permission
- Import from RH Topic Tags
- Languages: English, Spanish, Romanian

## Notes

- Tags stay on a topic when it is moved to another forum.
- Pre-made searches (new posts, unread...) and "search in this topic" ignore the tag filter.
- Full details, to do and roadmap: `ext/iorgian/tags/changelog.md`.

## Author

Georgian Iordache (iorG19 on phpbb.com, [iorGian](https://github.com/iorGian) on GitHub)

## License

GNU General Public License, version 2 (GPL-2.0-only). See `LICENSE`.
