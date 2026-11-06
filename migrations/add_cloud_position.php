<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class add_cloud_position extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\tags\migrations\add_tag_groups'];
    }

    public function update_data()
    {
        return [
            // 0 = above the forum list (default, as in 0.1.0 / 0.1.1), 1 = below it
            ['config.add', ['iorgian_tags_cloud_position', 0]],
        ];
    }
}
