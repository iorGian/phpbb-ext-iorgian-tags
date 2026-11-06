<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\tags\service;

/**
 * Tag name cleaning, validation and slug generation.
 *
 * Names are stored RAW (never HTML-escaped) and escaped on output. phpBB's request class
 * returns already-escaped strings, so parse() decodes them first.
 *
 * Candidate to move into iorgian/core (generic text helper) in a future core release.
 */
class normalizer
{
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\config\db_text */
    protected $config_text;
    /** @var string[]|null */
    protected $blacklist = null;

    public function __construct(\phpbb\config\config $config, \phpbb\config\db_text $config_text)
    {
        $this->config = $config;
        $this->config_text = $config_text;
    }

    public function max_length(): int
    {
        $max = (int) ($this->config['iorgian_tags_max_length'] ?? 30);

        return max(2, min(100, $max ?: 30));
    }

    /** Trim, collapse whitespace and normalise Unicode (NFC). Input must be RAW (decoded). */
    public function clean(string $name): string
    {
        $name = utf8_normalize_nfc($name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }

    /** Lowercase, URL-safe identifier. "C++" => "c-plus-plus", "Node.js" => "node-js". */
    public function slug(string $name): string
    {
        $s = utf8_strtolower($this->clean($name));
        $s = str_replace(['+', '#', '.'], [' plus ', ' sharp ', ' '], $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);
        $s = trim((string) $s, '-');

        return (string) utf8_substr($s, 0, 100);
    }

    /** Removes disallowed characters and truncates (used when importing foreign data). */
    public function sanitize(string $name): string
    {
        $name = $this->clean($name);
        $name = preg_replace('/[^\p{L}\p{N} _.+#-]+/u', '', $name);
        $name = ltrim($this->clean((string) $name), ' _.+#-');

        return (string) utf8_substr($name, 0, $this->max_length());
    }

    /**
     * @return string|null null = valid, otherwise 'invalid' or 'blacklisted'
     */
    public function check(string $name): ?string
    {
        $len = utf8_strlen($name);
        if ($len < 1 || $len > $this->max_length() || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.+#-]*$/u', $name))
        {
            return 'invalid';
        }

        $slug = $this->slug($name);
        if ($slug === '')
        {
            return 'invalid';
        }

        return in_array($slug, $this->blacklist(), true) ? 'blacklisted' : null;
    }

    /**
     * Parses what a user typed (comma / semicolon / newline separated, phpBB-escaped).
     *
     * @return array ['names' => slug => name, 'invalid' => string[], 'blacklisted' => string[]]
     */
    public function parse(string $escaped_input): array
    {
        $input = htmlspecialchars_decode($escaped_input, ENT_COMPAT);
        $out = ['names' => [], 'invalid' => [], 'blacklisted' => []];

        foreach ((array) preg_split('/[,;\n\r]+/u', $input) as $part)
        {
            $name = $this->clean((string) $part);
            if ($name === '')
            {
                continue;
            }

            $error = $this->check($name);
            if ($error === 'invalid')
            {
                $out['invalid'][] = $name;
            }
            else if ($error === 'blacklisted')
            {
                $out['blacklisted'][] = $name;
            }
            else
            {
                $slug = $this->slug($name);
                if (!isset($out['names'][$slug]))
                {
                    $out['names'][$slug] = $name;
                }
            }
        }

        return $out;
    }

    /** @return string[] blacklisted slugs */
    public function blacklist(): array
    {
        if ($this->blacklist === null)
        {
            $this->blacklist = [];
            foreach (preg_split('/[\r\n]+/', (string) $this->config_text->get('iorgian_tags_blacklist')) as $line)
            {
                $slug = $this->slug((string) $line);
                if ($slug !== '')
                {
                    $this->blacklist[] = $slug;
                }
            }
        }

        return $this->blacklist;
    }
}
