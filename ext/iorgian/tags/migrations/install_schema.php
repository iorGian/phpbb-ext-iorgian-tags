<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class install_schema extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\core\migrations\install_core'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'iorgian_tags' => [
                    'COLUMNS' => [
                        'tag_id'    => ['UINT', null, 'auto_increment'],
                        'tag_name'  => ['VCHAR_UNI:100', ''],
                        'tag_slug'  => ['VCHAR_UNI:100', ''],
                        'tag_color' => ['VCHAR:7', ''],
                        'tag_count' => ['UINT', 0],
                    ],
                    'PRIMARY_KEY' => 'tag_id',
                    'KEYS' => [
                        'iorgian_tags_slug'  => ['UNIQUE', 'tag_slug'],
                        'iorgian_tags_count' => ['INDEX', 'tag_count'],
                    ],
                ],
                $this->table_prefix . 'iorgian_tags_topics' => [
                    'COLUMNS' => [
                        'topic_id' => ['UINT', 0],
                        'tag_id'   => ['UINT', 0],
                    ],
                    'PRIMARY_KEY' => ['topic_id', 'tag_id'],
                    'KEYS' => [
                        'iorgian_tags_t_tag' => ['INDEX', 'tag_id'],
                    ],
                ],
                // Own table instead of a column on phpbb_forums: no core table is altered
                // and a new forum never needs a cache purge to show up.
                $this->table_prefix . 'iorgian_tags_forum_cfg' => [
                    'COLUMNS' => [
                        'forum_id'     => ['UINT', 0],
                        'tags_enabled' => ['BOOL', 0],
                        'min_tags'     => ['TINT:3', 0],
                        'max_tags'     => ['TINT:3', 0],
                    ],
                    'PRIMARY_KEY' => 'forum_id',
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'iorgian_tags',
                $this->table_prefix . 'iorgian_tags_topics',
                $this->table_prefix . 'iorgian_tags_forum_cfg',
            ],
        ];
    }
}
