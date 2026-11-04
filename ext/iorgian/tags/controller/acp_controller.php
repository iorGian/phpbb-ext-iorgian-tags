<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\controller;

class acp_controller
{
    protected const PER_PAGE = 25;

    /** @var \iorgian\tags\service\repository */
    protected $repo;
    /** @var \iorgian\tags\service\renderer */
    protected $renderer;
    /** @var \iorgian\tags\service\prefix_bridge */
    protected $bridge;
    /** @var \iorgian\tags\service\normalizer */
    protected $normalizer;
    /** @var \iorgian\tags\service\importer */
    protected $importer;
    /** @var \iorgian\core\util\colors|null */
    protected $colors;
    /** @var \iorgian\core\util\color_field|null */
    protected $color_field;
    /** @var \iorgian\core\util\forums|null */
    protected $forums;
    /** @var \iorgian\core\util\groups|null */
    protected $groups;
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\config\db_text */
    protected $config_text;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\language\language */
    protected $language;
    /** @var string */
    protected $u_action = '';

    public function __construct(
        \iorgian\tags\service\repository $repo,
        \iorgian\tags\service\renderer $renderer,
        \iorgian\tags\service\prefix_bridge $bridge,
        \iorgian\tags\service\normalizer $normalizer,
        \iorgian\tags\service\importer $importer,
        $colors,
        $color_field,
        $forums,
        $groups,
        \phpbb\config\config $config,
        \phpbb\config\db_text $config_text,
        \phpbb\pagination $pagination,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language
    ) {
        $this->repo = $repo;
        $this->renderer = $renderer;
        $this->bridge = $bridge;
        $this->normalizer = $normalizer;
        $this->importer = $importer;
        $this->colors = $colors;
        $this->color_field = $color_field;
        $this->forums = $forums;
        $this->groups = $groups;
        $this->config = $config;
        $this->config_text = $config_text;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
    }

    public function set_page_url(string $u_action): void
    {
        $this->u_action = $u_action;
    }

    protected function boot(): void
    {
        $this->language->add_lang(['common', 'info_acp_iorgian_tags'], 'iorgian/tags');

        if ($this->colors === null || $this->color_field === null || $this->forums === null || $this->groups === null || !$this->renderer->available())
        {
            trigger_error($this->language->lang('IORGIAN_TAGS_REQUIRES_CORE'), E_USER_WARNING);
        }
    }

