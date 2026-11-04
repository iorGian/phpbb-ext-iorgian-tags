<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class add_tag_groups extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\tags\migrations\install_data'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                // Groups allowed to use a tag. No rows for a tag = everyone.
                $this->table_prefix . 'iorgian_tags_groups' => [
                    'COLUMNS' => [
                        'tag_id'   => ['UINT', 0],
                        'group_id' => ['UINT', 0],
                    ],
                    'PRIMARY_KEY' => ['tag_id', 'group_id'],
                    'KEYS' => [
                        'iorgian_tags_g_group' => ['INDEX', 'group_id'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'iorgian_tags_groups',
            ],
        ];
    }
}
