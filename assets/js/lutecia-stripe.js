/**
 * The Stripe step: Account, then Agentic commerce, one block open at a
 * time. Both are done on what the hub detects: the account connected, the
 * catalog accepted by Stripe (agentic_enabled). While Stripe still has to
 * answer, the step re-reads the status every 20 seconds.
 */
(function (L) {
	'use strict';
	if (L.off) {
		return;
	}

	var cfg = L.cfg;
	var S = cfg.stripe || {};
	var AUTHORIZE_PREFIX = 'https://connect.stripe.com/oauth/authorize?';
	var SEND_AGAIN_PAUSE_MS = 60000;
	var POLL_MS = 20000;
	var POLL_MAX = 90;
	// "Change", or a failed return from Stripe, reopens the Account block
	// until the page reloads: a status payload never closes it.
	var reopened = false;
	var stripeReturn = null;
	var lastAgentic = null;
	var polls = 0;
	var pollTimer = 0;

	var connectBtn = L.q('[data-lutecia-stripe-connect]');
	var connectError = L.q('[data-lutecia-stripe-error]');
	var sendAgain = L.q('[data-lutecia-send-again]');
	var next = L.q('[data-lutecia-stripe-next]');

	function stripeState() {
		return (L.state.settings && L.state.settings.stripe_acs) || {};
	}

	/** Whether the hub offers the Stripe channel. */
	L.stripeAvailable = function () {
		return !!stripeState().available;
	};

	/** Whether both blocks are done: account connected, catalog accepted. */
	L.stripeDone = function () {
		var stripe = stripeState();
		return !!(stripe.available && stripe.connected && stripe.agentic_enabled === true);
	};

	/**
	 * The outcome of the return from Stripe's connection page ({ok, text}),
	 * null when the page did not come back from Stripe.
	 */
	L.stripeReturned = function () {
		return stripeReturn;
	};

	function block(name) {
		return L.q('[data-lutecia-blk="' + name + '"]');
	}

	function focusCurrent() {
		var current = L.q('[data-lutecia-blk][data-state="current"]');
		var target = current && L.q('.lutecia-blk-content a, .lutecia-blk-content button', current);
		if (target) {
			target.focus();
		} else if (!next.disabled) {
			next.focus();
		}
	}

	/** Paints both blocks from the hub status. */
	function paint(moveFocus) {
		var stripe = stripeState();
		var accountDone = !!stripe.connected && !reopened;
		var agenticDone = stripe.agentic_enabled === true;
		block('account').setAttribute('data-state', accountDone ? 'done' : 'current');
		block('agentic').setAttribute('data-state', !accountDone ? 'later' : (agenticDone ? 'done' : 'current'));
		L.q('[data-lutecia-reopen]').hidden = !accountDone;
		var nameEl = L.q('[data-lutecia-account-name]');
		nameEl.textContent = stripe.account_name || '';
		nameEl.hidden = !(accountDone && stripe.account_name);
		L.q('[data-lutecia-stripe-wait]').hidden = !(accountDone && !agenticDone && stripe.agentic_enabled !== false);
		L.q('[data-lutecia-activation]').hidden = !(stripe.connected && stripe.account_ready === false);
		L.q('[data-lutecia-agentic-off]').hidden = !(stripe.connected && stripe.agentic_enabled === false);
		next.disabled = !L.stripeDone();
		L.emit('progress');
		if (moveFocus) {
			focusCurrent();
		}
	}

	/** Says "Catalog accepted by Stripe." when it turns done during the visit. */
	function announce(stripe) {
		if (!stripe.available) {
			return;
		}
		var accepted = stripe.agentic_enabled === true;
		if (lastAgentic === false && accepted) {
			L.say(S.catalog_accepted);
		}
		lastAgentic = accepted;
	}

	function waiting(stripe) {
		return !!(stripe.available && stripe.connected && (stripe.account_ready === false || stripe.agentic_enabled !== true));
	}

	function onStep() {
		return !document.hidden && L.app.getAttribute('data-lutecia-current') === 'setup' && L.currentStep === 3;
	}

	// Back from Stripe's tab: read the status at once instead of waiting for the next poll.
	document.addEventListener('visibilitychange', function () {
		if (onStep() && waiting(stripeState())) {
			L.loadSettings().catch(function () {});
		}
	});

	function schedulePoll() {
		clearTimeout(pollTimer);
		pollTimer = 0;
		if (!waiting(stripeState()) || polls >= POLL_MAX) {
			return;
		}
		pollTimer = setTimeout(function () {
			if (!onStep()) {
				// Hidden tab or another step: wait, without spending a poll.
				schedulePoll();
				return;
			}
			polls += 1;
			L.loadSettings().then(function (res) {
				if (!res.ok) {
					schedulePoll();
				}
			}).catch(schedulePoll);
		}, POLL_MS);
	}

	// Back from Stripe: keep the outcome, then clean the address bar so a
	// reload does not show it again.
	function readStripeReturn() {
		var params = new URLSearchParams(window.location.search);
		var flag = params.get('lutecia_stripe');
		if (!flag) {
			return;
		}
		var reason = params.get('lutecia_stripe_reason') || '';
		stripeReturn = flag === 'connected'
			? { ok: true, text: S.returned_connected }
			: { ok: false, text: S['error_' + reason] || cfg.i18n.genericError };
		params.delete('lutecia_stripe');
		params.delete('lutecia_stripe_reason');
		var query = params.toString();
		window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
	}

	function showReturn() {
		if (!stripeReturn) {
			return;
		}
		if (stripeReturn.ok) {
			L.say(stripeReturn.text);
			return;
		}
		reopened = true;
		connectError.textContent = stripeReturn.text;
		connectError.hidden = false;
	}

	function connectFailed(message) {
		connectError.textContent = message;
		connectError.hidden = false;
		connectBtn.disabled = false;
		connectBtn.textContent = S.connect;
		connectBtn.focus();
	}

	connectBtn.addEventListener('click', function () {
		stripeReturn = null;
		connectError.hidden = true;
		connectBtn.disabled = true;
		connectBtn.textContent = S.connecting;
		L.api('stripe/connect', 'POST').then(function (res) {
			var url = res.ok && res.json && res.json.url ? String(res.json.url) : '';
			// Only Stripe's own authorize page is ever opened from here.
			if (url.indexOf(AUTHORIZE_PREFIX) === 0) {
				window.location.assign(url);
				return;
			}
			connectFailed(L.failure(res));
		}).catch(function () {
			connectFailed(cfg.i18n.genericError);
		});
	});

	L.q('[data-lutecia-reopen]').addEventListener('click', function () {
		reopened = true;
		paint(true);
	});

	sendAgain.addEventListener('click', function () {
		sendAgain.disabled = true;
		// One click a minute at most: the hub also refuses while an import is pending.
		setTimeout(function () {
			sendAgain.disabled = false;
		}, SEND_AGAIN_PAUSE_MS);
		L.api('stripe/retry-import', 'POST').then(function (res) {
			if (!res.ok) {
				// Nothing started (409: an import is pending, or Stripe refused
				// one a moment ago): the hub's sentence, and no new polling.
				L.notice(sendAgain, L.failure(res));
				return;
			}
			// A new import is on its way: a fresh round of polling for its outcome.
			polls = 0;
			schedulePoll();
		}).catch(function () {
			L.notice(sendAgain, cfg.i18n.genericError);
		});
	});

	next.addEventListener('click', function () {
		L.openSetup(4);
	});

	L.q('[data-lutecia-finish-later]').addEventListener('click', function () {
		L.finishLater();
	});

	L.on('settings', function (payload) {
		var stripe = payload.stripe_acs || {};
		if (stripeReturn && !stripe.available && L.currentStep === 3) {
			// Back from Stripe, but the hub no longer offers it: the Stripe
			// step has nothing to show. Home says how the return went.
			var outcome = stripeReturn;
			stripeReturn = null;
			L.finishLater();
			L.emit('stripe-return', outcome);
			return;
		}
		announce(stripe);
		paint(false);
		schedulePoll();
	});

	readStripeReturn();
	showReturn();
	paint(false);
})(window.luteciaScreen);
