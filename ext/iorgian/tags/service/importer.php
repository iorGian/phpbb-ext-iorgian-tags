<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * One-way import from RH Topic Tags (robertheim/topictags). Source tables are only read,
 * never modified, so RH Topic Tags can be kept installed until the result is verified.
 */
class importer
{
    protected const CHUNK = 2000;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\db\tools\tools_interface */
    protected $tools;
    /** @var repository */
    protected $repo;
    /** @var normalizer */
    protected $normalizer;
    /** @var string */
    protected $t_rh_tags;
    protected $t_rh_topics;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\db\tools\tools_interface $tools,
        repository $repo,
        normalizer $normalizer,
        string $table_prefix
    ) {
        $this->db = $db;
        $this->tools = $tools;
        $this->repo = $repo;
        $this->normalizer = $normalizer;
        $this->t_rh_tags = $table_prefix . 'rh_topictags_tag';
        $this->t_rh_topics = $table_prefix . 'rh_topictags';
    }

    public function available(): bool
    {
        return $this->tools->sql_table_exists($this->t_rh_tags) && $this->tools->sql_table_exists($this->t_rh_topics);
    }

    /**
     * @return int[] [tags imported, assignments imported, forums enabled]
     */
    public function run(): array
    {
        if (!$this->available())
        {
            return [0, 0, 0];
        }

        // 1) Tags (names that collapse to the same slug are merged).
        $map = [];
        $result = $this->db->sql_query('SELECT id, tag FROM ' . $this->t_rh_tags);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        foreach ($rows as $row)
        {
            $name = $this->normalizer->sanitize(htmlspecialchars_decode((string) $row['tag'], ENT_COMPAT));
            if ($name === '' || $this->normalizer->slug($name) === '')
            {
                continue;
            }
            $map[(int) $row['id']] = $this->repo->get_or_create($name, '', true)['tag_id'];
        }
        $tags = count(array_unique($map));

        // 2) Assignments, in chunks.
        $assignments = 0;
        $start = 0;
        while (true)
        {
            $result = $this->db->sql_query_limit(
                'SELECT topic_id, tag_id FROM ' . $this->t_rh_topics . ' ORDER BY id ASC',
                self::CHUNK,
                $start
            );
            $pairs = [];
            $read = 0;
            while ($row = $this->db->sql_fetchrow($result))
            {
                $read++;
                if (isset($map[(int) $row['tag_id']]))
                {
                    $pairs[(int) $row['topic_id'] . ':' . $map[(int) $row['tag_id']]] = [(int) $row['topic_id'], $map[(int) $row['tag_id']]];
                }
            }
            $this->db->sql_freeresult($result);

            $assignments += $this->insert_pairs($pairs);
            $start += self::CHUNK;

            if ($read < self::CHUNK)
            {
                break;
            }
        }

        $this->repo->recount_all();

        // 3) Forums where RH Topic Tags was enabled.
        $forums = 0;
        if ($this->tools->sql_column_exists(FORUMS_TABLE, 'rh_topictags_enabled'))
        {
            $result = $this->db->sql_query('SELECT forum_id FROM ' . FORUMS_TABLE . ' WHERE rh_topictags_enabled = 1');
            $ids = [];
            while ($row = $this->db->sql_fetchrow($result))
            {
                $ids[] = (int) $row['forum_id'];
            }
            $this->db->sql_freeresult($result);

            foreach ($ids as $forum_id)
            {
                $cfg = $this->repo->get_forum_config($forum_id);
                $this->repo->set_forum_config($forum_id, true, $cfg['min'], $cfg['max']);
                $forums++;
            }
        }

        return [$tags, $assignments, $forums];
    }

    /** @param array<string,int[]> $pairs */
    protected function insert_pairs(array $pairs): int
    {
        if (!$pairs)
        {
            return 0;
        }

        $topic_ids = array_unique(array_column($pairs, 0));

        // Only topics that still exist.
        $exist = [];
        $result = $this->db->sql_query('SELECT topic_id FROM ' . TOPICS_TABLE . ' WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids));
        while ($row = $this->db->sql_fetchrow($result))
        {
            $exist[(int) $row['topic_id']] = true;
        }
        $this->db->sql_freeresult($result);

        // Skip pairs already present (the import can be run twice safely).
        $have = [];
        $result = $this->db->sql_query('SELECT topic_id, tag_id FROM ' . $this->repo->topics_table() . ' WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids));
        while ($row = $this->db->sql_fetchrow($result))
        {
            $have[(int) $row['topic_id'] . ':' . (int) $row['tag_id']] = true;
        }
        $this->db->sql_freeresult($result);

        $insert = [];
        foreach ($pairs as $key => [$topic_id, $tag_id])
        {
            if (isset($exist[$topic_id]) && !isset($have[$key]))
            {
                $insert[] = ['topic_id' => $topic_id, 'tag_id' => $tag_id];
            }
        }

        if ($insert)
        {
            $this->db->sql_multi_insert($this->repo->topics_table(), $insert);
        }

        return count($insert);
    }
}
