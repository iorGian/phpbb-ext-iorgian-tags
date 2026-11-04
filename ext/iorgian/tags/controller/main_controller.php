<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Public pages: tag cloud (/tags), topics by tag(s) (/tag/a,b/AND) and autocomplete.
 */
class main_controller
{
    protected const MAX_TAGS_IN_URL = 10;

    /** @var \iorgian\tags\service\repository */
    protected $repo;
    /** @var \iorgian\tags\service\renderer */
    protected $renderer;
    /** @var \iorgian\tags\service\prefix_bridge */
    protected $bridge;
    /** @var \iorgian\tags\service\normalizer */
    protected $normalizer;
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\controller\helper */
    protected $helper;
    /** @var \phpbb\pagination */
    protected $pagination;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\language\language */
    protected $language;
    /** @var \phpbb\user */
    protected $user;
    /** @var string */
    protected $root_path;
    /** @var string */
    protected $php_ext;

    public function __construct(
        \iorgian\tags\service\repository $repo,
        \iorgian\tags\service\renderer $renderer,
        \iorgian\tags\service\prefix_bridge $bridge,
        \iorgian\tags\service\normalizer $normalizer,
        \phpbb\config\config $config,
        \phpbb\controller\helper $helper,
        \phpbb\pagination $pagination,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language,
        \phpbb\user $user,
        string $root_path,
        string $php_ext
    ) {
        $this->repo = $repo;
        $this->renderer = $renderer;
        $this->bridge = $bridge;
        $this->normalizer = $normalizer;
        $this->config = $config;
        $this->helper = $helper;
        $this->pagination = $pagination;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
        $this->user = $user;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    /** /tags */
    public function cloud()
    {
        $this->language->add_lang('common', 'iorgian/tags');

        $tags = $this->repo->cloud(200);
        $show_count = !empty($this->config['iorgian_tags_show_count']);

        foreach ($tags as $tag)
        {
            $this->template->assign_block_vars('iorgian_tags_cloud', [
                'HTML' => $this->renderer->render_one($tag, 'iorgian-tag-lv' . $tag['level'], $show_count ? $tag['uses'] : null),
            ]);
        }

        $this->template->assign_var('S_IORGIAN_TAGS_CLOUD', !empty($tags));
        $this->breadcrumb($this->language->lang('IORGIAN_TAGS_CLOUD_TITLE'), $this->helper->route('iorgian_tags_cloud'));

        return $this->helper->render('iorgian_tags_cloud.html', $this->language->lang('IORGIAN_TAGS_CLOUD_TITLE'));
    }

    /** /tag/{tags}/{mode} */
    public function view($tags, $mode = 'AND', $casesensitive = 'false')
    {
        $this->language->add_lang('common', 'iorgian/tags');

        $mode = (strtoupper((string) $mode) === 'OR') ? 'OR' : 'AND';

        $slugs = [];
        foreach (explode(',', (string) $tags) as $part)
        {
            $slug = $this->normalizer->slug($part);
            if ($slug !== '')
            {
                $slugs[$slug] = true;
            }
        }
        $slugs = array_slice(array_keys($slugs), 0, self::MAX_TAGS_IN_URL);

        if (!$slugs)
        {
            throw new \phpbb\exception\http_exception(404, 'IORGIAN_TAGS_NOT_FOUND');
        }

        $names = [];
        foreach ($slugs as $slug)
        {
            $row = $this->repo->find_by_slug($slug);
            $names[] = $row ? $row['tag_name'] : $slug;
        }
        $title_names = htmlspecialchars(implode(', ', $names), ENT_COMPAT, 'UTF-8');

        $per_page = max(5, (int) $this->config['topics_per_page']);
        $start = max(0, $this->request->variable('start', 0));

        [$total, $rows] = $this->repo->topics_by_tags($slugs, $mode, $start, $per_page);

        $topic_ids = [];
        foreach ($rows as $row)
        {
            $topic_ids[] = (int) $row['topic_id'];
        }
        $tag_map = $this->repo->get_for_topics($topic_ids);
        $prefix_map = $this->bridge->html_for_topics($topic_ids);

        foreach ($rows as $row)
        {
            $topic_id = (int) $row['topic_id'];
            $last_post_id = (int) $row['topic_last_post_id'];

            $this->template->assign_block_vars('iorgian_tags_topics', [
                'TOPIC_TITLE'      => censor_text($row['topic_title']),
                'PREFIX_HTML'      => $prefix_map[$topic_id] ?? '',
                'TAGS_HTML'        => $this->renderer->render($tag_map[$topic_id] ?? []),
                'FORUM_NAME'       => $row['forum_name'],
                'U_VIEW_TOPIC'     => append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'f=' . (int) $row['forum_id'] . '&amp;t=' . $topic_id),
                'U_VIEW_FORUM'     => append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $row['forum_id']),
                'U_LAST_POST'      => append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $last_post_id) . '#p' . $last_post_id,
                'AUTHOR_FULL'      => get_username_string('full', (int) $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                'LAST_AUTHOR_FULL' => get_username_string('full', (int) $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                'FIRST_POST_TIME'  => $this->user->format_date((int) $row['topic_time']),
                'LAST_POST_TIME'   => $this->user->format_date((int) $row['topic_last_post_time']),
                'REPLIES'          => max(0, (int) $row['topic_posts_approved'] - 1),
                'VIEWS'            => (int) $row['topic_views'],
            ]);
        }

        $slug_list = implode(',', $slugs);
        $base_url = $this->renderer->url($slug_list, $mode);
        $this->pagination->generate_template_pagination($base_url, 'pagination', 'start', $total, $per_page, $start);

        $this->template->assign_vars([
            'IORGIAN_TAGS_TITLE'      => $title_names,
            'IORGIAN_TAGS_TOTAL'      => $this->language->lang('IORGIAN_TAGS_TOTAL_TOPICS', $total),
            'S_IORGIAN_TAGS_MULTI'    => count($slugs) > 1,
            'S_IORGIAN_TAGS_MODE_AND' => $mode === 'AND',
            'U_IORGIAN_TAGS_AND'      => $this->renderer->url($slug_list, 'AND'),
            'U_IORGIAN_TAGS_OR'       => $this->renderer->url($slug_list, 'OR'),
            'PAGE_NUMBER'             => $this->pagination->on_page($total, $per_page, $start),
            'TOTAL_TOPICS'            => $total,
        ]);

        $this->breadcrumb($this->language->lang('IORGIAN_TAGS_CLOUD_TITLE'), $this->helper->route('iorgian_tags_cloud'));
        $this->breadcrumb(implode(', ', $names), $base_url);

        return $this->helper->render('iorgian_tags_topics.html', $this->language->lang('IORGIAN_TAGS_TOPICS_TITLE', $title_names));
    }

    /** /tags/suggest?q=li&exclude=linux,debian : names for the autocomplete (registered users only). */
    public function suggest()
    {
        if ((int) $this->user->data['user_id'] === ANONYMOUS)
        {
            return new JsonResponse(['tags' => []]);
        }

        $typed = htmlspecialchars_decode($this->request->variable('q', '', true), ENT_COMPAT);
        $exclude = [];
        foreach (explode(',', htmlspecialchars_decode($this->request->variable('exclude', '', true), ENT_COMPAT)) as $part)
        {
            $slug = $this->normalizer->slug($part);
            if ($slug !== '')
            {
                $exclude[] = $slug;
            }
        }

        return new JsonResponse(['tags' => $this->repo->suggest($typed, $exclude, 10, $this->request->variable('f', 0))]);
    }

    protected function breadcrumb(string $name, string $url): void
    {
        $this->template->assign_block_vars('navlinks', [
            'BREADCRUMB_NAME' => htmlspecialchars($name, ENT_COMPAT, 'UTF-8'),
            'U_BREADCRUMB'    => $url,
        ]);
    }
}
