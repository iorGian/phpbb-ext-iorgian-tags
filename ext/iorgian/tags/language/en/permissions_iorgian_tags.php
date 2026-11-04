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
	'ACL_F_IORGIAN_TAGS_USE' => 'Can use Tags',
	'ACL_U_IORGIAN_TAGS_SEARCH' => 'Can search by tags',
]);
