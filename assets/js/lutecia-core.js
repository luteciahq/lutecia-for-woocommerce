/**
 * Shared ground of the Lutecia screen: REST calls, events, screens, the
 * live region, counts, the sync watch and the wizard state. No sentence is
 * ever put in a title attribute by a script: what the merchant must read
 * is visible text. The other scripts add their part to
 * window.luteciaScreen and load after this one.
 * Vanilla JS on purpose: no build step, no dependency, trivially auditable.
 */
(function () {
	'use strict';

	var cfg = window.luteciaAdmin || {};
	var T = cfg.screen || {};
	var app = document.getElementById('lutecia-app');
	var L = window.luteciaScreen = {
		cfg: cfg,
		app: app,
		state: { status: null, settings: null, wizard: {} }
	};
	if (!app || !cfg.restUrl) {
		L.off = true;
		return;
	}

	var SYNC_POLL_MS = 3000;
	var SYNC_WATCH_MAX_MS = 10 * 60 * 1000;
	var handlers = {};
	var syncStartedAt = 0;

	/** Subscribes to an event: status, settings, screen, progress, stripe-return. */
	L.on = function (name, fn) {
		(handlers[name] = handlers[name] || []).push(fn);
	};

	/** Calls every subscriber of an event with its data. */
	L.emit = function (name, data) {
		(handlers[name] || []).forEach(function (fn) {
			fn(data);
		});
	};

	/** First element matching the selector, inside the screen or `root`. */
	L.q = function (selector, root) {
		return (root || app).querySelector(selector);
	};

	/** Every element matching the selector, as an array. */
	L.qa = function (selector, root) {
		return Array.prototype.slice.call((root || app).querySelectorAll(selector));
	};

	/** A call to the plugin's REST routes, answered as {ok, status, json}. */
	L.api = function (path, method, body) {
		var init = {
			method: method || 'GET',
			headers: { 'X-WP-Nonce': cfg.restNonce, 'Content-Type': 'application/json' },
			credentials: 'same-origin'
		};
		if (body) {
			init.body = JSON.stringify(body);
		}
		return fetch(cfg.restUrl + path, init).then(function (res) {
			return res.json().catch(function () {
				return {};
			}).then(function (json) {
				return { ok: res.ok, status: res.status, json: json };
			});
		});
	};

	/** The sentence shown for a failed call. */
	L.failure = function (res) {
		if (res && (res.status === 401 || res.status === 403)) {
			return cfg.i18n.sessionExpired;
		}
		return (res && res.json && res.json.message) || cfg.i18n.genericError;
	};

	var live = document.createElement('div');
	live.className = 'lutecia-sr';
	live.setAttribute('aria-live', 'polite');
	app.appendChild(live);

	/** Reads a sentence out to screen readers. */
	L.say = function (text) {
		live.textContent = '';
		setTimeout(function () {
			live.textContent = text;
		}, 30);
	};

	/** The block a panel is placed in: the list row of the control, else its parent. */
	function blockOf(el) {
		return el.closest('li') || el.parentNode;
	}

	var pendingConfirm = null;

	/** Removes every panel; a confirmation still open is answered no. */
	function closePanels() {
		if (pendingConfirm) {
			var cancel = pendingConfirm;
			pendingConfirm = null;
			cancel();
		}
		L.qa('[data-lutecia-panel]').forEach(function (el) {
			el.parentNode.removeChild(el);
		});
	}

	/**
	 * Asks the merchant to confirm an action, in the page, under the control:
	 * the question, a button with the action's own words (red when `danger`),
	 * Cancel. Resolves true or false. Never a browser dialog.
	 */
	L.confirm = function (control, question, label, danger) {
		closePanels();
		return new Promise(function (resolve) {
			var panel = document.createElement('div');
			panel.className = 'lutecia-confirm';
			panel.setAttribute('data-lutecia-panel', '');
			panel.setAttribute('role', 'group');
			var text = document.createElement('p');
			text.textContent = question;
			var yes = document.createElement('button');
			yes.type = 'button';
			yes.className = 'lutecia-btn lutecia-btn-small' + (danger ? ' lutecia-btn-danger' : '');
			yes.textContent = label;
			var no = document.createElement('button');
			no.type = 'button';
			no.className = 'lutecia-btn lutecia-btn-small lutecia-btn-ghost';
			no.textContent = cfg.i18n.cancel;
			var actions = document.createElement('div');
			actions.className = 'lutecia-confirm-actions';
			actions.appendChild(yes);
			actions.appendChild(no);
			panel.appendChild(text);
			panel.appendChild(actions);
			function done(answer) {
				pendingConfirm = null;
				document.removeEventListener('keydown', onKey);
				if (panel.parentNode) {
					panel.parentNode.removeChild(panel);
				}
				if (!answer) {
					control.focus();
				}
				resolve(answer);
			}
			function onKey(e) {
				if (e.key === 'Escape') {
					done(false);
				}
			}
			yes.addEventListener('click', function () { done(true); });
			no.addEventListener('click', function () { done(false); });
			document.addEventListener('keydown', onKey);
			pendingConfirm = function () { done(false); };
			blockOf(control).appendChild(panel);
			yes.focus();
		});
	};

	/** A failure sentence under the control, in the page, read out loud. */
	L.notice = function (control, message) {
		closePanels();
		var line = document.createElement('p');
		line.className = 'lutecia-error lutecia-notice';
		line.setAttribute('data-lutecia-panel', '');
		line.setAttribute('role', 'alert');
		line.textContent = message;
		blockOf(control).appendChild(line);
	};

	/** Shows one screen (welcome, setup or home) and hides the others. */
	L.showScreen = function (name) {
		L.qa('[data-lutecia-screen]').forEach(function (el) {
			el.hidden = el.getAttribute('data-lutecia-screen') !== name;
		});
		app.setAttribute('data-lutecia-current', name);
		window.scrollTo(0, 0);
		L.emit('screen', name);
	};

	/** "1 product" or "24 products", from the two sentences of a count. */
	L.count = function (n, many, one) {
		return n === 1 ? one : many.replace('%s', n);
	};

	/** "21 of 24". */
	L.ofTotal = function (part, total) {
		return T.part_of_total.replace('%1$s', part).replace('%2$s', total);
	};

	/** Reads the store's status (connection, discovery, product count). */
	L.loadStatus = function () {
		return L.api('status').then(function (res) {
			if (res.ok) {
				L.state.status = res.json;
				L.emit('status', res.json);
			}
			return res;
		});
	};

	/** Reads the hub settings (channels, catalog, access, Stripe). */
	L.loadSettings = function () {
		return L.api('channels').then(function (res) {
			if (res.ok) {
				L.state.settings = res.json;
				L.emit('settings', res.json);
			}
			return res;
		});
	};

	/** Saves part of the wizard state; the answer is the saved state. */
	L.saveWizard = function (patch) {
		Object.keys(patch).forEach(function (key) {
			L.state.wizard[key] = patch[key];
		});
		return L.api('wizard', 'POST', patch).then(function (res) {
			if (res.ok) {
				L.state.wizard = res.json;
			}
			return res;
		});
	};

	/** Milliseconds since the sync watch started, 0 when it is not running. */
	L.syncElapsed = function () {
		return syncStartedAt ? Date.now() - syncStartedAt : 0;
	};

	/**
	 * Re-reads the status every 3 seconds until the store answers AI
	 * assistants on its own address, ten minutes at most per page load.
	 */
	L.watchSync = function () {
		if (!syncStartedAt) {
			syncStartedAt = Date.now();
		}
		function again() {
			if (Date.now() - syncStartedAt < SYNC_WATCH_MAX_MS) {
				setTimeout(L.watchSync, SYNC_POLL_MS);
			}
		}
		L.loadStatus().then(function (res) {
			var s = res.ok ? res.json : null;
			if (s && (s.duplicate || !s.connected || s.discovery_ok)) {
				syncStartedAt = 0;
				return;
			}
			again();
		}).catch(again);
	};
})();
