<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\acp;

class main_info
{
    public function module()
    {
        $auth = 'ext_iorgian/tags && acl_a_board';

        return [
            'filename' => '\iorgian\tags\acp\main_module',
            'title'    => 'ACP_IORGIAN_TAGS_TITLE',
            'modes'    => [
                'settings'    => ['title' => 'ACP_IORGIAN_TAGS_SETTINGS', 'auth' => $auth, 'cat' => ['ACP_IORGIAN_TAGS_TITLE']],
                'tags'        => ['title' => 'ACP_IORGIAN_TAGS_TAGS', 'auth' => $auth, 'cat' => ['ACP_IORGIAN_TAGS_TITLE']],
                'forums'      => ['title' => 'ACP_IORGIAN_TAGS_FORUMS', 'auth' => $auth, 'cat' => ['ACP_IORGIAN_TAGS_TITLE']],
                'maintenance' => ['title' => 'ACP_IORGIAN_TAGS_MAINTENANCE', 'auth' => $auth, 'cat' => ['ACP_IORGIAN_TAGS_TITLE']],
            ],
        ];
    }
}
