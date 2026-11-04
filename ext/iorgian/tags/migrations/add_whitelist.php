<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\migrations;

class add_whitelist extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\tags\migrations\add_search_integration'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                // 1 = official tag: usable by everybody when the whitelist mode is on
                $this->table_prefix . 'iorgian_tags' => [
                    'tag_official' => ['BOOL', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'iorgian_tags' => ['tag_official'],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['iorgian_tags_whitelist', 0]],
            // Existing tags start as official, so turning the whitelist on does not empty the vocabulary.
            ['custom', [[$this, 'mark_existing_official']]],
        ];
    }

    public function mark_existing_official()
    {
        $this->db->sql_query('UPDATE ' . $this->table_prefix . 'iorgian_tags SET tag_official = 1');
    }
}
