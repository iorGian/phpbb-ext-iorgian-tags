<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class install_data extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\tags\migrations\install_schema'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['iorgian_tags_users_create', 1]],
            ['config.add', ['iorgian_tags_max_length', 30]],
            ['config.add', ['iorgian_tags_cloud_index', 1]],
            ['config.add', ['iorgian_tags_cloud_max', 20]],
            ['config.add', ['iorgian_tags_show_viewforum', 1]],
            ['config.add', ['iorgian_tags_show_count', 1]],
            ['config.add', ['iorgian_tags_nav_link', 1]],
            // 0 = ignore, 1 = warn in the ACP, 2 = also block users in posting
            ['config.add', ['iorgian_tags_prefix_conflict', 1]],
            ['config.add', ['iorgian_tags_default_enabled', 0]],
            ['config_text.add', ['iorgian_tags_blacklist', '']],

            // Local per-forum permission: "Can use Tags"
            ['permission.add', ['f_iorgian_tags_use', false]],
            ['permission.permission_set', ['ROLE_FORUM_FULL', 'f_iorgian_tags_use']],
            ['permission.permission_set', ['ROLE_FORUM_STANDARD', 'f_iorgian_tags_use']],
            ['permission.permission_set', ['ROLE_FORUM_POLLS', 'f_iorgian_tags_use']],
            ['permission.permission_set', ['ROLE_FORUM_ONQUEUE', 'f_iorgian_tags_use']],

            // ACP > Extensions > Tags
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_IORGIAN_TAGS_TITLE']],
            ['module.add', ['acp', 'ACP_IORGIAN_TAGS_TITLE', [
                'module_basename' => '\iorgian\tags\acp\main_module',
                'modes'           => ['settings', 'tags', 'forums', 'maintenance'],
            ]]],
        ];
    }
}
