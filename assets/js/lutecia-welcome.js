/**
 * Welcome screen: connect, agency code; on a copy of a connected
 * store, the reconnect choice. A fresh connection opens the setup wizard.
 */
(function (L) {
	'use strict';
	if (L.off) {
		return;
	}

	var cfg = L.cfg;
	var connectBtn = L.q('[data-lutecia-connect]');
	var connectLabel = L.q('[data-lutecia-connect-label]');
	var errorEl = L.q('[data-lutecia-error]');
	var resetBtn = L.q('[data-lutecia-reset]');
	var agencyToggle = L.q('[data-lutecia-agency-toggle]');

	function panel(name) {
		L.qa('[data-lutecia-welcome]').forEach(function (el) {
			el.hidden = el.getAttribute('data-lutecia-welcome') !== name;
		});
	}

	function resetConnect() {
		connectBtn.disabled = false;
		connectLabel.textContent = cfg.i18n.connect;
	}

	function fail(message) {
		errorEl.textContent = message;
		errorEl.hidden = false;
		resetConnect();
	}

	/** Back to the connect panel, as after a disconnect or a reset. */
	L.showWelcome = function () {
		panel('connect');
		resetConnect();
		L.showScreen('welcome');
	};

	if (agencyToggle) {
		agencyToggle.addEventListener('click', function () {
			L.q('[data-lutecia-agency-field]').hidden = false;
			agencyToggle.setAttribute('aria-expanded', 'true');
			agencyToggle.parentNode.hidden = true;
			L.q('[data-lutecia-agency]').focus();
		});
	}

	connectBtn.addEventListener('click', function () {
		connectBtn.disabled = true;
		errorEl.hidden = true;
		connectLabel.textContent = cfg.i18n.connecting;
		var agency = L.q('[data-lutecia-agency]');
		L.api('connect', 'POST', { agency_code: agency ? agency.value.trim() : '' }).then(function (res) {
			if (!res.ok) {
				fail(L.failure(res));
				return;
			}
			L.app.setAttribute('data-connected', '1');
			L.state.wizard = { step: 1, assistants_done: false };
			L.openSetup(1);
			L.watchSync();
			L.loadSettings().catch(function () {});
		}).catch(function () {
			fail(cfg.i18n.genericError);
		});
	});

	resetBtn.addEventListener('click', function () {
		L.confirm(resetBtn, cfg.i18n.confirmReset, cfg.i18n.reconnect).then(function (yes) {
			if (!yes) {
				return;
			}
			resetBtn.disabled = true;
			L.api('reset', 'POST').then(function (res) {
				resetBtn.disabled = false;
				if (res.ok) {
					L.app.setAttribute('data-connected', '0');
					L.app.setAttribute('data-duplicate', '0');
					L.showWelcome();
				} else {
					L.notice(resetBtn, L.failure(res));
				}
			}).catch(function () {
				resetBtn.disabled = false;
				L.notice(resetBtn, cfg.i18n.genericError);
			});
		});
	});

	// A copy detected, or a connection lost, while the page is open.
	L.on('status', function (s) {
		if (s.duplicate) {
			L.q('[data-lutecia-dup-expected]').textContent = s.duplicate.expected || '';
			L.q('[data-lutecia-dup-seen]').textContent = s.duplicate.seen || '';
			panel('duplicate');
			L.showScreen('welcome');
		} else if (!s.connected) {
			L.app.setAttribute('data-connected', '0');
			L.showWelcome();
		}
	});
})(window.luteciaScreen);
