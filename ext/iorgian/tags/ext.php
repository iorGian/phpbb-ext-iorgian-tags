<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags;

class ext extends \phpbb\extension\base
{
    /**
     * Requires iorgian/core to be installed and enabled.
     * iorgian/multiprefixes is optional: if present, both extensions cooperate.
     */
    public function is_enableable()
    {
        $c = $this->container;
        $core_ok = $c->get('ext.manager')->is_enabled('iorgian/core')
            && $c->has('iorgian.core.badge')
            && $c->has('iorgian.core.forums')
            && $c->has('iorgian.core.acl')
            && $c->has('iorgian.core.colors')
            && $c->has('iorgian.core.color_field');

        if (!$core_ok)
        {
            $language = $c->get('language');
            $language->add_lang('common', 'iorgian/tags');
            return [$language->lang('IORGIAN_TAGS_REQUIRES_CORE')];
        }

        return true;
    }
}
