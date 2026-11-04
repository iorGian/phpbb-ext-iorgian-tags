<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * All database access of iorgian/tags.
 */
class repository
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\auth\auth */
    protected $auth;
    /** @var \phpbb\content_visibility */
    protected $visibility;
    /** @var \phpbb\config\config */
    protected $config;
    /** @var normalizer */
    protected $normalizer;
    /** @var \iorgian\core\permission\acl_helper|null */
    protected $acl;
    /** @var string */
    protected $t_tags;
    protected $t_topics;
    protected $t_cfg;
    protected $t_groups;
    /** @var array<int,array>|null */
    protected $cfg_cache = null;
    /** @var array<int,int[]>|null tag_id => allowed group ids */
    protected $group_cache = null;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\auth\auth $auth,
        \phpbb\content_visibility $visibility,
        \phpbb\config\config $config,
        normalizer $normalizer,
        $acl,
        string $t_tags,
        string $t_topics,
        string $t_cfg,
        string $t_groups
    ) {
        $this->db = $db;
        $this->auth = $auth;
        $this->visibility = $visibility;
        $this->config = $config;
        $this->normalizer = $normalizer;
        $this->acl = $acl;
        $this->t_tags = $t_tags;
        $this->t_topics = $t_topics;
        $this->t_cfg = $t_cfg;
        $this->t_groups = $t_groups;
    }

    public function topics_table(): string
    {
        return $this->t_topics;
    }

    /** True when the three tables exist (guards against a half-installed state). */
    public function is_complete(): bool
    {
        return $this->t_tags !== '' && $this->t_topics !== '' && $this->t_cfg !== '' && $this->t_groups !== '';
    }

    protected function cast(array $row): array
    {
        $row['tag_id'] = (int) $row['tag_id'];
        $row['tag_count'] = (int) ($row['tag_count'] ?? 0);
        $row['tag_official'] = (int) ($row['tag_official'] ?? 0);

        return $row;
    }

    /* ---------------------------------------------------------------- */
    /* Tags                                                             */
    /* ---------------------------------------------------------------- */

    public function find_by_slug(string $slug): ?array
    {
        $sql = 'SELECT * FROM ' . $this->t_tags . " WHERE tag_slug = '" . $this->db->sql_escape($slug) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ? $this->cast($row) : null;
    }

    public function get_tag(int $tag_id): ?array
    {
        $sql = 'SELECT * FROM ' . $this->t_tags . ' WHERE tag_id = ' . (int) $tag_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ? $this->cast($row) : null;
    }

    /** @param string $name RAW tag name (already validated) */
    public function get_or_create(string $name, string $color = '', bool $official = false): array
    {
        $slug = $this->normalizer->slug($name);
        $row = $this->find_by_slug($slug);
        if ($row)
        {
            return $row;
        }

        $this->db->sql_query('INSERT INTO ' . $this->t_tags . ' ' . $this->db->sql_build_array('INSERT', [
            'tag_name'  => $name,
            'tag_slug'  => $slug,
            'tag_color' => $color,
            'tag_count' => 0,
            'tag_official' => $official ? 1 : 0,
        ]));

        return $this->get_tag((int) $this->db->sql_nextid());
    }

    /**
     * @param string $search search text (matched against the slug); '' = all
     * @param string $sort   'name' or 'count'
     * @return array[]
     */
    public function list_tags(string $search, int $start, int $limit, string $sort = 'name', bool $only_unofficial = false): array
    {
        $order = ($sort === 'count') ? 'tag_count DESC, tag_slug ASC' : 'tag_slug ASC';
        $sql = 'SELECT * FROM ' . $this->t_tags . $this->search_where($search, $only_unofficial) . ' ORDER BY ' . $order;
        $result = $this->db->sql_query_limit($sql, $limit, $start);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $this->cast($row);
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function count_tags(string $search = '', bool $only_unofficial = false): int
    {
        $sql = 'SELECT COUNT(tag_id) AS total FROM ' . $this->t_tags . $this->search_where($search, $only_unofficial);
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        return $total;
    }

    protected function search_where(string $search, bool $only_unofficial = false): string
    {
        $conditions = [];

        $slug = $this->normalizer->slug($search);
        if ($slug !== '')
        {
            $conditions[] = 'tag_slug ' . $this->db->sql_like_expression($this->db->get_any_char() . $slug . $this->db->get_any_char());
        }
        if ($only_unofficial)
        {
            $conditions[] = 'tag_official = 0';
        }

        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    /* ---------------------------------------------------------------- */
    /* Whitelist mode                                                   */
    /* ---------------------------------------------------------------- */

    public function whitelist_on(): bool
    {
        return !empty($this->config['iorgian_tags_whitelist']);
    }

    public function set_official(int $tag_id, bool $official): void
    {
        $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_official = ' . ($official ? 1 : 0) . ' WHERE tag_id = ' . (int) $tag_id);
    }

    /** @return int number of tags that were not official */
    public function mark_all_official(): int
    {
        $result = $this->db->sql_query('SELECT COUNT(tag_id) AS n FROM ' . $this->t_tags . ' WHERE tag_official = 0');
        $n = (int) $this->db->sql_fetchfield('n');
        $this->db->sql_freeresult($result);

        $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_official = 1 WHERE tag_official = 0');

        return $n;
    }

    /** @return string[] RAW tag names matching what the user is typing (restricted tags the user cannot use are left out) */
    public function suggest(string $typed, array $exclude_slugs, int $limit = 10, int $forum_id = 0): array
    {
        $slug = $this->normalizer->slug($typed);
        if ($slug === '')
        {
            return [];
        }

        $where = 'tag_slug ' . $this->db->sql_like_expression($this->db->get_any_char() . $slug . $this->db->get_any_char());
        if ($exclude_slugs)
        {
            $where .= ' AND ' . $this->db->sql_in_set('tag_slug', $exclude_slugs, true);
        }

        $sql = 'SELECT tag_id, tag_name, tag_official FROM ' . $this->t_tags . ' WHERE ' . $where . ' ORDER BY tag_count DESC, tag_slug ASC';
        $result = $this->db->sql_query_limit($sql, $limit * 4);
        $names = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            if (count($names) < $limit && $this->can_use_tag($this->cast($row), $forum_id))
            {
                $names[] = $row['tag_name'];
            }
        }
        $this->db->sql_freeresult($result);

        return $names;
    }

    /** @return string 'ok' or 'exists' (another tag already has that name) */
    public function update_tag(int $tag_id, string $name, string $color): string
    {
        $slug = $this->normalizer->slug($name);
        $other = $this->find_by_slug($slug);
        if ($other && $other['tag_id'] !== $tag_id)
        {
            return 'exists';
        }

        $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET ' . $this->db->sql_build_array('UPDATE', [
            'tag_name'  => $name,
            'tag_slug'  => $slug,
            'tag_color' => $color,
        ]) . ' WHERE tag_id = ' . (int) $tag_id);

        return 'ok';
    }

    public function delete_tag(int $tag_id): void
    {
        $this->db->sql_query('DELETE FROM ' . $this->t_topics . ' WHERE tag_id = ' . (int) $tag_id);
        $this->db->sql_query('DELETE FROM ' . $this->t_groups . ' WHERE tag_id = ' . (int) $tag_id);
        $this->db->sql_query('DELETE FROM ' . $this->t_tags . ' WHERE tag_id = ' . (int) $tag_id);
        $this->group_cache = null;
    }

    /* ---------------------------------------------------------------- */
    /* Group restrictions                                               */
    /* ---------------------------------------------------------------- */

    /** @return array<int,int[]> tag_id => allowed group ids (tags without restriction have no entry) */
    public function get_groups_map(): array
    {
        if ($this->group_cache === null)
        {
            $this->group_cache = [];
            $result = $this->db->sql_query('SELECT tag_id, group_id FROM ' . $this->t_groups);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $this->group_cache[(int) $row['tag_id']][] = (int) $row['group_id'];
            }
            $this->db->sql_freeresult($result);
        }

        return $this->group_cache;
    }

    /** @return int[] empty = everyone */
    public function get_tag_groups(int $tag_id): array
    {
        return $this->get_groups_map()[$tag_id] ?? [];
    }

    /** @param int[] $group_ids empty = everyone */
    public function set_tag_groups(int $tag_id, array $group_ids): void
    {
        $this->db->sql_query('DELETE FROM ' . $this->t_groups . ' WHERE tag_id = ' . (int) $tag_id);

        $rows = [];
        foreach (array_unique(array_filter(array_map('intval', $group_ids))) as $group_id)
        {
            $rows[] = ['tag_id' => $tag_id, 'group_id' => $group_id];
        }
        if ($rows)
        {
            $this->db->sql_multi_insert($this->t_groups, $rows);
        }

        $this->group_cache = null;
    }

    /**
     * May the current user add this tag to a topic?
     * Unrestricted tags: yes. Restricted tags: members of an allowed group, and moderators
     * with edit permission in the forum (same rule as Multi Prefixes).
     */
    public function can_use_tag(array $tag, int $forum_id = 0): bool
    {
        return $this->use_status($tag, $forum_id) === null;
    }

    /**
     * Why the current user may not add this tag to a topic.
     *
     * @return string|null null = allowed, 'whitelist' = not on the allowed list (whitelist mode),
     *                     'group' = reserved for other user groups
     */
    public function use_status(array $tag, int $forum_id = 0): ?string
    {
        $moderator = $forum_id > 0 && $this->auth->acl_get('m_edit', $forum_id);

        if ($this->whitelist_on() && empty($tag['tag_official']) && !$moderator)
        {
            return 'whitelist';
        }

        $groups = $this->get_tag_groups((int) $tag['tag_id']);
        if (!$groups || $moderator)
        {
            return null;
        }

        return ($this->acl !== null && $this->acl->in_any_group($groups)) ? null : 'group';
    }

    /** Moves every assignment of $from to $to and deletes $from. */
    public function merge(int $from, int $to): void
    {
        if ($from === $to)
        {
            return;
        }

        $have = [];
        $result = $this->db->sql_query('SELECT topic_id FROM ' . $this->t_topics . ' WHERE tag_id = ' . (int) $to);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $have[(int) $row['topic_id']] = true;
        }
        $this->db->sql_freeresult($result);

        $rows = [];
        $result = $this->db->sql_query('SELECT topic_id FROM ' . $this->t_topics . ' WHERE tag_id = ' . (int) $from);
        while ($row = $this->db->sql_fetchrow($result))
        {
            if (!isset($have[(int) $row['topic_id']]))
            {
                $rows[] = ['topic_id' => (int) $row['topic_id'], 'tag_id' => $to];
            }
        }
        $this->db->sql_freeresult($result);

        if ($rows)
        {
            $this->db->sql_multi_insert($this->t_topics, $rows);
        }

        $this->delete_tag($from);
        $this->recount_tag($to);
    }

    /* ---------------------------------------------------------------- */
    /* Topic <-> tags                                                   */
    /* ---------------------------------------------------------------- */

    /** @return array[] tag rows of one topic, by name */
    public function get_topic_tags(int $topic_id): array
    {
        return $this->get_for_topics([$topic_id])[$topic_id] ?? [];
    }

    /**
     * @param int[] $topic_ids
     * @return array<int,array[]> topic_id => tag rows
     */
    public function get_for_topics(array $topic_ids): array
    {
        $topic_ids = array_values(array_filter(array_map('intval', $topic_ids)));
        if (!$topic_ids)
        {
            return [];
        }

        $sql = 'SELECT tt.topic_id, t.*
            FROM ' . $this->t_topics . ' tt
            INNER JOIN ' . $this->t_tags . ' t ON t.tag_id = tt.tag_id
            WHERE ' . $this->db->sql_in_set('tt.topic_id', $topic_ids) . '
            ORDER BY t.tag_slug ASC';
        $result = $this->db->sql_query($sql);
        $out = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $topic_id = (int) $row['topic_id'];
            unset($row['topic_id']);
            $out[$topic_id][] = $this->cast($row);
        }
        $this->db->sql_freeresult($result);

        return $out;
    }

    /**
     * Replaces the tags of a topic.
     *
     * @param string[] $names        RAW, validated names
     * @param bool     $allow_create false = names that do not exist yet are ignored
     * @param int[]    $keep_tag_ids tags that must stay on the topic whatever $names says
     *                               (restricted tags the current user may not change)
     * @param bool     $new_official new tags are created as official (whitelist mode)
     */
    public function set_topic_tags(int $topic_id, array $names, bool $allow_create, array $keep_tag_ids = [], bool $new_official = false): void
    {
        $wanted = [];
        foreach ($names as $name)
        {
            $row = $this->find_by_slug($this->normalizer->slug($name));
            if (!$row && $allow_create)
            {
                $row = $this->get_or_create($name, '', $new_official);
            }
            if ($row)
            {
                $wanted[$row['tag_id']] = true;
            }
        }

        foreach ($keep_tag_ids as $tag_id)
        {
            $wanted[(int) $tag_id] = true;
        }

        $current = [];
        foreach ($this->get_topic_tags($topic_id) as $row)
        {
            $current[] = $row['tag_id'];
        }

        foreach (array_diff(array_keys($wanted), $current) as $tag_id)
        {
            $this->db->sql_query('INSERT INTO ' . $this->t_topics . ' ' . $this->db->sql_build_array('INSERT', [
                'topic_id' => $topic_id,
                'tag_id'   => (int) $tag_id,
            ]));
            $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_count = tag_count + 1 WHERE tag_id = ' . (int) $tag_id);
        }

        foreach (array_diff($current, array_keys($wanted)) as $tag_id)
        {
            $this->db->sql_query('DELETE FROM ' . $this->t_topics . ' WHERE topic_id = ' . $topic_id . ' AND tag_id = ' . (int) $tag_id);
            $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_count = tag_count - 1 WHERE tag_id = ' . (int) $tag_id . ' AND tag_count > 0');
        }
    }

    /** Adjusts the counters for topics that are about to be deleted (rows go with the delete event). */
    public function discount_topics(array $topic_ids): void
    {
        $topic_ids = array_values(array_filter(array_map('intval', $topic_ids)));
        if (!$topic_ids)
        {
            return;
        }

        $sql = 'SELECT tag_id, COUNT(topic_id) AS n FROM ' . $this->t_topics . '
            WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids) . '
            GROUP BY tag_id';
        $result = $this->db->sql_query($sql);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = [(int) $row['tag_id'], (int) $row['n']];
        }
        $this->db->sql_freeresult($result);

        foreach ($rows as [$tag_id, $n])
        {
            $this->db->sql_query('UPDATE ' . $this->t_tags . '
                SET tag_count = CASE WHEN tag_count > ' . $n . ' THEN tag_count - ' . $n . ' ELSE 0 END
                WHERE tag_id = ' . $tag_id);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Maintenance                                                      */
    /* ---------------------------------------------------------------- */

    public function recount_tag(int $tag_id): void
    {
        $result = $this->db->sql_query('SELECT COUNT(topic_id) AS n FROM ' . $this->t_topics . ' WHERE tag_id = ' . (int) $tag_id);
        $n = (int) $this->db->sql_fetchfield('n');
        $this->db->sql_freeresult($result);

        $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_count = ' . $n . ' WHERE tag_id = ' . (int) $tag_id);
    }

    /** Removes orphan assignments and recalculates every counter. */
    public function recount_all(): void
    {
        $this->db->sql_query('DELETE FROM ' . $this->t_topics . ' WHERE topic_id NOT IN (SELECT topic_id FROM ' . TOPICS_TABLE . ')');
        $this->db->sql_query('DELETE FROM ' . $this->t_topics . ' WHERE tag_id NOT IN (SELECT tag_id FROM ' . $this->t_tags . ')');
        $this->db->sql_query('DELETE FROM ' . $this->t_groups . ' WHERE tag_id NOT IN (SELECT tag_id FROM ' . $this->t_tags . ')');
        $this->group_cache = null;
        $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_count = 0');

        $result = $this->db->sql_query('SELECT tag_id, COUNT(topic_id) AS n FROM ' . $this->t_topics . ' GROUP BY tag_id');
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = [(int) $row['tag_id'], (int) $row['n']];
        }
        $this->db->sql_freeresult($result);

        foreach ($rows as [$tag_id, $n])
        {
            $this->db->sql_query('UPDATE ' . $this->t_tags . ' SET tag_count = ' . $n . ' WHERE tag_id = ' . $tag_id);
        }
    }

    /** @return int number of tags deleted */
    public function delete_unused(): int
    {
        $ids = [];
        $result = $this->db->sql_query('SELECT tag_id FROM ' . $this->t_tags . ' WHERE tag_count = 0');
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['tag_id'];
        }
        $this->db->sql_freeresult($result);

        if ($ids)
        {
            $this->db->sql_query('DELETE FROM ' . $this->t_groups . ' WHERE ' . $this->db->sql_in_set('tag_id', $ids));
            $this->db->sql_query('DELETE FROM ' . $this->t_tags . ' WHERE ' . $this->db->sql_in_set('tag_id', $ids));
            $this->group_cache = null;
        }

        return count($ids);
    }

    /* ---------------------------------------------------------------- */
    /* Per-forum configuration                                          */
    /* ---------------------------------------------------------------- */

    protected function load_configs(): array
    {
        if ($this->cfg_cache === null)
        {
            $this->cfg_cache = [];
            $result = $this->db->sql_query('SELECT * FROM ' . $this->t_cfg);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $this->cfg_cache[(int) $row['forum_id']] = [
                    'enabled' => (bool) $row['tags_enabled'],
                    'min'     => (int) $row['min_tags'],
                    'max'     => (int) $row['max_tags'],
                ];
            }
            $this->db->sql_freeresult($result);
        }

        return $this->cfg_cache;
    }

    /** @return array ['enabled' => bool, 'min' => int, 'max' => int] */
    public function get_forum_config(int $forum_id): array
    {
        $all = $this->load_configs();

        return $all[$forum_id] ?? [
            'enabled' => !empty($this->config['iorgian_tags_default_enabled']),
            'min'     => 0,
            'max'     => 0,
        ];
    }

    public function set_forum_config(int $forum_id, bool $enabled, int $min, int $max): void
    {
        $this->db->sql_query('DELETE FROM ' . $this->t_cfg . ' WHERE forum_id = ' . (int) $forum_id);
        $this->db->sql_query('INSERT INTO ' . $this->t_cfg . ' ' . $this->db->sql_build_array('INSERT', [
            'forum_id'     => $forum_id,
            'tags_enabled' => $enabled ? 1 : 0,
            'min_tags'     => $min,
            'max_tags'     => $max,
        ]));
        $this->cfg_cache = null;
    }

    /** True if $post_id is the first post of the topic (tags are edited there only). */
    public function is_first_post(int $topic_id, int $post_id): bool
    {
        $result = $this->db->sql_query('SELECT topic_first_post_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id);
        $first = (int) $this->db->sql_fetchfield('topic_first_post_id');
        $this->db->sql_freeresult($result);

        return $first > 0 && $first === (int) $post_id;
    }

    /* ---------------------------------------------------------------- */
    /* Public listings (respect forum permissions)                      */
    /* ---------------------------------------------------------------- */

    /** SQL fragment: topics the current user may see. */
    protected function visible_sql(string $alias): string
    {
        $readable = array_keys($this->auth->acl_getf('f_read', true));
        if (!$readable)
        {
            return '1 = 0';
        }

        return $this->db->sql_in_set($alias . '.forum_id', $readable)
            . ' AND ' . $alias . '.topic_status <> ' . ITEM_MOVED
            . ' AND ' . $this->visibility->get_global_visibility_sql('topic', [], $alias . '.');
    }

    /**
     * Most used tags among topics the user can see.
     *
     * @return array[] tag rows + 'uses' + 'level' (1 to 5), sorted by name
     */
    public function cloud(int $limit): array
    {
        $sql = 'SELECT t.tag_id, t.tag_name, t.tag_slug, t.tag_color, COUNT(tt.topic_id) AS uses
            FROM ' . $this->t_tags . ' t
            INNER JOIN ' . $this->t_topics . ' tt ON tt.tag_id = t.tag_id
            INNER JOIN ' . TOPICS_TABLE . ' tp ON tp.topic_id = tt.topic_id
            WHERE ' . $this->visible_sql('tp') . '
            GROUP BY t.tag_id, t.tag_name, t.tag_slug, t.tag_color
            ORDER BY COUNT(tt.topic_id) DESC, t.tag_slug ASC';
        $result = $this->db->sql_query_limit($sql, max(1, $limit));
        $rows = [];
        $max = 1;
        while ($row = $this->db->sql_fetchrow($result))
        {
            $row['tag_id'] = (int) $row['tag_id'];
            $row['uses'] = (int) $row['uses'];
            $max = max($max, $row['uses']);
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        foreach ($rows as &$row)
        {
            $row['level'] = 1 + (int) floor(($row['uses'] / $max) * 4.999);
        }
        unset($row);

        usort($rows, function ($a, $b)
        {
            return strcmp($a['tag_slug'], $b['tag_slug']);
        });

        return $rows;
    }

    /**
     * SQL (a SELECT of topic ids) for the topics carrying all ('AND') or any ('OR') of the tags.
     * Used by the tag pages and by the phpBB search integration.
     *
     * @param string[] $slugs        normalised tag slugs (never raw user input)
     * @param bool     $enabled_only only topics of forums where tags are enabled
     */
    public function tag_topics_subquery(array $slugs, string $mode, bool $enabled_only = false): string
    {
        $slugs = array_values(array_unique($slugs));

        $sub = 'SELECT tt.topic_id FROM ' . $this->t_topics . ' tt
            INNER JOIN ' . $this->t_tags . ' t ON t.tag_id = tt.tag_id
            WHERE ' . $this->db->sql_in_set('t.tag_slug', $slugs);

        if ($enabled_only)
        {
            $forums = $this->enabled_forum_ids();
            $sub .= ' AND ' . ($forums
                ? 'tt.topic_id IN (SELECT tx.topic_id FROM ' . TOPICS_TABLE . ' tx WHERE ' . $this->db->sql_in_set('tx.forum_id', $forums) . ')'
                : '1 = 0');
        }

        if ($mode === 'AND')
        {
            $sub .= ' GROUP BY tt.topic_id HAVING COUNT(DISTINCT tt.tag_id) = ' . count($slugs);
        }

        return $sub;
    }

    /**
     * Topic ids for engines that filter by attribute (Sphinx). One more than $limit is returned at most,
     * so the caller can tell that the list was cut.
     *
     * @return int[]
     */
    public function tag_topic_ids(array $slugs, string $mode, bool $enabled_only, int $limit): array
    {
        $slugs = array_values(array_unique($slugs));
        if (!$slugs)
        {
            return [];
        }

        $result = $this->db->sql_query_limit($this->tag_topics_subquery($slugs, $mode, $enabled_only), $limit + 1);
        $ids = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['topic_id'];
        }
        $this->db->sql_freeresult($result);

        return $ids;
    }

    /** @return int[] forums where tags are enabled (explicit setting, or the default for forums without one) */
    public function enabled_forum_ids(): array
    {
        $configs = $this->load_configs();
        $default = !empty($this->config['iorgian_tags_default_enabled']);

        $ids = [];
        $result = $this->db->sql_query('SELECT forum_id FROM ' . FORUMS_TABLE . ' WHERE forum_type = ' . FORUM_POST);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $forum_id = (int) $row['forum_id'];
            if (isset($configs[$forum_id]) ? $configs[$forum_id]['enabled'] : $default)
            {
                $ids[] = $forum_id;
            }
        }
        $this->db->sql_freeresult($result);

        return $ids;
    }

    /**
     * Tags-only search for phpBB's search.php: one page of topic ids, sorted like the other searches.
     *
     * @param int[] $ex_fid_ary excluded forums (search.php already removed the ones the user cannot read)
     * @return array [int total, int[] topic ids of the page, int start (corrected if out of range)]
     */
    public function search_topic_ids(array $slugs, string $mode, array $ex_fid_ary, string $sort_key, string $sort_dir, int $sort_days, int $start, int $limit, bool $enabled_only): array
    {
        $slugs = array_values(array_unique($slugs));
        if (!$slugs || $limit < 1)
        {
            return [0, [], 0];
        }

        $where = 'tp.topic_id IN (' . $this->tag_topics_subquery($slugs, $mode, $enabled_only) . ')'
            . ' AND tp.topic_status <> ' . ITEM_MOVED
            . ($ex_fid_ary ? ' AND ' . $this->db->sql_in_set('tp.forum_id', $ex_fid_ary, true) : '')
            . ' AND ' . $this->visibility->get_global_visibility_sql('topic', $ex_fid_ary, 'tp.');

        if ($sort_days > 0)
        {
            $where .= ' AND tp.topic_last_post_time >= ' . (time() - $sort_days * 86400);
        }

        $from = TOPICS_TABLE . ' tp';
        switch ($sort_key)
        {
            case 'a':
                $from .= ' INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = tp.topic_poster';
                $order = 'u.username_clean';
            break;

            case 'f':
                $order = 'tp.forum_id';
            break;

            case 'i':
            case 's':
                $order = 'tp.topic_title';
            break;

            default:
                $order = 'tp.topic_last_post_time';
            break;
        }

        $result = $this->db->sql_query('SELECT COUNT(tp.topic_id) AS total FROM ' . $from . ' WHERE ' . $where);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        if (!$total)
        {
            return [0, [], 0];
        }

        if ($start < 0 || $start >= $total)
        {
            $start = (int) (floor(($total - 1) / $limit) * $limit);
        }

        $sql = 'SELECT tp.topic_id FROM ' . $from . ' WHERE ' . $where
            . ' ORDER BY ' . $order . ' ' . ($sort_dir === 'a' ? 'ASC' : 'DESC') . ', tp.topic_id DESC';
        $result = $this->db->sql_query_limit($sql, $limit, $start);
        $ids = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['topic_id'];
        }
        $this->db->sql_freeresult($result);

        return [$total, $ids, $start];
    }

    /**
     * Topics carrying all ('AND') or any ('OR') of the given tags.
     *
     * @param string[] $slugs
     * @return array [int total, array[] rows]
     */
    public function topics_by_tags(array $slugs, string $mode, int $start, int $limit): array
    {
        $slugs = array_values(array_unique($slugs));
        if (!$slugs)
        {
            return [0, []];
        }

        $sub = $this->tag_topics_subquery($slugs, $mode);

        $where = 'tp.topic_id IN (' . $sub . ') AND ' . $this->visible_sql('tp');

        $result = $this->db->sql_query('SELECT COUNT(tp.topic_id) AS total FROM ' . TOPICS_TABLE . ' tp WHERE ' . $where);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        if (!$total)
        {
            return [0, []];
        }

        $sql = 'SELECT tp.topic_id, tp.forum_id, tp.topic_title, tp.topic_time, tp.topic_posts_approved,
                tp.topic_views, tp.topic_poster, tp.topic_first_poster_name, tp.topic_first_poster_colour,
                tp.topic_last_post_id, tp.topic_last_post_time, tp.topic_last_poster_id,
                tp.topic_last_poster_name, tp.topic_last_poster_colour, f.forum_name
            FROM ' . TOPICS_TABLE . ' tp
            INNER JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = tp.forum_id
            WHERE ' . $where . '
            ORDER BY tp.topic_last_post_time DESC';
        $result = $this->db->sql_query_limit($sql, $limit, $start);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return [$total, $rows];
    }
}
