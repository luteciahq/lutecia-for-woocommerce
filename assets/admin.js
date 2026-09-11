/**
 * Admin screen logic: connect / sync / channels wizard, disconnect,
 * status refresh. Vanilla JS on purpose: no build step, no dependency,
 * trivially auditable.
 */
(function () {
	'use strict';

	var cfg = window.luteciaAdmin || {};
	var app = document.getElementById('lutecia-app');
	if (!app || !cfg.restUrl) {
		return;
	}

	var STEPS = { duplicate: '1', disconnected: '1', syncing: '2', connected: '3' };

	function api(path, method, body) {
		var init = {
			method: method || 'GET',
			headers: { 'X-WP-Nonce': cfg.restNonce, 'Content-Type': 'application/json' },
			credentials: 'same-origin'
		};
		if (body) {
			init.body = JSON.stringify(body);
		}
		return fetch(cfg.restUrl + path, init).then(function (res) {
			return res.json().then(function (json) {
				return { ok: res.ok, status: res.status, json: json };
			});
		});
	}

	function show(state) {
		app.querySelectorAll('[data-lutecia-view]').forEach(function (el) {
			el.hidden = el.getAttribute('data-lutecia-view') !== state;
		});
		app.setAttribute('data-lutecia-step', STEPS[state] || '1');
	}

	function showError(message, status) {
		var el = app.querySelector('[data-lutecia-error]');
		if (el) {
			if (status === 401 || status === 403) {
				message = cfg.i18n.sessionExpired;
			}
			el.textContent = message || cfg.i18n.genericError;
			el.hidden = false;
		}
	}

	function setProductCount(count) {
		app.querySelectorAll('[data-lutecia-product-count]').forEach(function (el) {
			el.textContent = count === 1
				? cfg.i18n.productSyncedOne
				: cfg.i18n.productsSynced.replace('%s', count);
		});
	}

	function applyStatus(s) {
		setProductCount(s.product_count);
		var badge = app.querySelector('[data-lutecia-discovery-badge]');
		if (badge) {
			badge.textContent = s.discovery_ok ? cfg.i18n.activeNow : cfg.i18n.checking;
			badge.classList.toggle('lutecia-badge-live', !!s.discovery_ok);
			badge.classList.toggle('lutecia-badge-checking', !s.discovery_ok);
		}
	}

	var STATUS_RETRIES_MAX = 40; // about five minutes at eight seconds
	var statusRetries = 0;

	function refreshStatus() {
		api('status').then(function (res) {
			if (!res.ok) {
				return;
			}
			var s = res.json;
			if (s.duplicate) {
				var dupExpected = app.querySelector('[data-lutecia-dup-expected]');
				var dupSeen = app.querySelector('[data-lutecia-dup-seen]');
				if (dupExpected) { dupExpected.textContent = s.duplicate.expected || ''; }
				if (dupSeen) { dupSeen.textContent = s.duplicate.seen || ''; }
				show('duplicate');
				return;
			}
			show(s.connected ? 'connected' : 'disconnected');
			applyStatus(s);
			// Re-check on its own until green, for a while: no refresh needed.
			if (s.connected && !s.discovery_ok && statusRetries < STATUS_RETRIES_MAX) {
				statusRetries += 1;
				setTimeout(refreshStatus, 8000);
			}
		}).catch(function () {
			// Network hiccup: the next user action refreshes the status.
		});
	}

	/**
	 * Fresh connect: hold the syncing step until discovery answers, then
	 * land on the channels step. Discovery answering on the store's own
	 * domain is the real "you are online" signal.
	 */
	var syncStartedAt = 0;
	var SYNC_WATCH_MAX_MS = 10 * 60 * 1000;

	function watchFreshSync() {
		if (!syncStartedAt) {
			syncStartedAt = Date.now();
		}
		var hint = app.querySelector('[data-lutecia-sync-hint]');
		if (hint && Date.now() - syncStartedAt > 45000) {
			hint.hidden = false;
		}
		if (Date.now() - syncStartedAt > SYNC_WATCH_MAX_MS) {
			// Still not served after ten minutes: stop polling, the hint
			// stays on screen and the next page load checks again.
			return;
		}
		api('status').then(function (res) {
			if (!res.ok) {
				setTimeout(watchFreshSync, 3000);
				return;
			}
			var s = res.json;
			if (!s.connected) {
				show('disconnected');
				return;
			}
			setProductCount(s.product_count);
			if (s.discovery_ok) {
				syncStartedAt = 0;
				show('connected');
				applyStatus(s);
				loadChannels();
			} else {
				setTimeout(watchFreshSync, 3000);
			}
		}).catch(function () {
			setTimeout(watchFreshSync, 3000);
		});
	}

	var connectBtn = app.querySelector('[data-lutecia-connect]');
	var connectLabel = app.querySelector('[data-lutecia-connect-label]');
	var termsBox = app.querySelector('[data-lutecia-terms]');
	if (termsBox && connectBtn) {
		termsBox.addEventListener('change', function () {
			connectBtn.disabled = !termsBox.checked;
		});
	}
	if (connectBtn) {
		connectBtn.addEventListener('click', function () {
			connectBtn.disabled = true;
			if (connectLabel) {
				connectLabel.textContent = cfg.i18n.connecting;
			}
			var agencyInput = app.querySelector('[data-lutecia-agency]');
			var connectBody = { agency_code: agencyInput ? agencyInput.value.trim() : '' };
			api('connect', 'POST', connectBody).then(function (res) {
				if (res.ok) {
					show('syncing');
					watchFreshSync();
				} else {
					showError(res.json && res.json.message, res.status);
					connectBtn.disabled = termsBox ? !termsBox.checked : false;
					if (connectLabel) {
						connectLabel.textContent = cfg.i18n.connect;
					}
				}
			}).catch(function () {
				showError();
				connectBtn.disabled = termsBox ? !termsBox.checked : false;
				if (connectLabel) {
					connectLabel.textContent = cfg.i18n.connect;
				}
			});
		});
	}

	var resetBtn = app.querySelector('[data-lutecia-reset]');
	if (resetBtn) {
		resetBtn.addEventListener('click', function () {
			if (!window.confirm(cfg.i18n.confirmReset)) {
				return;
			}
			resetBtn.disabled = true;
			api('reset', 'POST').then(function (res) {
				if (res.ok) {
					show('disconnected');
				} else {
					showError(res.json && res.json.message, res.status);
				}
				resetBtn.disabled = false;
			}).catch(function () {
				showError(cfg.i18n.genericError);
				resetBtn.disabled = false;
			});
		});
	}

	var disconnectBtn = app.querySelector('[data-lutecia-disconnect]');
	if (disconnectBtn) {
		disconnectBtn.addEventListener('click', function () {
			if (!window.confirm(cfg.i18n.confirmDisconnect)) {
				return;
			}
			disconnectBtn.disabled = true;
			api('disconnect', 'POST').then(function () {
				show('disconnected');
				disconnectBtn.disabled = false;
				if (connectBtn) {
					connectBtn.disabled = termsBox ? !termsBox.checked : false;
				}
				if (connectLabel) {
					connectLabel.textContent = cfg.i18n.connect;
				}
			}).catch(function () {
				disconnectBtn.disabled = false;
				window.alert(cfg.i18n.genericError);
			});
		});
	}

	function applyChannels(channels) {
		Object.keys(channels || {}).forEach(function (name) {
			var box = app.querySelector('[data-lutecia-channel="' + name + '"]');
			if (box) {
				box.checked = !!channels[name];
				box.disabled = false;
			}
		});
	}

	function markReceived(card, receivedAt) {
		card.classList.add('is-received');
		var text = card.querySelector('[data-lutecia-received-text]');
		text.textContent = receivedAt
			? cfg.i18n.accessReceived.replace('%s', receivedAt)
			: cfg.i18n.accessReceivedNoDate;
		card.querySelector('[data-lutecia-received]').hidden = false;
		card.querySelector('[data-lutecia-access-form]').hidden = true;
	}

	function applySettings(payload) {
		applyChannels(payload.channels);
		var verified = app.querySelector('[data-lutecia-verified]');
		var verifiedLine = app.querySelector('[data-lutecia-verified-line]');
		if (verified && verifiedLine && payload.statuses_verified_on) {
			verified.textContent = payload.statuses_verified_on;
			verifiedLine.hidden = false;
		}
		var catalog = payload.catalog || null;
		var access = payload.channel_access || {};
		app.querySelectorAll('[data-lutecia-access]').forEach(function (card) {
			var key = card.getAttribute('data-lutecia-access');
			if (access[key]) {
				markReceived(card, access[key].received_at);
			}
		});
		// Product data card: catalog completeness per field.
		var dataCard = app.querySelector('[data-lutecia-data-card]');
		if (dataCard && catalog) {
			dataCard.hidden = false;
			var one = catalog.products === 1;
			['brand', 'gtin'].forEach(function (field) {
				var el = app.querySelector('[data-lutecia-data-status="' + field + '"]');
				var missing = catalog['missing_' + field];
				if (missing > 0) {
					var tpl = one ? cfg.i18n.dataMissingOne : cfg.i18n.dataMissing;
					el.textContent = tpl.replace('%1$s', missing).replace('%2$s', catalog.products);
					el.className = 'lutecia-data-status is-missing';
				} else {
					el.textContent = one
						? cfg.i18n.dataCoveredOne
						: cfg.i18n.dataCovered.replace('%s', catalog.products);
					el.className = 'lutecia-data-status is-ok';
				}
			});
		}
	}

	function loadChannels() {
		api('channels').then(function (res) {
			if (res.ok) {
				applySettings(res.json);
			}
		}).catch(function () {
			// Network hiccup: toggles stay disabled until the next load.
		});
	}

	// "I've been accepted": reveal the paste form, send the access to the
	// hub (stored encrypted), then show the received state.
	app.querySelectorAll('[data-lutecia-access]').forEach(function (card) {
		var button = card.querySelector('[data-lutecia-accepted]');
		var form = card.querySelector('[data-lutecia-access-form]');
		var cancel = card.querySelector('[data-lutecia-access-cancel]');
		var errorEl = card.querySelector('[data-lutecia-access-error]');
		if (!button || !form) {
			return;
		}
		var replaceBtn = card.querySelector('[data-lutecia-replace]');
		function openForm() {
			form.hidden = false;
			form.querySelector('textarea').focus();
		}
		button.addEventListener('click', openForm);
		replaceBtn.addEventListener('click', function () {
			card.classList.remove('is-received');
			card.querySelector('[data-lutecia-received]').hidden = true;
			openForm();
		});
		cancel.addEventListener('click', function () {
			form.hidden = true;
			errorEl.hidden = true;
			// A replace attempt was cancelled: restore the received state.
			if (card.querySelector('[data-lutecia-received-text]').textContent) {
				card.classList.add('is-received');
				card.querySelector('[data-lutecia-received]').hidden = false;
			}
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var textarea = form.querySelector('textarea');
			var submit = form.querySelector('[type="submit"]');
			errorEl.hidden = true;
			submit.disabled = true;
			api('channel-access', 'POST', {
				channel: card.getAttribute('data-lutecia-access'),
				access: textarea.value
			}).then(function (res) {
				submit.disabled = false;
				if (res.ok) {
					textarea.value = '';
					markReceived(card, res.json.received_at);
				} else {
					errorEl.textContent = (res.json && res.json.message) || cfg.i18n.genericError;
					errorEl.hidden = false;
				}
			}).catch(function () {
				submit.disabled = false;
				errorEl.textContent = cfg.i18n.genericError;
				errorEl.hidden = false;
			});
		});
	});

	// Confirmations only where a click has real consequences.
	var confirms = {
		'realtime:off': cfg.i18n.confirmRealtimeOff,
		'checkout:on': cfg.i18n.confirmCheckoutOn
	};

	app.querySelectorAll('[data-lutecia-channel]').forEach(function (box) {
		box.addEventListener('change', function () {
			var name = box.getAttribute('data-lutecia-channel');
			var ask = confirms[name + ':' + (box.checked ? 'on' : 'off')];
			if (ask && !window.confirm(ask)) {
				box.checked = !box.checked;
				return;
			}
			box.disabled = true;
			api('channels', 'POST', { channel: name, enabled: box.checked }).then(function (res) {
				if (res.ok) {
					applyChannels(res.json.channels);
				} else {
					box.checked = !box.checked;
					box.disabled = false;
					window.alert((res.json && res.json.message) || cfg.i18n.genericError);
				}
			}).catch(function () {
				box.checked = !box.checked;
				box.disabled = false;
			});
		});
	});

	var agencyToggle = app.querySelector('[data-lutecia-agency-toggle]');
	if (agencyToggle) {
		agencyToggle.addEventListener('click', function () {
			var field = app.querySelector('[data-lutecia-agency-field]');
			field.hidden = false;
			agencyToggle.setAttribute('aria-expanded', 'true');
			agencyToggle.parentNode.hidden = true;
			var input = field.querySelector('input');
			if (input) { input.focus(); }
		});
	}

	if (app.getAttribute('data-connected') === '1') {
		refreshStatus();
		loadChannels();
	}
})();
