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
		if (new URLSearchParams(window.location.search).get('tab') === 'logs') {
			initial = 'logs';
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
		initConnectionTest();
	});
}());
