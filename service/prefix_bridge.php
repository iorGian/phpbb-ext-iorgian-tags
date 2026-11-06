<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * Optional bridge to iorgian/multiprefixes. It only uses MP's public services
 * (repository + renderer) and never touches its tables. When MP is not installed,
 * or fails for any reason, every method degrades silently.
 */
class prefix_bridge
{
    /** @var \iorgian\multiprefixes\service\repository|null */
    protected $repo;
    /** @var \iorgian\multiprefixes\service\renderer|null */
    protected $renderer;
    /** @var normalizer */
    protected $normalizer;

    public function __construct($repo, $renderer, normalizer $normalizer)
    {
        $this->repo = $repo;
        $this->renderer = $renderer;
        $this->normalizer = $normalizer;
    }

    public function active(): bool
    {
        try
        {
            return $this->repo !== null && $this->renderer !== null && $this->repo->is_complete();
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    /**
     * Name of a Multi Prefixes prefix that has the same name as the tag, or null.
     */
    public function find_conflict(string $tag_name): ?string
    {
        if (!$this->active())
        {
            return null;
        }

        $slug = $this->normalizer->slug($tag_name);

        try
        {
            foreach ($this->repo->get_all() as $prefix)
            {
                // MP stores prefix names HTML-escaped.
                $name = html_entity_decode((string) $prefix['prefix_name'], ENT_QUOTES, 'UTF-8');
                if ($slug !== '' && $this->normalizer->slug($name) === $slug)
                {
                    return $name;
                }
            }
        }
        catch (\Throwable $e)
        {
            return null;
        }

        return null;
    }

    /**
     * Prefix badges (HTML) for several topics.
     *
     * @param int[] $topic_ids
     * @return array<int,string> topic_id => HTML
     */
    public function html_for_topics(array $topic_ids): array
    {
        if (!$this->active() || !$topic_ids)
        {
            return [];
        }

        $out = [];
        try
        {
            foreach ($this->repo->get_for_topics($topic_ids) as $topic_id => $prefixes)
            {
                $out[(int) $topic_id] = $this->renderer->render($prefixes);
            }
        }
        catch (\Throwable $e)
        {
            return [];
        }

        return $out;
    }
}
