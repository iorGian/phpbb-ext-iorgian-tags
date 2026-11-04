<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Compatibility with iorgian/multiprefixes by design:
 *  - own prefix everywhere (iorgian_tags_*, IORGIAN_TAGS_*, f_iorgian_tags_use, iorgian_tags[]),
 *  - own template variables, never reusing IORGIAN_MP_*,
 *  - posting errors are appended to $event['error'], never replaced.
 */
class listener implements EventSubscriberInterface
{
    /** @var \iorgian\tags\service\repository */
    protected $repo;
    /** @var \iorgian\tags\service\renderer */
    protected $renderer;
    /** @var \iorgian\tags\service\prefix_bridge */
    protected $bridge;
    /** @var \iorgian\tags\service\normalizer */
    protected $normalizer;
    /** @var \iorgian\core\permission\acl_helper|null */
    protected $acl;
    /** @var \phpbb\auth\auth */
    protected $auth;
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\language\language */
    protected $language;
    /** @var \phpbb\controller\helper */
    protected $helper;

    /** @var array<int,array[]> */
    protected $topic_tags = [];
    /** @var bool */
    protected $preloaded = false;
    /** @var string tags of the topic being viewed (shown in its first post only) */
    protected $topic_tags_html = '';

    public function __construct(
        \iorgian\tags\service\repository $repo,
        \iorgian\tags\service\renderer $renderer,
        \iorgian\tags\service\prefix_bridge $bridge,
        \iorgian\tags\service\normalizer $normalizer,
        $acl,
        \phpbb\auth\auth $auth,
        \phpbb\config\config $config,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language,
        \phpbb\controller\helper $helper
    ) {
        $this->repo = $repo;
        $this->renderer = $renderer;
        $this->bridge = $bridge;
        $this->normalizer = $normalizer;
        $this->acl = $acl;
        $this->auth = $auth;
        $this->config = $config;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
        $this->helper = $helper;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'                            => 'load_language',
            'core.permissions'                           => 'add_permissions',
            'core.page_header'                           => 'page_header',
            'core.index_modify_page_title'               => 'index_cloud',
            'core.posting_modify_template_vars'          => 'posting_template',
            'core.posting_modify_submission_errors'      => 'posting_validate',
            'core.submit_post_end'                       => 'posting_save',
            'core.viewforum_modify_topics_data'          => 'forum_preload',
            'core.viewforum_modify_topicrow'             => 'forum_row',
            'core.viewtopic_assign_template_vars_before' => 'topic_view',
            'core.viewtopic_modify_post_row'             => 'post_row',
            'core.delete_topics_before_query'            => 'delete_topics',
            'core.ucp_main_topiclist_topic_modify_template_vars' => 'ucp_topic_row',
            'core.mcp_view_forum_modify_topicrow'        => 'mcp_topic_row',
        ];
    }

    /** Without iorgian/core the extension stays inactive instead of breaking the board. */
    protected function ready(): bool
    {
        return $this->acl !== null && $this->repo->is_complete() && $this->renderer->available();
    }

    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = ['ext_name' => 'iorgian/tags', 'lang_set' => 'common'];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function add_permissions($event)
    {
        $permissions = $event['permissions'];
        $permissions['f_iorgian_tags_use'] = ['lang' => 'ACL_F_IORGIAN_TAGS_USE', 'cat' => 'post'];
        $permissions['u_iorgian_tags_search'] = ['lang' => 'ACL_U_IORGIAN_TAGS_SEARCH', 'cat' => 'misc'];
        $event['permissions'] = $permissions;
    }

    public function page_header($event)
    {
        if ($this->ready() && !empty($this->config['iorgian_tags_nav_link']))
        {
            $this->template->assign_vars([
                'S_IORGIAN_TAGS_NAV' => true,
                'U_IORGIAN_TAGS'     => $this->helper->route('iorgian_tags_cloud'),
            ]);
        }
    }

    public function index_cloud($event)
    {
        if (!$this->ready() || empty($this->config['iorgian_tags_cloud_index']))
        {
            return;
        }

        $tags = $this->repo->cloud(max(1, (int) $this->config['iorgian_tags_cloud_max']));
        if (!$tags)
        {
            return;
        }

        $show_count = !empty($this->config['iorgian_tags_show_count']);
        foreach ($tags as $tag)
        {
            $this->template->assign_block_vars('iorgian_tags_cloud', [
                'HTML' => $this->renderer->render_one($tag, 'iorgian-tag-lv' . $tag['level'], $show_count ? $tag['uses'] : null),
            ]);
        }

        $this->template->assign_vars([
            'S_IORGIAN_TAGS_CLOUD'        => true,
            'S_IORGIAN_TAGS_CLOUD_BOTTOM' => (int) $this->config['iorgian_tags_cloud_position'] === 1,
            'U_IORGIAN_TAGS_ALL'     => $this->helper->route('iorgian_tags_cloud'),
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Posting                                                          */
    /* ---------------------------------------------------------------- */

    public function posting_template($event)
    {
        if (!$this->ready())
        {
            return;
        }

        $mode = (string) $event['mode'];
        $post_data = $event['post_data'];

        if (!$this->selector_allowed($mode, $post_data))
        {
            return;
        }

        $forum_id = $this->forum_id($post_data);
        if (!$forum_id || !$this->tags_available($forum_id))
        {
            return;
        }

        // Restricted tags of this topic that the user may not change are shown read-only.
        $locked = $this->locked_tags($post_data, $mode, $forum_id);

        if ($this->request->is_set_post('iorgian_tags_present'))
        {
            $value = $this->request->variable('iorgian_tags', '', true); // already HTML-escaped by phpBB
        }
        else if ($mode === 'edit' && !empty($post_data['topic_id']))
        {
            $names = [];
            foreach ($this->repo->get_topic_tags((int) $post_data['topic_id']) as $tag)
            {
                if (!isset($locked[$tag['tag_slug']]))
                {
                    $names[] = $tag['tag_name'];
                }
            }
            $value = htmlspecialchars(implode(', ', $names), ENT_COMPAT, 'UTF-8');
        }
        else
        {
            $value = '';
        }

        $this->template->assign_vars([
            'S_IORGIAN_TAGS'          => true,
            'IORGIAN_TAGS_VALUE'      => $value,
            'IORGIAN_TAGS_HELP'       => $this->help_text($forum_id),
            'IORGIAN_TAGS_MAXLEN'     => $this->normalizer->max_length(),
            'IORGIAN_TAGS_FORUM'      => $forum_id,
            'IORGIAN_TAGS_LOCKED_HTML' => $locked ? $this->renderer->render(array_values($locked)) : '',
            'U_IORGIAN_TAGS_SUGGEST'  => $this->helper->route('iorgian_tags_suggest'),
        ]);
    }

    public function posting_validate($event)
    {
        // Only when the form is really submitted, and only if the tag field was part of it.
        if (!$this->ready() || !$this->request->is_set_post('post') || !$this->request->is_set_post('iorgian_tags_present'))
        {
            return;
        }

        $mode = $this->request->variable('mode', '');
        $post_data = $event['post_data'];

        if (!$this->selector_allowed($mode, $post_data))
        {
            return;
        }

        $forum_id = $this->forum_id($post_data);
        if (!$forum_id || !$this->tags_available($forum_id))
        {
            return;
        }

        $parsed = $this->normalizer->parse($this->request->variable('iorgian_tags', '', true));
        $locked = $this->locked_tags($post_data, $mode, $forum_id);
        // Locked tags stay on the topic whatever is typed; they still count for min / max.
        $names = array_diff_key($parsed['names'], $locked);
        $cfg = $this->repo->get_forum_config($forum_id);
        $error = $event['error'];

        if ($parsed['invalid'])
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_INVALID', $this->escape_list($parsed['invalid']));
        }
        if ($parsed['blacklisted'])
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_BLACKLISTED', $this->escape_list($parsed['blacklisted']));
        }

        // Tags reserved for other groups, or not on the allowed list (whitelist mode).
        $restricted = [];
        $not_official = [];
        foreach ($names as $slug => $name)
        {
            $row = $this->repo->find_by_slug($slug);
            $status = $row ? $this->repo->use_status($row, $forum_id) : null;
            if ($status === 'group')
            {
                $restricted[] = $name;
            }
            else if ($status === 'whitelist')
            {
                $not_official[] = $name;
            }
        }
        if ($restricted)
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_RESTRICTED', $this->escape_list($restricted));
        }
        if ($not_official)
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_NOT_OFFICIAL', $this->escape_list($not_official));
        }

        $count = count($names) + count($locked);
        if ($cfg['min'] > 0 && $count < $cfg['min'])
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_MIN', $cfg['min']);
        }
        if ($cfg['max'] > 0 && $count > $cfg['max'])
        {
            $error[] = $this->language->lang('IORGIAN_TAGS_ERR_MAX', $cfg['max']);
        }

        if (!$this->can_create($forum_id))
        {
            $missing = [];
            foreach ($names as $slug => $name)
            {
                if (!$this->repo->find_by_slug($slug))
                {
                    $missing[] = $name;
                }
            }
            if ($missing)
            {
                $error[] = $this->language->lang('IORGIAN_TAGS_ERR_NOT_EXIST', $this->escape_list($missing));
            }
        }

        // Optional hard rule: a tag may not share its name with a Multi Prefixes prefix.
        if ((int) $this->config['iorgian_tags_prefix_conflict'] === 2 && !$this->auth->acl_get('m_edit', $forum_id))
        {
            $clash = [];
            foreach ($names as $name)
            {
                if ($this->bridge->find_conflict($name) !== null)
                {
                    $clash[] = $name;
                }
            }
            if ($clash)
            {
                $error[] = $this->language->lang('IORGIAN_TAGS_ERR_PREFIX_CONFLICT', $this->escape_list($clash));
            }
        }

        $event['error'] = $error;
    }

    public function posting_save($event)
    {
        if (!$this->ready())
        {
            return;
        }

        $mode = (string) $event['mode'];
        if (!in_array($mode, ['post', 'edit'], true) || !$this->request->is_set_post('iorgian_tags_present'))
        {
            return;
        }

        $data = $event['data'];
        $forum_id = (int) ($data['forum_id'] ?? 0);
        $topic_id = (int) ($data['topic_id'] ?? 0);

        if (!$forum_id || !$topic_id || !$this->tags_available($forum_id))
        {
            return;
        }

        if ($mode === 'edit' && !$this->repo->is_first_post($topic_id, (int) ($data['post_id'] ?? 0)))
        {
            return;
        }

        $parsed = $this->normalizer->parse($this->request->variable('iorgian_tags', '', true));

        // Restricted tags already on the topic that this user may not change are always kept.
        $keep = [];
        if ($mode === 'edit')
        {
            foreach ($this->repo->get_topic_tags($topic_id) as $tag)
            {
                if (!$this->repo->can_use_tag($tag, $forum_id))
                {
                    $keep[] = $tag['tag_id'];
                }
            }
        }

        // Defence in depth: validation already refuses restricted names.
        $names = [];
        foreach ($parsed['names'] as $slug => $name)
        {
            $row = $this->repo->find_by_slug($slug);
            if (!$row || $this->repo->can_use_tag($row, $forum_id))
            {
                $names[] = $name;
            }
        }

        $this->repo->set_topic_tags($topic_id, $names, $this->can_create($forum_id), $keep, $this->repo->whitelist_on());
    }

    /* ---------------------------------------------------------------- */
    /* Display                                                          */
    /* ---------------------------------------------------------------- */

    public function forum_preload($event)
    {
        if (!$this->ready() || empty($this->config['iorgian_tags_show_viewforum']))
        {
            return;
        }

        $this->topic_tags = $this->repo->get_for_topics((array) $event['topic_list']);
        $this->preloaded = true;
    }

    public function forum_row($event)
    {
        if (!$this->ready() || empty($this->config['iorgian_tags_show_viewforum']))
        {
            return;
        }

        $topic_id = (int) ($event['row']['topic_id'] ?? 0);

        // Fallback if the preload event did not fire: one query per row.
        if (!$this->preloaded && !isset($this->topic_tags[$topic_id]))
        {
            $this->topic_tags += $this->repo->get_for_topics([$topic_id]);
        }

        if (!empty($this->topic_tags[$topic_id]))
        {
            $topic_row = $event['topic_row'];
            $topic_row['IORGIAN_TAGS_HTML'] = $this->renderer->render($this->topic_tags[$topic_id]);
            $event['topic_row'] = $topic_row;
        }
    }

    public function topic_view($event)
    {
        if (!$this->ready())
        {
            return;
        }

        $topic_id = (int) ($event['topic_data']['topic_id'] ?? 0);
        $tags = $this->repo->get_topic_tags($topic_id);
        if (!$tags)
        {
            return;
        }

        $names = [];
        foreach ($tags as $tag)
        {
            $names[] = $tag['tag_name'];
        }

        $this->topic_tags_html = $this->renderer->render($tags);
        $this->template->assign_var('IORGIAN_TAGS_META', htmlspecialchars(implode(', ', $names), ENT_COMPAT, 'UTF-8'));
    }

    /** Tags go in the footer of the first post of the topic (not the first row of every page). */
    public function post_row($event)
    {
        if ($this->topic_tags_html === '')
        {
            return;
        }

        if ((int) $event['row']['post_id'] === (int) ($event['topic_data']['topic_first_post_id'] ?? 0))
        {
            $post_row = $event['post_row'];
            $post_row['IORGIAN_TAGS_HTML'] = $this->topic_tags_html;
            $event['post_row'] = $post_row;
        }
    }

    /** UCP > Bookmarks and Subscribed topics. One row at a time: the event gives no list of the page. */
    public function ucp_topic_row($event)
    {
        $html = $this->tags_html_for((int) $event['topic_id']);
        if ($html !== '')
        {
            $template_vars = $event['template_vars'];
            $template_vars['IORGIAN_TAGS_HTML'] = $html;
            $event['template_vars'] = $template_vars;
        }
    }

    /** MCP > forum view (moderator topic list). Moved-topic shadows carry no tags. */
    public function mcp_topic_row($event)
    {
        $topic_row = $event['topic_row'];
        if (!empty($topic_row['S_MOVED_TOPIC']))
        {
            return;
        }

        $html = $this->tags_html_for((int) ($event['row']['topic_id'] ?? 0));
        if ($html !== '')
        {
            $topic_row['IORGIAN_TAGS_HTML'] = $html;
            $event['topic_row'] = $topic_row;
        }
    }

    /** Rendered tags of one topic for the lists, or '' (same switch as the forum lists). */
    protected function tags_html_for(int $topic_id): string
    {
        if ($topic_id <= 0 || !$this->ready() || empty($this->config['iorgian_tags_show_viewforum']))
        {
            return '';
        }

        $tags = $this->repo->get_for_topics([$topic_id])[$topic_id] ?? [];

        return $tags ? $this->renderer->render($tags) : '';
    }

    public function delete_topics($event)
    {
        if (!$this->repo->is_complete())
        {
            return;
        }

        $this->repo->discount_topics((array) $event['topic_ids']);

        $table_ary = $event['table_ary'];
        $table_ary[] = $this->repo->topics_table();
        $event['table_ary'] = $table_ary;
    }

    /* ---------------------------------------------------------------- */
    /* Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /** Tags are entered when creating a topic or editing its first post. */
    protected function selector_allowed(string $mode, $post_data): bool
    {
        if ($mode === 'post')
        {
            return true;
        }

        return $mode === 'edit'
            && isset($post_data['topic_first_post_id'], $post_data['post_id'])
            && (int) $post_data['topic_first_post_id'] === (int) $post_data['post_id'];
    }

    /**
     * Restricted tags of the topic being edited that the current user may not change.
     *
     * @return array<string,array> slug => tag row
     */
    protected function locked_tags($post_data, string $mode, int $forum_id): array
    {
        if ($mode !== 'edit' || empty($post_data['topic_id']))
        {
            return [];
        }

        $locked = [];
        foreach ($this->repo->get_topic_tags((int) $post_data['topic_id']) as $tag)
        {
            if (!$this->repo->can_use_tag($tag, $forum_id))
            {
                $locked[$tag['tag_slug']] = $tag;
            }
        }

        return $locked;
    }

    protected function forum_id($post_data): int
    {
        return (int) ($post_data['forum_id'] ?? $this->request->variable('f', 0));
    }

    protected function tags_available(int $forum_id): bool
    {
        $cfg = $this->repo->get_forum_config($forum_id);

        return $cfg['enabled'] && $this->acl->can_in_forum('f_iorgian_tags_use', $forum_id);
    }

    protected function can_create(int $forum_id): bool
    {
        // In whitelist mode only moderators can introduce new tags.
        return (!empty($this->config['iorgian_tags_users_create']) && !$this->repo->whitelist_on()) || $this->auth->acl_get('m_edit', $forum_id);
    }

    protected function help_text(int $forum_id): string
    {
        $cfg = $this->repo->get_forum_config($forum_id);
        $parts = [];

        if ($cfg['min'] > 0 && $cfg['max'] > 0)
        {
            $parts[] = $this->language->lang('IORGIAN_TAGS_HELP_MIN_MAX', $cfg['min'], $cfg['max']);
        }
        else if ($cfg['min'] > 0)
        {
            $parts[] = $this->language->lang('IORGIAN_TAGS_HELP_MIN', $cfg['min']);
        }
        else if ($cfg['max'] > 0)
        {
            $parts[] = $this->language->lang('IORGIAN_TAGS_HELP_MAX', $cfg['max']);
        }

        if ($this->repo->whitelist_on() && !$this->auth->acl_get('m_edit', $forum_id))
        {
            $parts[] = $this->language->lang('IORGIAN_TAGS_HELP_WHITELIST');
        }
        else if (!$this->can_create($forum_id))
        {
            $parts[] = $this->language->lang('IORGIAN_TAGS_HELP_NO_CREATE');
        }

        return $this->language->lang('IORGIAN_TAGS_HELP') . ($parts ? ' ' . implode(' ', $parts) : '');
    }

    /** @param string[] $names RAW names */
    protected function escape_list(array $names): string
    {
        return implode(', ', array_map(function ($n)
        {
            return htmlspecialchars($n, ENT_COMPAT, 'UTF-8');
        }, $names));
    }
}
