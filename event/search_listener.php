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
 * Tags in the phpBB search (search.php), for the native, MySQL, PostgreSQL and Sphinx backends.
 *
 *  - tags only: own query on topics (no keywords, no author),
 *  - tags + keywords and/or author: the filter is injected in the backend query through the events
 *    the backends already expose, and added to the cache key,
 *  - the form field, the URL parameters, the results title and the tags shown in the results.
 */
class search_listener implements EventSubscriberInterface
{
    /** Sphinx accepts 4096 values per attribute filter by default (max_filter_values). */
    protected const SPHINX_MAX_TOPICS = 4000;

    /** @var \iorgian\tags\service\search_filter */
    protected $filter;
    /** @var \iorgian\tags\service\repository */
    protected $repo;
    /** @var \iorgian\tags\service\renderer */
    protected $renderer;
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

    /** @var string 'none' | 'tag_only' | 'filter' (set once the search starts) */
    protected $state = 'none';
    /** @var array<int,array[]> */
    protected $topic_tags = [];

    public function __construct(
        \iorgian\tags\service\search_filter $filter,
        \iorgian\tags\service\repository $repo,
        \iorgian\tags\service\renderer $renderer,
        \phpbb\config\config $config,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language,
        \phpbb\controller\helper $helper
    ) {
        $this->filter = $filter;
        $this->repo = $repo;
        $this->renderer = $renderer;
        $this->config = $config;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
        $this->helper = $helper;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.search_modify_submit_parameters'           => 'submit',
            'core.search_modify_param_before'                => 'param_before',
            'core.search_backend_search_after'               => 'tag_only_search',
            'core.search_modify_url_parameters'              => 'url_parameters',
            'core.search_modify_rowset'                      => 'preload_tags',
            'core.search_modify_tpl_ary'                     => 'result_tags',
            'core.search_modify_forum_select_list'           => 'form_fields',

            // Backend hooks: add the tag condition to the queries and to the cache keys.
            'core.search_native_keywords_count_query_before' => 'native_keywords_where',
            'core.search_mysql_keywords_main_query_before'   => 'match_where',
            'core.search_postgres_keywords_main_query_before' => 'match_where',
            'core.search_postgres_author_count_query_before' => 'author_where',
            'core.search_postgres_by_keyword_modify_search_key' => 'cache_key',
            'core.search_postgres_by_author_modify_search_key' => 'cache_key',
            'core.search_sphinx_keywords_modify_options'     => 'sphinx_filter',
            'core.search_native_author_count_query_before'   => 'author_where',
            'core.search_mysql_author_query_before'          => 'author_where',
            'core.search_native_by_keyword_modify_search_key' => 'cache_key',
            'core.search_mysql_by_keyword_modify_search_key' => 'cache_key',
            'core.search_native_by_author_modify_search_key' => 'cache_key',
            'core.search_mysql_by_author_modify_search_key'  => 'cache_key',
        ];
    }

    /** A search with tags is a search even without keywords (a link such as search.php?tags=linux). */
    public function submit($event)
    {
        if (!$this->filter->requested())
        {
            return;
        }

        if (!$this->filter->allowed())
        {
            trigger_error('IORGIAN_TAGS_SEARCH_NOT_ALLOWED');
        }

        $event['submit'] = true;
    }

    public function param_before($event)
    {
        $this->state = 'none';

        // Pre-made searches (new posts, unread...) and "search in this topic" do not take tag filters.
        if (!$this->filter->requested() || !$this->filter->allowed() || $event['search_id'] || $this->request->variable('t', 0))
        {
            return;
        }

        $has_keywords = trim((string) $event['keywords']) !== '';
        $has_author = count((array) $event['author_id_ary']) > 0;

        if (!$has_keywords && !$has_author)
        {
            // Tags only: the result is a list of topics.
            $this->state = 'tag_only';
            $event['show_results'] = 'topics';

            // search.php prepared the sort keys for posts: switch them to the topic columns.
            $sort_by_sql = $event['sort_by_sql'];
            $sort_by_sql['t'] = 't.topic_last_post_time';
            $sort_by_sql['s'] = 't.topic_title';
            $event['sort_by_sql'] = $sort_by_sql;

            return;
        }

        if (!$this->filter->backend_supported((string) $this->config['search_type']))
        {
            trigger_error('IORGIAN_TAGS_SEARCH_BACKEND');
        }

        $this->state = 'filter';
    }

    /** Tags only: our own query. phpBB then loads and displays the topics as for any other search. */
    public function tag_only_search($event)
    {
        if ($this->state !== 'tag_only')
        {
            return;
        }

        $filter = $this->filter->requested();
        [$total, $ids, $start] = $this->repo->search_topic_ids(
            $filter['slugs'],
            $filter['mode'],
            (array) $event['ex_fid_ary'],
            (string) $event['sort_key'],
            (string) $event['sort_dir'],
            (int) $event['sort_days'],
            (int) $event['start'],
            (int) $event['per_page'],
            $this->filter->enabled_only()
        );

        $event['id_ary'] = $ids;
        $event['total_match_count'] = $total;
        $event['start'] = $start;
    }

    public function url_parameters($event)
    {
        if ($this->state === 'none' || !($filter = $this->filter->requested()))
        {
            return;
        }

        // Keeps the filter in pagination, "search in results" and the sort form.
        $u_search = $event['u_search'];
        $u_search .= '&amp;tags=' . urlencode(implode(',', $filter['names']));
        $u_search .= ($filter['mode'] === 'OR') ? '&amp;tagmode=OR' : '';
        $event['u_search'] = $u_search;

        $this->template->assign_var('IORGIAN_TAGS_SEARCH_INFO', htmlspecialchars(implode(', ', $filter['names']), ENT_COMPAT, 'UTF-8')
            . ' (' . $this->language->lang($filter['mode'] === 'OR' ? 'IORGIAN_TAGS_MODE_OR' : 'IORGIAN_TAGS_MODE_AND') . ')');
    }

    /* ---------------------------------------------------------------- */
    /* Backend hooks (native and MySQL fulltext)                        */
    /* ---------------------------------------------------------------- */

    public function native_keywords_where($event)
    {
        if ($this->state !== 'filter')
        {
            return;
        }

        $sql_where = $event['sql_where'];
        $sql_where[] = $this->filter->condition('p.topic_id');
        $event['sql_where'] = $sql_where;
    }

    /** MySQL and PostgreSQL: sql_match_where is appended to the WHERE of the keyword query. */
    public function match_where($event)
    {
        if ($this->state !== 'filter')
        {
            return;
        }

        $event['sql_match_where'] = $event['sql_match_where'] . ' AND ' . $this->filter->condition('p.topic_id');
    }

    /** Sphinx: the tag filter becomes a topic_id attribute filter (author searches go through keyword_search too). */
    public function sphinx_filter($event)
    {
        if ($this->state !== 'filter')
        {
            return;
        }

        $topic_ids = $this->filter->topic_ids(self::SPHINX_MAX_TOPICS);
        if (count($topic_ids) > self::SPHINX_MAX_TOPICS)
        {
            trigger_error('IORGIAN_TAGS_SEARCH_TOO_MANY');
        }

        // An empty list must match nothing.
        $event['sphinx']->SetFilter('topic_id', $topic_ids ?: [0]);
    }

    /** Author searches (native, MySQL, PostgreSQL): sql_fora is appended to the WHERE of every query. */
    public function author_where($event)
    {
        if ($this->state !== 'filter')
        {
            return;
        }

        $event['sql_fora'] = $event['sql_fora'] . ' AND ' . $this->filter->condition('p.topic_id');
    }

    public function cache_key($event)
    {
        if ($this->state !== 'filter')
        {
            return;
        }

        $key = $event['search_key_array'];
        $key[] = $this->filter->cache_key();
        $event['search_key_array'] = $key;
    }

    /* ---------------------------------------------------------------- */
    /* Form and results                                                 */
    /* ---------------------------------------------------------------- */

    /** Fires only when the search form is displayed. */
    public function form_fields($event)
    {
        if (!$this->filter->enabled() || !$this->filter->allowed())
        {
            return;
        }

        $filter = $this->filter->requested();

        $this->template->assign_vars([
            'S_IORGIAN_TAGS_SEARCH'        => true,
            'IORGIAN_TAGS_SEARCH_VALUE'    => $filter ? htmlspecialchars(implode(', ', $filter['names']), ENT_COMPAT, 'UTF-8') : '',
            'S_IORGIAN_TAGS_SEARCH_OR'     => $filter && $filter['mode'] === 'OR',
            'U_IORGIAN_TAGS_SUGGEST'       => $this->helper->route('iorgian_tags_suggest'),
        ]);
    }

    public function preload_tags($event)
    {
        if (!$this->renderer->available() || empty($this->config['iorgian_tags_show_viewforum']))
        {
            return;
        }

        $topic_ids = [];
        foreach ((array) $event['rowset'] as $row)
        {
            if (!empty($row['topic_id']))
            {
                $topic_ids[(int) $row['topic_id']] = true;
            }
        }

        $this->topic_tags = $this->repo->get_for_topics(array_keys($topic_ids));
    }

    public function result_tags($event)
    {
        $topic_id = (int) ($event['row']['topic_id'] ?? 0);
        if (empty($this->topic_tags[$topic_id]))
        {
            return;
        }

        $tpl_ary = $event['tpl_ary'];
        $tpl_ary['IORGIAN_TAGS_HTML'] = $this->renderer->render($this->topic_tags[$topic_id]);
        $event['tpl_ary'] = $tpl_ary;
    }
}
