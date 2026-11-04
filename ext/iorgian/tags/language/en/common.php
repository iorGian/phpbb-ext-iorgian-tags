<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'IORGIAN_TAGS_REQUIRES_CORE' => 'Tags requires “Iorgian Core” 0.2.4-a1 or newer to be installed and enabled first.',
	'IORGIAN_TAGS_LABEL' => 'Tags',
	'IORGIAN_TAGS_HELP' => 'Separate tags with commas.',
	'IORGIAN_TAGS_HELP_MIN' => 'Enter at least %d.',
	'IORGIAN_TAGS_HELP_MAX' => 'Up to %d tags.',
	'IORGIAN_TAGS_HELP_MIN_MAX' => 'Between %1$d and %2$d tags.',
	'IORGIAN_TAGS_HELP_NO_CREATE' => 'You can only use existing tags.',
	'IORGIAN_TAGS_ERR_INVALID' => 'Invalid tag(s): %s. Allowed: letters, numbers, spaces and - _ . + # (within the maximum length).',
	'IORGIAN_TAGS_ERR_BLACKLISTED' => 'These tags are not allowed: %s',
	'IORGIAN_TAGS_ERR_MIN' => 'You must enter at least %d tag(s).',
	'IORGIAN_TAGS_ERR_MAX' => 'You can enter at most %d tag(s).',
	'IORGIAN_TAGS_ERR_NOT_EXIST' => 'These tags do not exist and you are not allowed to create new ones: %s',
	'IORGIAN_TAGS_ERR_PREFIX_CONFLICT' => 'These tags have the same name as a prefix: %s. Use the prefix instead.',
	'IORGIAN_TAGS_NAV' => 'Tags',
	'IORGIAN_TAGS_CLOUD' => 'Tag cloud',
	'IORGIAN_TAGS_CLOUD_TITLE' => 'All tags',
	'IORGIAN_TAGS_NO_TAGS' => 'There are no tags yet.',
	'IORGIAN_TAGS_TOPICS_TITLE' => 'Topics tagged: %s',
	'IORGIAN_TAGS_TOPICS_HEADING' => 'Tagged topics',
	'IORGIAN_TAGS_MATCH' => 'Match',
	'IORGIAN_TAGS_MODE_AND' => 'All of the tags',
	'IORGIAN_TAGS_MODE_OR' => 'Any of the tags',
	'IORGIAN_TAGS_TOTAL_TOPICS' => '%d topic(s)',
	'IORGIAN_TAGS_NO_TOPICS' => 'No visible topics have these tags.',
	'IORGIAN_TAGS_IN_FORUM' => 'in',
	'IORGIAN_TAGS_BY' => 'by',
	'IORGIAN_TAGS_NOT_FOUND' => 'No tag specified.',
	'IORGIAN_TAGS_SEARCH_EXPLAIN' => 'Separate tags with commas. Can be combined with keywords and author.',
	'IORGIAN_TAGS_SEARCH_FILTER' => 'Tags filter',
	'IORGIAN_TAGS_SEARCH_NOT_ALLOWED' => 'You are not allowed to search by tags.',
	'IORGIAN_TAGS_SEARCH_BACKEND' => 'Searching by tags together with keywords or an author is not supported by this search backend. Use the native, MySQL, PostgreSQL or Sphinx search, search by tags alone, or remove the tags.',
	'IORGIAN_TAGS_ERR_RESTRICTED' => 'These tags are reserved for other user groups: %s',
	'IORGIAN_TAGS_LOCKED' => 'Reserved tags (you cannot change them)',
	'IORGIAN_TAGS_ERR_NOT_OFFICIAL' => 'These tags are not on the allowed list: %s',
	'IORGIAN_TAGS_HELP_WHITELIST' => 'Only tags from the allowed list can be used.',
	'IORGIAN_TAGS_SEARCH_TOO_MANY' => 'These tags match too many topics for this search engine. Use more specific tags.',
]);
