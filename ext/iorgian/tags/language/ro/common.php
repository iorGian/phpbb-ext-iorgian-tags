<?php
/**
 * Tags
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'IORGIAN_TAGS_REQUIRES_CORE' => 'Tags necesită ca „Iorgian Core” 0.2.4-a1 sau mai nou să fie instalat și activat în prealabil.',
	'IORGIAN_TAGS_LABEL' => 'Etichete',
	'IORGIAN_TAGS_HELP' => 'Separă etichetele prin virgulă.',
	'IORGIAN_TAGS_HELP_MIN' => 'Introdu cel puțin %d.',
	'IORGIAN_TAGS_HELP_MAX' => 'Maximum %d etichete.',
	'IORGIAN_TAGS_HELP_MIN_MAX' => 'Între %1$d și %2$d etichete.',
	'IORGIAN_TAGS_HELP_NO_CREATE' => 'Poți folosi doar etichete existente.',
	'IORGIAN_TAGS_ERR_INVALID' => 'Etichete nevalide: %s. Sunt permise litere, cifre, spații și - _ . + # (în limita lungimii maxime).',
	'IORGIAN_TAGS_ERR_BLACKLISTED' => 'Aceste etichete nu sunt permise: %s',
	'IORGIAN_TAGS_ERR_MIN' => 'Trebuie să introduci cel puțin %d etichete.',
	'IORGIAN_TAGS_ERR_MAX' => 'Poți introduce cel mult %d etichete.',
	'IORGIAN_TAGS_ERR_NOT_EXIST' => 'Aceste etichete nu există și nu ai permisiunea de a crea etichete noi: %s',
	'IORGIAN_TAGS_ERR_PREFIX_CONFLICT' => 'Aceste etichete au același nume ca un prefix: %s. Folosește prefixul în locul lor.',
	'IORGIAN_TAGS_NAV' => 'Etichete',
	'IORGIAN_TAGS_CLOUD' => 'Nor de etichete',
	'IORGIAN_TAGS_CLOUD_TITLE' => 'Toate etichetele',
	'IORGIAN_TAGS_NO_TAGS' => 'Nu există încă etichete.',
	'IORGIAN_TAGS_TOPICS_TITLE' => 'Subiecte etichetate: %s',
	'IORGIAN_TAGS_TOPICS_HEADING' => 'Subiecte etichetate',
	'IORGIAN_TAGS_MATCH' => 'Potrivire',
	'IORGIAN_TAGS_MODE_AND' => 'Toate etichetele',
	'IORGIAN_TAGS_MODE_OR' => 'Oricare dintre etichete',
	'IORGIAN_TAGS_TOTAL_TOPICS' => '%d subiecte',
	'IORGIAN_TAGS_NO_TOPICS' => 'Niciun subiect vizibil nu are aceste etichete.',
	'IORGIAN_TAGS_IN_FORUM' => 'în',
	'IORGIAN_TAGS_BY' => 'de',
	'IORGIAN_TAGS_NOT_FOUND' => 'Nu a fost specificată nicio etichetă.',
	'IORGIAN_TAGS_SEARCH_EXPLAIN' => 'Separă etichetele prin virgulă. Poate fi combinat cu cuvinte-cheie și autor.',
	'IORGIAN_TAGS_SEARCH_FILTER' => 'Filtru de etichete',
	'IORGIAN_TAGS_SEARCH_NOT_ALLOWED' => 'Nu ai permisiunea de a căuta după etichete.',
	'IORGIAN_TAGS_SEARCH_BACKEND' => 'Căutarea după etichete împreună cu cuvinte-cheie sau un autor nu este acceptată de acest motor de căutare. Folosește motorul nativ, MySQL, PostgreSQL sau Sphinx, caută doar după etichete sau elimină etichetele.',
	'IORGIAN_TAGS_ERR_RESTRICTED' => 'Aceste etichete sunt rezervate altor grupuri de utilizatori: %s',
	'IORGIAN_TAGS_LOCKED' => 'Etichete rezervate (nu le poți modifica)',
	'IORGIAN_TAGS_ERR_NOT_OFFICIAL' => 'Aceste etichete nu sunt în lista permisă: %s',
	'IORGIAN_TAGS_HELP_WHITELIST' => 'Pot fi folosite doar etichete din lista permisă.',
	'IORGIAN_TAGS_SEARCH_TOO_MANY' => 'Aceste etichete se potrivesc cu prea multe subiecte pentru acest motor de căutare. Folosește etichete mai specifice.',
]);
