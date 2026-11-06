/**
 * Tags: autocomplete for the tag field (plain JS, no dependencies).
 * Suggests names for the tag being typed (the text after the last comma) using a <datalist>.
 */
(function ()
{
	'use strict';

	var input = document.getElementById('iorgian_tags');
	var list = document.getElementById('iorgian_tags_datalist');

	if (!input || !list || !window.fetch)
	{
		return;
	}

	var timer = null;
	var lastQuery = null;

	function currentToken()
	{
		var parts = input.value.split(',');
		return parts[parts.length - 1].replace(/^\s+/, '');
	}

	function alreadyChosen()
	{
		var parts = input.value.split(',');
		parts.pop();
		return parts.map(function (p) { return p.trim(); }).filter(Boolean).join(',');
	}

	function prefixFromInput()
	{
		var idx = input.value.lastIndexOf(',');
		return idx === -1 ? '' : input.value.substring(0, idx + 1) + ' ';
	}

	function render(names)
	{
		var prefix = prefixFromInput();
		while (list.firstChild)
		{
			list.removeChild(list.firstChild);
		}
		names.forEach(function (name)
		{
			var option = document.createElement('option');
			option.value = prefix + name;
			list.appendChild(option);
		});
	}

	function suggest()
	{
		var token = currentToken();
		if (token.length < 2 || token === lastQuery)
		{
			return;
		}
		lastQuery = token;

		var url = input.getAttribute('data-suggest')
			+ (input.getAttribute('data-suggest').indexOf('?') === -1 ? '?' : '&')
			+ 'q=' + encodeURIComponent(token)
			+ '&exclude=' + encodeURIComponent(alreadyChosen())
			+ '&f=' + encodeURIComponent(input.getAttribute('data-forum') || '0');

		fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (response) { return response.ok ? response.json() : { tags: [] }; })
			.then(function (data) { render(data.tags || []); })
			.catch(function () { /* autocomplete is a convenience only */ });
	}

	input.addEventListener('input', function ()
	{
		clearTimeout(timer);
		timer = setTimeout(suggest, 200);
	});
})();
