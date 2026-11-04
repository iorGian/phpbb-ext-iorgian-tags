<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * Builds tag links using the shared badge helper from iorgian/core.
 */
class renderer
{
    /** @var \iorgian\core\util\badge|null */
    protected $badge;
    /** @var \phpbb\controller\helper */
    protected $helper;

    public function __construct($badge, \phpbb\controller\helper $helper)
    {
        $this->badge = $badge;
        $this->helper = $helper;
    }

    public function available(): bool
    {
        return $this->badge !== null;
    }

    public function url(string $slugs, string $mode = 'AND'): string
    {
        $params = ['tags' => $slugs];
        if ($mode === 'OR')
        {
            $params['mode'] = 'OR';
        }

        return $this->helper->route('iorgian_tags_view', $params);
    }

    /**
     * One clickable tag. Tag names are stored raw, so the badge escapes them.
     *
     * @param int|null $count show the number of topics next to the name when not null
     */
    public function render_one(array $tag, string $extra_class = '', ?int $count = null): string
    {
        if ($this->badge === null)
        {
            return '';
        }

        $label = htmlspecialchars((string) $tag['tag_name'], ENT_COMPAT, 'UTF-8');
        if ($count !== null)
        {
            $label .= ' <span class="iorgian-tag-count">' . (int) $count . '</span>';
        }

        return '<a class="iorgian-tag-link" href="' . $this->url((string) $tag['tag_slug']) . '">'
            . $this->badge->render($label, (string) ($tag['tag_color'] ?? ''), '', trim('iorgian-tag ' . $extra_class), true)
            . '</a> ';
    }

    /** @param array[] $tags */
    public function render(array $tags): string
    {
        $html = '';
        foreach ($tags as $tag)
        {
            $html .= $this->render_one($tag);
        }

        return $html;
    }

    /** Non-clickable badge (ACP preview). */
    public function preview(array $tag): string
    {
        return ($this->badge === null) ? '' : $this->badge->render((string) $tag['tag_name'], (string) ($tag['tag_color'] ?? ''), '', 'iorgian-tag');
    }
}
