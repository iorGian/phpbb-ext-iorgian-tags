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
	'IORGIAN_TAGS_REQUIRES_CORE' => 'Tags requiere que “Iorgian Core” 0.2.4-a1 o posterior esté instalado y activado antes.',
	'IORGIAN_TAGS_LABEL' => 'Etiquetas',
	'IORGIAN_TAGS_HELP' => 'Separa las etiquetas con comas.',
	'IORGIAN_TAGS_HELP_MIN' => 'Introduce al menos %d.',
	'IORGIAN_TAGS_HELP_MAX' => 'Hasta %d etiquetas.',
	'IORGIAN_TAGS_HELP_MIN_MAX' => 'Entre %1$d y %2$d etiquetas.',
	'IORGIAN_TAGS_HELP_NO_CREATE' => 'Solo puedes usar etiquetas existentes.',
	'IORGIAN_TAGS_ERR_INVALID' => 'Etiqueta(s) no válida(s): %s. Se permiten letras, números, espacios y - _ . + # (dentro de la longitud máxima).',
	'IORGIAN_TAGS_ERR_BLACKLISTED' => 'Estas etiquetas no están permitidas: %s',
	'IORGIAN_TAGS_ERR_MIN' => 'Debes introducir al menos %d etiqueta(s).',
	'IORGIAN_TAGS_ERR_MAX' => 'Puedes introducir como máximo %d etiqueta(s).',
	'IORGIAN_TAGS_ERR_NOT_EXIST' => 'Estas etiquetas no existen y no tienes permiso para crear nuevas: %s',
	'IORGIAN_TAGS_ERR_PREFIX_CONFLICT' => 'Estas etiquetas tienen el mismo nombre que un prefijo: %s. Usa el prefijo en su lugar.',
	'IORGIAN_TAGS_NAV' => 'Etiquetas',
	'IORGIAN_TAGS_CLOUD' => 'Nube de etiquetas',
	'IORGIAN_TAGS_CLOUD_TITLE' => 'Todas las etiquetas',
	'IORGIAN_TAGS_NO_TAGS' => 'Todavía no hay etiquetas.',
	'IORGIAN_TAGS_TOPICS_TITLE' => 'Temas con la etiqueta: %s',
	'IORGIAN_TAGS_TOPICS_HEADING' => 'Temas etiquetados',
	'IORGIAN_TAGS_MATCH' => 'Coincidencia',
	'IORGIAN_TAGS_MODE_AND' => 'Todas las etiquetas',
	'IORGIAN_TAGS_MODE_OR' => 'Cualquiera de las etiquetas',
	'IORGIAN_TAGS_TOTAL_TOPICS' => '%d tema(s)',
	'IORGIAN_TAGS_NO_TOPICS' => 'Ningún tema visible tiene estas etiquetas.',
	'IORGIAN_TAGS_IN_FORUM' => 'en',
	'IORGIAN_TAGS_BY' => 'por',
	'IORGIAN_TAGS_NOT_FOUND' => 'No se ha indicado ninguna etiqueta.',
	'IORGIAN_TAGS_SEARCH_EXPLAIN' => 'Separa las etiquetas con comas. Se puede combinar con palabras clave y autor.',
	'IORGIAN_TAGS_SEARCH_FILTER' => 'Filtro de etiquetas',
	'IORGIAN_TAGS_SEARCH_NOT_ALLOWED' => 'No tienes permiso para buscar por etiquetas.',
	'IORGIAN_TAGS_SEARCH_BACKEND' => 'Buscar por etiquetas junto con palabras clave o un autor no está soportado por este motor de búsqueda. Usa el motor nativo, MySQL, PostgreSQL o Sphinx, busca solo por etiquetas, o quita las etiquetas.',
	'IORGIAN_TAGS_ERR_RESTRICTED' => 'Estas etiquetas están reservadas a otros grupos de usuarios: %s',
	'IORGIAN_TAGS_LOCKED' => 'Etiquetas reservadas (no puedes cambiarlas)',
	'IORGIAN_TAGS_ERR_NOT_OFFICIAL' => 'Estas etiquetas no están en la lista permitida: %s',
	'IORGIAN_TAGS_HELP_WHITELIST' => 'Solo se pueden usar etiquetas de la lista permitida.',
	'IORGIAN_TAGS_SEARCH_TOO_MANY' => 'Estas etiquetas coinciden con demasiados temas para este motor de búsqueda. Usa etiquetas más específicas.',
]);
