<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * The tag filter of a phpBB search request (search.php?tags=a,b&tagmode=OR).
 * Parsed once per request; used by the search listener and by the backend hooks.
 */
class search_filter
{
    protected const MAX_TAGS = 10;

    /** @var repository */
    protected $repo;
    /** @var normalizer */
    protected $normalizer;
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var array|false|null */
    protected $parsed = null;

    public function __construct(
        repository $repo,
        normalizer $normalizer,
        \phpbb\config\config $config,
        \phpbb\request\request $request,
        \phpbb\auth\auth $auth
    ) {
        $this->repo = $repo;
        $this->normalizer = $normalizer;
        $this->config = $config;
        $this->request = $request;
        $this->auth = $auth;
    }

    public function enabled(): bool
    {
        return !empty($this->config['iorgian_tags_search_enabled']) && $this->repo->is_complete();
    }

    /** Permission "Can search by tags". */
    public function allowed(): bool
    {
        return (bool) $this->auth->acl_get('u_iorgian_tags_search');
    }

    /**
     * The filter requested in the URL / form, or null when there is none.
     *
     * @return array|null ['slugs' => string[], 'names' => string[], 'mode' => 'AND'|'OR']
     */
    public function requested(): ?array
    {
        if ($this->parsed === null)
        {
            $this->parsed = false;

            $raw = $this->request->variable('tags', '', true); // HTML-escaped by phpBB
            if ($raw !== '' && $this->enabled())
            {
                $found = [];
                foreach ((array) preg_split('/[,;\n\r]+/u', htmlspecialchars_decode($raw, ENT_COMPAT)) as $part)
                {
                    $name = $this->normalizer->clean((string) $part);
                    $slug = $this->normalizer->slug($name);
                    if ($slug !== '' && !isset($found[$slug]) && count($found) < self::MAX_TAGS)
                    {
                        $found[$slug] = $name;
                    }
                }

                if ($found)
                {
                    $mode = strtoupper($this->request->variable('tagmode', 'AND')) === 'OR' ? 'OR' : 'AND';
                    $this->parsed = ['slugs' => array_keys($found), 'names' => array_values($found), 'mode' => $mode];
                }
            }
        }

        return $this->parsed ?: null;
    }

    public function enabled_only(): bool
    {
        return !empty($this->config['iorgian_tags_search_enabled_only']);
    }

    /** SQL condition limiting a column that holds topic ids (e.g. "p.topic_id") to the filtered topics. */
    public function condition(string $column): string
    {
        $filter = $this->requested();
        if (!$filter)
        {
            return '1 = 1';
        }

        return $column . ' IN (' . $this->repo->tag_topics_subquery($filter['slugs'], $filter['mode'], $this->enabled_only()) . ')';
    }

    /**
     * Topic ids of the filter, for Sphinx (an attribute filter, not SQL).
     *
     * @return int[] at most $limit + 1 ids
     */
    public function topic_ids(int $limit): array
    {
        $filter = $this->requested();

        return $filter ? $this->repo->tag_topic_ids($filter['slugs'], $filter['mode'], $this->enabled_only(), $limit) : [];
    }

    /** Added to the cached-results key so a search with tags never reuses one without them. */
    public function cache_key(): string
    {
        $filter = $this->requested();

        return $filter
            ? 'iorgian_tags:' . $filter['mode'] . ':' . implode(',', $filter['slugs']) . ':' . (int) $this->enabled_only()
            : '';
    }

    /**
     * Tags combined with keywords or an author are injected into the query of the native, MySQL and
     * PostgreSQL fulltext backends, and into the topic_id filter of Sphinx (all of them expose events
     * for that). Other backends do not.
     */
    public function backend_supported(string $search_type): bool
    {
        return in_array(ltrim($search_type, '\\'), [
            'phpbb\search\fulltext_native',
            'phpbb\search\fulltext_mysql',
            'phpbb\search\fulltext_postgres',
            'phpbb\search\fulltext_sphinx',
        ], true);
    }
}
