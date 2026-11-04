<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\acp;

class main_module
{
    public $page_title;
    public $tpl_name;
    public $u_action;

    public function main($id, $mode)
    {
        global $phpbb_container;

        /** @var \iorgian\tags\controller\acp_controller $controller */
        $controller = $phpbb_container->get('iorgian.tags.acp_controller');
        $controller->set_page_url($this->u_action);

        switch ($mode)
        {
            case 'tags':
                $this->tpl_name = 'acp_iorgian_tags_tags';
                $this->page_title = 'ACP_IORGIAN_TAGS_TAGS';
                $controller->handle_tags();
            break;

            case 'forums':
                $this->tpl_name = 'acp_iorgian_tags_forums';
                $this->page_title = 'ACP_IORGIAN_TAGS_FORUMS';
                $controller->handle_forums();
            break;

            case 'maintenance':
                $this->tpl_name = 'acp_iorgian_tags_maintenance';
                $this->page_title = 'ACP_IORGIAN_TAGS_MAINTENANCE';
                $controller->handle_maintenance();
            break;

            case 'settings':
            default:
                $this->tpl_name = 'acp_iorgian_tags_settings';
                $this->page_title = 'ACP_IORGIAN_TAGS_SETTINGS';
                $controller->handle_settings();
            break;
        }
    }
}
