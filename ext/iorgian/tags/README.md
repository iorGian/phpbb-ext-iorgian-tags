# Iorgian Tags (iorgian/tags)

Topic tags for phpBB 3.3.x, built on top of [iorgian/core](https://nictops.net) and fully compatible with iorgian/multiprefixes. A modern replacement for RH Topic Tags.

**Status: 1.0.0-rc1 (release candidate).** Feature complete. Tested on MySQL; the PostgreSQL and Sphinx search filters have not been tested on real installations yet. Back up your database before installing.

## Requirements

- phpBB 3.3.0 or newer (PHP 7.4+)
- iorgian/core 0.2.4-a1 or newer, installed and enabled
- Optional: iorgian/multiprefixes (both extensions cooperate, see changelog.md)

## Installation

1. Copy the `tags` folder to `ext/iorgian/tags`.
2. ACP > Customise > Manage extensions > enable "Iorgian Tags".
3. ACP > Extensions > Tags > Forums: enable tags in the forums you want.
4. Give the forum permission "Can use Tags" to the groups that may tag topics (the standard roles already include it).
5. Search by tags: user permission "Can search by tags" (ACP > Permissions > User permissions > Misc). The standard registered-user roles get it on install; guests and bots do not.

## Upgrading

Replace the folder. When the new version adds a migration (0.1.1, 0.1.2, 0.2.0 and 0.2.1), disable and re-enable the extension once; 0.2.2-a1 and 1.0.0-rc1 have no database changes. Always update `iorgian/core` first.

See `changelog.md` for the complete feature list, to do, roadmap and history.

## License

GPL-2.0-only
