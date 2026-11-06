# Iorgian Tags (iorgian/tags) — changelog

## Description

Topic tagging for phpBB 3.3.x. Users tag a topic when they create it or edit its first post; tags are clickable, searchable (AND / OR), shown in topic lists and in a tag cloud, and managed from a modern ACP. It is a new extension (not a fork) that reuses the shared services of `iorgian/core` and is designed to live side by side with `iorgian/multiprefixes`.

## Requirements

- phpBB 3.3.0+ (4.0 ready through `iorgian/core`), PHP 7.4+
- `iorgian/core` 0.2.4-a1 or newer (required)
- `iorgian/multiprefixes` (optional)

## Features

- Tag field in the posting form (new topic or first post), comma separated, with autocomplete (plain JS, no libraries)
- Tags stored once and shared: case-insensitive and URL-safe identifier ("C++" = `c-plus-plus`), first spelling wins
- Colored tags (optional color per tag, rendered with the shared core badge); the ACP uses the shared color field of `iorgian/core` (color selector in real time, no hex codes needed, plus a "Default" button; typed #rgb / #rrggbb codes still work)
- Per-forum settings in its own table: enable/disable, minimum and maximum tags per topic (no column added to `phpbb_forums`; new forums appear immediately, no cache purge)
- Permission `f_iorgian_tags_use` (local, per forum); moderators with edit permission can always create tags
- Per-tag group restrictions: a tag can be limited to selected user groups (empty = everyone). The tag stays visible to everybody; only adding it to topics is restricted. Restricted tags already on a topic are kept (shown read-only) when someone without access edits the topic, and they still count for the forum minimum / maximum. Moderators with edit permission are not restricted, and the autocomplete hides tags the user cannot use
- Option to forbid users from creating new tags (they pick existing ones only) and a blacklist
- Whitelist mode (new in 0.2.1): when on, users can only add *official* tags and cannot create new ones; moderators with edit permission are not restricted and tags already on topics stay. Each tag has an "Official" flag (ACP: edit form, column, "not official" filter, and a maintenance action that marks every tag as official). Existing tags become official on upgrade; tags created in the ACP, by moderators in whitelist mode, or imported are official; tags created by users while the mode is off start as not official
- Tags shown under the topic title in forum lists, in UCP > Bookmarks and Subscribed topics, in the MCP forum view (moderator topic list), and in the footer of the first post in a topic
- Tag pages: `/tag/a,b` (all of the tags) and `/tag/a,b/OR` (any of the tags) — same URL scheme as RH Topic Tags, so old links keep working
- Tag cloud page (`/tags`) and optional cloud on the board index, shown above or below the forum list (ACP setting); counts only include topics the visitor can read
- Optional "Tags" link in the navigation bar; tags added to `meta keywords` in topics
- ACP: settings, tag manager (search, sort, add, rename, recolor, merge, delete), per-forum settings, maintenance (recalculate counters, delete unused tags)
- phpBB search integration (new in 0.2.0): a "Tags" field with autocomplete and an AND / OR choice in the advanced search form; the same parameters work in URLs (`search.php?tags=linux,debian&tagmode=OR`).
  - Tags only: a list of topics with those tags (no keywords or author needed), sorted and paginated like any other search.
  - Tags + keywords and/or author: the filter is added to the query of the native and MySQL fulltext backends (through the events those backends expose) and to their cache key, so results are exact, paginated and never mixed with searches without tags.
  - Forum selection, "search subforums", read permissions, password-protected forums and post visibility are those of phpBB. Optional setting: only search forums where tags are enabled.
  - Who can use it: user permission "Can search by tags" (default: registered users). Settings page switch to turn it off.
  - Tags are shown in the search results (under the topic title, and in the post details when results are posts), and the active filter is shown above the results.
  - Backends: native, MySQL and PostgreSQL fulltext (the filter is added to their queries and cache keys) and Sphinx (the filter becomes a `topic_id` attribute filter; up to 4000 matching topics, otherwise the user is asked to use more specific tags). Any other backend shows an explanatory message when tags are combined with keywords or an author; tags alone work everywhere.
  - Limits: pre-made searches (new posts, unread, active topics...) and "search in this topic" ignore the tag filter.
- Import from RH Topic Tags (tags, topic assignments and enabled forums); source data is never modified
- Languages: English, Spanish, Romanian

### Compatibility with iorgian/multiprefixes

- Separate tables, config keys, permission, language keys, template variables and form fields (`iorgian_tags_*` / `IORGIAN_TAGS_*`): nothing is shared or overwritten
- Both extensions can validate the same posting form; errors are appended, never replaced
- Optional services (`@?iorgian.multiprefixes.*`): if Multi Prefixes is missing or disabled, Tags keeps working
- Tag pages show the Multi Prefixes badges before each topic title
- Same-name detection: ACP warning when a tag has the same name as a prefix, and an optional rule that blocks users from using such tags when posting

## Known limitations

- The PostgreSQL and Sphinx search filters were written from the phpBB backend sources and have not been run on real installations yet
- Tags combined with keywords or an author need the native, MySQL, PostgreSQL or Sphinx search backend; pre-made searches (new posts, unread...) and "search in this topic" ignore the tag filter
- Tags stay on a topic when it is moved to another forum

## To do

- Test the PostgreSQL and Sphinx search filters on real installations (written from the phpBB backend sources, not run yet)

## Roadmap

- Combine the tag filter with Multi Prefixes' clickable prefixes in one search
- Tag groups / sets (same idea as prefix groups)
- Tag aliases (synonyms) and optional description per tag
- Related topics by shared tags
- Move the generic text helper (`normalizer`) into `iorgian/core`
- Tool to convert a Multi Prefixes prefix into a tag, and the other way round
- phpBB 4.0 support through `iorgian.core.compat`

## Changelog

### 1.0.0-rc1

- Release candidate: no new features
- The extension is listed as "Iorgian Tags" in the extensions list, and the author is Georgian Iordache (composer.json and copyright headers)
- Pre-release audit of the package: migration chain (install and upgrade from 0.1.0, and removal of the data with "Delete data"), configuration keys and permissions created vs used, routes, class and namespace names, language files (same keys and placeholders in en / es / ro), and every phpBB event used in the search, UCP and MCP checked against the phpBB sources
- Known limitations are now listed in this file and in the README

### 0.2.2-a1

- Tags in UCP > Bookmarks, UCP > Subscribed topics and the MCP forum view (same switch as the forum lists: "Show tags in topic lists")
- No database changes: no need to disable and re-enable the extension

### 0.2.1-a1

- Whitelist mode with an "Official" flag per tag (new column `tag_official`, new setting; existing tags become official)
- Search filter for the PostgreSQL fulltext and Sphinx backends (in addition to native and MySQL)
- Posting: separate error messages for tags reserved to other groups and for tags that are not on the allowed list
- Upgrade: replace the folder, then **disable and re-enable** the extension (new migration). Before turning the whitelist mode on, check the tags marked "not official" in ACP > Tags
- Languages: en, es, ro updated

### 0.2.0-a1

- Integration with the phpBB search (see Features): form field, tags-only searches, tags combined with keywords / author on the native and MySQL fulltext backends, tags shown in the results, new user permission "Can search by tags", new ACP options (enable, search only in forums with tags enabled)
- Upgrade: replace the folder, then **disable and re-enable** the extension (new migration with the permission and settings). Check that the groups that should search by tags have "Can search by tags" (the standard registered-user roles get it automatically)
- Languages: en, es, ro updated

### 0.1.4-a1

- The ACP color selector now comes from `iorgian/core` 0.2.4-a1 (`iorgian.core.color_field`, shared with Multi Prefixes); the copy inside Tags was removed
- **Requires Iorgian Core 0.2.4-a1 or newer** (update Core first). No database changes: no need to disable and re-enable the extension

### 0.1.3-a1

- ACP: color selector for tags (same approach as Multi Prefixes) with a "Default" button; no database change, no need to re-enable the extension
- Languages: en, es, ro updated

### 0.1.2-a1

- Board index: the tag cloud can be shown above the forum list (default) or below it, before the "Who is online" block (ACP > Settings)
- New migration (config `iorgian_tags_cloud_position`): disable and re-enable the extension after replacing the folder
- Languages: en, es, ro updated

### 0.1.1-a1

- Per-tag group restrictions (new table `iorgian_tags_groups`, new ACP field and column)
- Autocomplete and posting validation respect the restrictions; new error message for reserved tags
- Upgrade: replace the folder, then disable and re-enable the extension so the new migration runs
- Languages: en, es, ro updated

### 0.1.0-a1

- First alpha release
- Complete first version: posting, validation, storage, display, tag pages, cloud, ACP, maintenance, RH Topic Tags import
- Languages: en, es, ro
