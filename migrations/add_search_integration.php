<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class add_search_integration extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\tags\migrations\add_cloud_position'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['iorgian_tags_search_enabled', 1]],
            // 1 = tag searches only look at forums where tags are enabled
            ['config.add', ['iorgian_tags_search_enabled_only', 0]],

            // Global user permission: "Can search by tags". Default: registered users (standard and full roles).
            ['permission.add', ['u_iorgian_tags_search', true]],
            ['permission.permission_set', ['ROLE_USER_FULL', 'u_iorgian_tags_search']],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_iorgian_tags_search']],
        ];
    }
}
