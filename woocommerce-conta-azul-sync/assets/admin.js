(function () {
	'use strict';

	function qs(selector, scope) {
		return (scope || document).querySelector(selector);
	}

	function qsa(selector, scope) {
		return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
	}

	function activateTab(tabId) {
		var target = tabId || 'connection';
		qsa('[data-wcas-panel]').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-wcas-panel') !== target;
		});
		qsa('[data-wcas-tab]').forEach(function (tab) {
			var active = tab.getAttribute('data-wcas-tab') === target;
			tab.classList.toggle('wcas-tab--active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
		});
	}

	function initTabs() {
		var tabs = qsa('[data-wcas-tab]');
		if (!tabs.length) {
			return;
		}
		tabs.forEach(function (tab) {
			tab.setAttribute('role', 'tab');
			tab.addEventListener('click', function (event) {
				event.preventDefault();
				var tabId = tab.getAttribute('data-wcas-tab');
				activateTab(tabId);
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', '#wcas-panel-' + tabId);
				}
			});
		});

		var initial = 'connection';
		if (window.location.hash && window.location.hash.indexOf('#wcas-panel-') === 0) {
			initial = window.location.hash.replace('#wcas-panel-', '');
		}
		var tabParam = new URLSearchParams(window.location.search).get('tab');
		if (tabParam) {
			initial = tabParam;
		}
		activateTab(initial);
	}

	function initSecretField() {
		var secret = qs('#wcas_client_secret');
		if (secret) {
			secret.addEventListener('focus', function () {
				secret.removeAttribute('placeholder');
			});
		}
	}

	function initLogFilters() {
		var search = qs('#wcas-log-search');
		var type = qs('#wcas-log-type');
		var rows = qsa('[data-wcas-log-row]');
		var empty = qs('.wcas-empty-state--filtered');
		if (!rows.length || !search || !type) {
			return;
		}

		function filterRows() {
			var term = search.value.trim().toLowerCase();
			var selectedType = type.value;
			var visible = 0;
			rows.forEach(function (row) {
				var matchesTerm = !term || row.textContent.toLowerCase().indexOf(term) !== -1;
				var matchesType = !selectedType || row.getAttribute('data-wcas-type') === selectedType;
				var show = matchesTerm && matchesType;
				row.hidden = !show;
				if (show) {
					visible += 1;
				}
			});
			if (empty) {
				empty.hidden = visible !== 0;
			}
		}

		search.addEventListener('input', filterRows);
		type.addEventListener('change', filterRows);
	}


	function initOAuthTraceFilters() {
		var search = qs('#wcas-oauth-trace-search');
		var phase = qs('#wcas-oauth-trace-phase');
		var rows = qsa('[data-wcas-oauth-trace-row]');
		var empty = qs('.wcas-empty-state--trace-filtered');
		if (!rows.length || !search || !phase) {
			return;
		}

		function filterRows() {
			var term = search.value.trim().toLowerCase();
			var selectedPhase = phase.value;
			var visible = 0;
			rows.forEach(function (row) {
				var matchesTerm = !term || row.textContent.toLowerCase().indexOf(term) !== -1;
				var matchesPhase = !selectedPhase || row.getAttribute('data-wcas-phase') === selectedPhase;
				var show = matchesTerm && matchesPhase;
				row.hidden = !show;
				if (show) {
					visible += 1;
				}
			});
			if (empty) {
				empty.hidden = visible !== 0;
			}
		}

		search.addEventListener('input', filterRows);
		phase.addEventListener('change', filterRows);
	}


	function stripInvisible(value) {
		return String(value || '').replace(/[\u0000-\u001F\u007F\u00A0\u200B-\u200D\uFEFF]/g, '');
	}

	function safeDecode(value) {
		try {
			return decodeURIComponent(value);
		} catch (error) {
			return value;
		}
	}

	function parseUri(value) {
		try {
			return new URL(value);
		} catch (error) {
			return null;
		}
	}

	function compareRedirectUris(expected, portal) {
		var differences = [];
		var rawExpected = String(expected || '');
		var rawPortal = String(portal || '');
		var cleanExpected = stripInvisible(rawExpected).trim();
		var cleanPortal = stripInvisible(rawPortal).trim();
		var decodedExpected = safeDecode(cleanExpected);
		var decodedPortal = safeDecode(cleanPortal);
		var expectedUrl = parseUri(decodedExpected);
		var portalUrl = parseUri(decodedPortal);

		if (!rawPortal.trim()) {
			differences.push('A Redirect URI cadastrada no Portal não foi informada.');
			return differences;
		}
		if (rawExpected !== cleanExpected || rawPortal !== cleanPortal) {
			differences.push('Caracteres invisíveis ou espaços extras detectados.');
		}
		if (cleanExpected !== decodedExpected || cleanPortal !== decodedPortal) {
			differences.push('Encoding diferente: compare a versão decodificada e não cole a URL duplamente codificada.');
		}
		if (!expectedUrl || !portalUrl) {
			differences.push('Uma das URIs não é uma URL absoluta válida.');
			return differences;
		}
		if (expectedUrl.protocol !== portalUrl.protocol) {
			differences.push('Protocolo diferente: plugin=' + expectedUrl.protocol + ' portal=' + portalUrl.protocol);
		}
		if (expectedUrl.hostname !== portalUrl.hostname) {
			differences.push('Domínio diferente: plugin=' + expectedUrl.hostname + ' portal=' + portalUrl.hostname);
		}
		if (expectedUrl.hostname.replace(/^www\./i, '') === portalUrl.hostname.replace(/^www\./i, '') && expectedUrl.hostname !== portalUrl.hostname) {
			differences.push('Diferença de www: um valor usa www e o outro não.');
		}
		if (expectedUrl.pathname !== portalUrl.pathname) {
			differences.push('Caminho diferente: plugin=' + expectedUrl.pathname + ' portal=' + portalUrl.pathname);
		}
		if (expectedUrl.search !== portalUrl.search) {
			differences.push('Query string diferente: plugin=' + (expectedUrl.search || '(vazia)') + ' portal=' + (portalUrl.search || '(vazia)'));
		}
		if (/\/$/.test(expectedUrl.pathname) !== /\/$/.test(portalUrl.pathname)) {
			differences.push('Barra final diferente no caminho.');
		}
		if (decodedExpected.toLowerCase() === decodedPortal.toLowerCase() && decodedExpected !== decodedPortal) {
			differences.push('Letras maiúsculas/minúsculas diferentes.');
		}
		if (decodedExpected !== decodedPortal && !differences.length) {
			differences.push('As strings completas são diferentes, mesmo sem diferença estrutural identificada.');
		}
		return differences;
	}

	function initRedirectUriExactMatch() {
		var box = qs('[data-wcas-redirect-match]');
		if (!box) {
			return;
		}
		var input = qs('[data-wcas-portal-uri]', box);
		var button = qs('[data-wcas-compare-uris]', box);
		var result = qs('[data-wcas-compare-result]', box);
		var expected = box.getAttribute('data-sent-uri') || box.getAttribute('data-plugin-uri') || '';
		if (!input || !button || !result) {
			return;
		}
		button.addEventListener('click', function () {
			var differences = compareRedirectUris(expected, input.value);
			if (!differences.length) {
				result.className = 'wcas-redirect-match__result wcas-redirect-match__result--match';
				result.innerHTML = '<strong>MATCH ✅</strong><p>A Redirect URI cadastrada no Portal é exatamente igual à enviada para a Conta Azul.</p>';
				return;
			}
			result.className = 'wcas-redirect-match__result wcas-redirect-match__result--mismatch';
			result.innerHTML = '<strong>MISMATCH ❌</strong><p>Diferenças encontradas:</p><ul><li>' + differences.map(function (item) {
				return item.replace(/[&<>"']/g, function (char) {
					return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
				});
			}).join('</li><li>') + '</li></ul>';
		});
	}

	function initConnectionTest() {
		var box = qs('[data-wcas-test-box]');
		var button = qs('[data-wcas-run-test]');
		var result = qs('[data-wcas-test-result]');
		if (!box || !button || !result) {
			return;
		}

		button.addEventListener('click', function () {
			var missing = [];
			if (box.getAttribute('data-client-id') !== 'yes') {
				missing.push('Client ID');
			}
			if (box.getAttribute('data-client-secret') !== 'yes') {
				missing.push('Client Secret');
			}
			if (box.getAttribute('data-redirect-uri') !== 'yes') {
				missing.push('Redirect URI');
			}

			var dotClass = 'wcas-is-success';
			var message = 'Configuração local parece pronta. Faça um pedido teste após validar endpoints reais.';
			if (missing.length) {
				dotClass = 'wcas-is-danger';
				message = 'Campos obrigatórios ausentes: ' + missing.join(', ') + '.';
			} else if (box.getAttribute('data-connected') !== 'yes') {
				dotClass = 'wcas-is-warning';
				message = 'Credenciais preenchidas. Conecte a Conta Azul para concluir o OAuth2.';
			} else if (box.getAttribute('data-token-expired') === 'yes') {
				dotClass = 'wcas-is-warning';
				message = 'OAuth conectado, mas o token está expirado. A próxima chamada tentará refresh token.';
			}

			result.innerHTML = '<span class="wcas-status-dot ' + dotClass + '"></span><span>' + message + '</span>';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initTabs();
		initSecretField();
		initLogFilters();
		initOAuthTraceFilters();
		initRedirectUriExactMatch();
		initConnectionTest();
	});
}());