    protected function form_check(string $key): void
    {
        if (!check_form_key($key))
        {
            trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Settings                                                         */
    /* ---------------------------------------------------------------- */

    public function handle_settings(): void
    {
        $this->boot();
        add_form_key('iorgian_tags_settings');

        if ($this->request->is_set_post('submit'))
        {
            $this->form_check('iorgian_tags_settings');

            $this->config->set('iorgian_tags_users_create', $this->request->variable('users_create', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_max_length', max(2, min(100, $this->request->variable('max_length', 30))));
            $this->config->set('iorgian_tags_cloud_index', $this->request->variable('cloud_index', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_cloud_position', $this->request->variable('cloud_position', 0) === 1 ? 1 : 0);
            $this->config->set('iorgian_tags_cloud_max', max(1, min(200, $this->request->variable('cloud_max', 20))));
            $this->config->set('iorgian_tags_show_viewforum', $this->request->variable('show_viewforum', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_show_count', $this->request->variable('show_count', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_nav_link', $this->request->variable('nav_link', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_prefix_conflict', max(0, min(2, $this->request->variable('prefix_conflict', 1))));
            $this->config->set('iorgian_tags_search_enabled', $this->request->variable('search_enabled', 1) ? 1 : 0);
            $this->config->set('iorgian_tags_search_enabled_only', $this->request->variable('search_enabled_only', 0) ? 1 : 0);
            $this->config->set('iorgian_tags_whitelist', $this->request->variable('whitelist', 0) ? 1 : 0);
            $this->config->set('iorgian_tags_default_enabled', $this->request->variable('default_enabled', 0) ? 1 : 0);

            // Stored raw, one per line.
            $this->config_text->set('iorgian_tags_blacklist', htmlspecialchars_decode($this->request->variable('blacklist', '', true), ENT_COMPAT));

            trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        $conflict = (int) $this->config['iorgian_tags_prefix_conflict'];

        $this->template->assign_vars([
            'U_ACTION'            => $this->u_action,
            'S_USERS_CREATE'      => !empty($this->config['iorgian_tags_users_create']),
            'MAX_LENGTH'          => (int) $this->config['iorgian_tags_max_length'],
            'S_CLOUD_INDEX'       => !empty($this->config['iorgian_tags_cloud_index']),
            'CLOUD_MAX'           => (int) $this->config['iorgian_tags_cloud_max'],
            'S_CP_BOTTOM'         => (int) $this->config['iorgian_tags_cloud_position'] === 1,
            'S_SHOW_VIEWFORUM'    => !empty($this->config['iorgian_tags_show_viewforum']),
            'S_SHOW_COUNT'        => !empty($this->config['iorgian_tags_show_count']),
            'S_NAV_LINK'          => !empty($this->config['iorgian_tags_nav_link']),
            'S_DEFAULT_ENABLED'   => !empty($this->config['iorgian_tags_default_enabled']),
            'S_WHITELIST'         => !empty($this->config['iorgian_tags_whitelist']),
            'S_SEARCH_ENABLED'    => !empty($this->config['iorgian_tags_search_enabled']),
            'S_SEARCH_ENABLED_ONLY' => !empty($this->config['iorgian_tags_search_enabled_only']),
            'S_PC_NONE'           => $conflict === 0,
            'S_PC_WARN'           => $conflict === 1,
            'S_PC_BLOCK'          => $conflict === 2,
            'S_MP_ACTIVE'         => $this->bridge->active(),
            'BLACKLIST'           => htmlspecialchars((string) $this->config_text->get('iorgian_tags_blacklist'), ENT_COMPAT, 'UTF-8'),
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Tags                                                             */
    /* ---------------------------------------------------------------- */

    public function handle_tags(): void
    {
        $this->boot();
        add_form_key('iorgian_tags_tag');

        $action = $this->request->variable('action', '');
        $id = $this->request->variable('id', 0);

        switch ($action)
        {
            case 'add':
            case 'edit':
                $this->handle_edit($action, $id);
                return;

            case 'merge':
                $this->handle_merge($id);
                return;

            case 'delete':
                if (confirm_box(true))
                {
                    $this->repo->delete_tag($id);
                    trigger_error($this->language->lang('ACP_IORGIAN_TAGS_DELETED') . adm_back_link($this->u_action));
                }
                else
                {
                    confirm_box(false, $this->language->lang('ACP_IORGIAN_TAGS_DELETE_CONFIRM'), build_hidden_fields([
                        'id'     => $id,
                        'action' => 'delete',
                    ]));
                }
            break;
        }

        $this->display_list();
    }

    protected function display_list(): void
    {
        $search = htmlspecialchars_decode($this->request->variable('search', '', true), ENT_COMPAT);
        $sort = ($this->request->variable('sort', 'name') === 'count') ? 'count' : 'name';
        $start = max(0, $this->request->variable('start', 0));

        $unofficial = $this->request->variable('filter', '') === 'unofficial';

        $total = $this->repo->count_tags($search, $unofficial);
        if ($start >= $total)
        {
            $start = 0;
        }

        $group_names = $this->groups->names();
        $groups_map = $this->repo->get_groups_map();

        foreach ($this->repo->list_tags($search, $start, self::PER_PAGE, $sort, $unofficial) as $tag)
        {
            $tag_groups = [];
            foreach ($groups_map[$tag['tag_id']] ?? [] as $group_id)
            {
                if (isset($group_names[$group_id]))
                {
                    $tag_groups[] = $group_names[$group_id];
                }
            }

            $this->template->assign_block_vars('tags', [
                'GROUPS'    => $tag_groups ? htmlspecialchars(implode(', ', $tag_groups), ENT_COMPAT, 'UTF-8') : $this->language->lang('ACP_IORGIAN_TAGS_ALL_GROUPS'),
                'S_RESTRICTED' => (bool) $tag_groups,
                'S_OFFICIAL' => !empty($tag['tag_official']),
                'ID'        => $tag['tag_id'],
                'PREVIEW'   => $this->renderer->preview($tag),
                'NAME'      => htmlspecialchars($tag['tag_name'], ENT_COMPAT, 'UTF-8'),
                'SLUG'      => htmlspecialchars($tag['tag_slug'], ENT_COMPAT, 'UTF-8'),
                'COUNT'     => $tag['tag_count'],
                'U_EDIT'    => $this->u_action . '&amp;action=edit&amp;id=' . $tag['tag_id'],
                'U_MERGE'   => $this->u_action . '&amp;action=merge&amp;id=' . $tag['tag_id'],
                'U_DELETE'  => $this->u_action . '&amp;action=delete&amp;id=' . $tag['tag_id'],
            ]);
        }

        $filter_query = $unofficial ? '&amp;filter=unofficial' : '';
        $query = '&amp;sort=' . $sort . ($search !== '' ? '&amp;search=' . urlencode($search) : '') . $filter_query;

        $this->template->assign_vars([
            'S_LIST'       => true,
            'U_ACTION'     => $this->u_action,
            'U_ADD'        => $this->u_action . '&amp;action=add',
            'SEARCH'       => htmlspecialchars($search, ENT_COMPAT, 'UTF-8'),
            'SORT'         => $sort,
            'U_SORT_NAME'  => $this->u_action . '&amp;sort=name' . ($search !== '' ? '&amp;search=' . urlencode($search) : '') . $filter_query,
            'U_SORT_COUNT' => $this->u_action . '&amp;sort=count' . ($search !== '' ? '&amp;search=' . urlencode($search) : '') . $filter_query,
            'S_FILTER_UNOFFICIAL' => $unofficial,
            'U_FILTER_ALL' => $this->u_action . '&amp;sort=' . $sort . ($search !== '' ? '&amp;search=' . urlencode($search) : ''),
            'U_FILTER_UNOFFICIAL' => $this->u_action . '&amp;sort=' . $sort . ($search !== '' ? '&amp;search=' . urlencode($search) : '') . '&amp;filter=unofficial',
            'TOTAL_UNOFFICIAL' => $this->repo->count_tags('', true),
            'TOTAL'        => $total,
            'PAGE_LINKS'   => $this->page_links($this->u_action . $query, $total, $start),
        ]);
    }

    protected function handle_edit(string $action, int $id): void
    {
        $tag = ($action === 'edit') ? $this->repo->get_tag($id) : null;
        if ($action === 'edit' && !$tag)
        {
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_ERR_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
        }

        $errors = [];
        $name = $tag ? $tag['tag_name'] : '';
        $color = $tag ? $tag['tag_color'] : '';
        // Tags created in the ACP are official by default.
        $official = $tag ? !empty($tag['tag_official']) : true;
        $group_names = $this->groups->names();
        $group_ids = $tag ? $this->repo->get_tag_groups($id) : [];

        if ($this->request->is_set_post('submit'))
        {
            $this->form_check('iorgian_tags_tag');

            $name = $this->normalizer->clean(htmlspecialchars_decode($this->request->variable('tag_name', '', true), ENT_COMPAT));
            $color = trim($this->request->variable('tag_color', ''));
            $official = $this->request->variable('tag_official', 0) === 1;
            $group_ids = array_values(array_filter(
                array_map('intval', $this->request->variable('group_ids', [0])),
                function ($group_id) use ($group_names)
                {
                    return isset($group_names[$group_id]);
                }
            ));

            // Administrators may use blacklisted names; only malformed names are refused.
            if ($this->normalizer->check($name) === 'invalid')
            {
                $errors[] = $this->language->lang('ACP_IORGIAN_TAGS_ERR_NAME', $this->normalizer->max_length());
            }

            if ($color !== '')
            {
                $normalized = $this->colors->normalize($color);
                if ($normalized === null)
                {
                    $errors[] = $this->language->lang('ACP_IORGIAN_TAGS_ERR_COLOR');
                }
                else
                {
                    $color = $normalized;
                }
            }

            if (!$errors)
            {
                $message = $this->language->lang('ACP_IORGIAN_TAGS_SAVED');

                if ($tag)
                {
                    if ($this->repo->update_tag($id, $name, $color) === 'exists')
                    {
                        $errors[] = $this->language->lang('ACP_IORGIAN_TAGS_ERR_EXISTS');
                    }
                    else
                    {
                        $this->repo->set_tag_groups($id, $group_ids);
                        $this->repo->set_official($id, $official);
                    }
                }
                else
                {
                    if ($this->repo->find_by_slug($this->normalizer->slug($name)))
                    {
                        $errors[] = $this->language->lang('ACP_IORGIAN_TAGS_ERR_EXISTS');
                    }
                    else
                    {
                        $created = $this->repo->get_or_create($name, $color, $official);
                        $this->repo->set_tag_groups($created['tag_id'], $group_ids);
                    }
                }

                if (!$errors)
                {
                    $clash = $this->bridge->find_conflict($name);
                    if ($clash !== null && (int) $this->config['iorgian_tags_prefix_conflict'] > 0)
                    {
                        $message .= '<br />' . $this->language->lang('ACP_IORGIAN_TAGS_PREFIX_CONFLICT_WARN', htmlspecialchars($clash, ENT_COMPAT, 'UTF-8'));
                    }

                    trigger_error($message . adm_back_link($this->u_action));
                }
            }
        }

        foreach ($errors as $error)
        {
            $this->template->assign_block_vars('errors', ['MESSAGE' => $error]);
        }

        foreach ($group_names as $group_id => $group_name)
        {
            $this->template->assign_block_vars('groups', [
                'ID'         => $group_id,
                'NAME'       => htmlspecialchars($group_name, ENT_COMPAT, 'UTF-8'),
                'S_SELECTED' => in_array($group_id, $group_ids, true),
            ]);
        }

        $this->template->assign_vars([
            'S_EDIT'       => true,
            'S_TAG_OFFICIAL' => $official,
            'S_ERROR'      => (bool) $errors,
            'U_ACTION'     => $this->u_action . '&amp;action=' . $action . ($tag ? '&amp;id=' . $id : ''),
            'U_BACK'       => $this->u_action,
            'EDIT_TITLE'   => $this->language->lang($tag ? 'ACP_IORGIAN_TAGS_EDIT' : 'ACP_IORGIAN_TAGS_ADD'),
            'TAG_NAME'     => htmlspecialchars($name, ENT_COMPAT, 'UTF-8'),
            // The request class returns HTML-escaped strings; the field escapes the value itself.
            'TAG_COLOR_FIELD' => $this->color_field->render('tag_color', htmlspecialchars_decode($color, ENT_COMPAT), true),
            'MAX_LENGTH'   => $this->normalizer->max_length(),
        ]);
    }

    protected function handle_merge(int $id): void
    {
        $tag = $this->repo->get_tag($id);
        if (!$tag)
        {
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_ERR_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
        }

        $error = '';
        $target_name = '';

        if ($this->request->is_set_post('submit'))
        {
            $this->form_check('iorgian_tags_tag');

            $target_name = htmlspecialchars_decode($this->request->variable('target', '', true), ENT_COMPAT);
            $target = $this->repo->find_by_slug($this->normalizer->slug($target_name));

            if (!$target || $target['tag_id'] === $id)
            {
                $error = $this->language->lang('ACP_IORGIAN_TAGS_ERR_TARGET');
            }
            else
            {
                $this->repo->merge($id, $target['tag_id']);
                trigger_error($this->language->lang('ACP_IORGIAN_TAGS_MERGED') . adm_back_link($this->u_action));
            }
        }

        $this->template->assign_vars([
            'S_MERGE'     => true,
            'S_ERROR'     => $error !== '',
            'ERROR_MSG'   => $error,
            'U_ACTION'    => $this->u_action . '&amp;action=merge&amp;id=' . $id,
            'U_BACK'      => $this->u_action,
            'MERGE_FROM'  => htmlspecialchars($tag['tag_name'], ENT_COMPAT, 'UTF-8'),
            'TARGET'      => htmlspecialchars($target_name, ENT_COMPAT, 'UTF-8'),
        ]);
    }

    /** Compact page links (HTML) with a window around the current page. */
    protected function page_links(string $base, int $total, int $start): string
    {
        $pages = (int) ceil($total / self::PER_PAGE);
        if ($pages < 2)
        {
            return '';
        }

        $current = (int) floor($start / self::PER_PAGE);
        $html = '';
        $last = -1;

        for ($i = 0; $i < $pages; $i++)
        {
            if ($i > 1 && $i < $pages - 2 && abs($i - $current) > 2)
            {
                continue;
            }
            if ($last !== -1 && $i - $last > 1)
            {
                $html .= ' &hellip; ';
            }

            $html .= ($i === $current)
                ? ' <strong>' . ($i + 1) . '</strong> '
                : ' <a href="' . $base . '&amp;start=' . ($i * self::PER_PAGE) . '">' . ($i + 1) . '</a> ';
            $last = $i;
        }

        return $html;
    }

    /* ---------------------------------------------------------------- */
    /* Forums                                                           */
    /* ---------------------------------------------------------------- */

    public function handle_forums(): void
    {
        $this->boot();
        add_form_key('iorgian_tags_forums');

        // Read live from the forums table (core helper): new forums show up immediately.
        $forums = $this->forums->postable();

        if ($this->request->is_set_post('submit') || $this->request->is_set_post('enable_all') || $this->request->is_set_post('disable_all'))
        {
            $this->form_check('iorgian_tags_forums');

            $enabled = $this->request->variable('enabled', [0 => 0]);
            $mins = $this->request->variable('min_tags', [0 => 0]);
            $maxs = $this->request->variable('max_tags', [0 => 0]);
            $all = $this->request->is_set_post('enable_all');
            $none = $this->request->is_set_post('disable_all');

            foreach ($forums as $row)
            {
                $forum_id = (int) $row['forum_id'];
                $min = max(0, min(20, (int) ($mins[$forum_id] ?? 0)));
                $max = max(0, min(20, (int) ($maxs[$forum_id] ?? 0)));
                if ($max > 0 && $min > $max)
                {
                    $min = $max;
                }

                $on = $all ? true : ($none ? false : isset($enabled[$forum_id]));
                $this->repo->set_forum_config($forum_id, $on, $min, $max);
            }

            trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        $this->template->assign_var('U_ACTION', $this->u_action);

        foreach ($forums as $row)
        {
            $forum_id = (int) $row['forum_id'];
            $cfg = $this->repo->get_forum_config($forum_id);
            $this->template->assign_block_vars('forums', [
                'ID'        => $forum_id,
                'NAME'      => $row['forum_name'],
                'PAD'       => str_repeat('&nbsp;&nbsp;&nbsp;', (int) $row['depth']),
                'S_ENABLED' => $cfg['enabled'],
                'MIN'       => $cfg['min'],
                'MAX'       => $cfg['max'],
            ]);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Maintenance                                                      */
    /* ---------------------------------------------------------------- */

    public function handle_maintenance(): void
    {
        $this->boot();
        add_form_key('iorgian_tags_maintenance');

        if ($this->request->is_set_post('resync'))
        {
            $this->form_check('iorgian_tags_maintenance');
            $this->repo->recount_all();
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_RESYNC_DONE') . adm_back_link($this->u_action));
        }

        if ($this->request->is_set_post('purge'))
        {
            $this->form_check('iorgian_tags_maintenance');
            $this->repo->recount_all();
            $deleted = $this->repo->delete_unused();
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_PURGE_DONE', $deleted) . adm_back_link($this->u_action));
        }

        if ($this->request->is_set_post('mark_official'))
        {
            $this->form_check('iorgian_tags_maintenance');
            $marked = $this->repo->mark_all_official();
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_MARK_OFFICIAL_DONE', $marked) . adm_back_link($this->u_action));
        }

        if ($this->request->is_set_post('import_rh'))
        {
            $this->form_check('iorgian_tags_maintenance');
            [$tags, $assignments, $forums] = $this->importer->run();
            trigger_error($this->language->lang('ACP_IORGIAN_TAGS_IMPORT_RH_DONE', $tags, $assignments, $forums) . adm_back_link($this->u_action));
        }

        $this->template->assign_vars([
            'U_ACTION'       => $this->u_action,
            'S_RH_AVAILABLE' => $this->importer->available(),
            'S_MP_ACTIVE'    => $this->bridge->active(),
            'TOTAL_TAGS'     => $this->repo->count_tags(),
            'TOTAL_UNOFFICIAL' => $this->repo->count_tags('', true),
        ]);
    }
}
