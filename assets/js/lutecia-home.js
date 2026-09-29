/**
 * Home screen: the Stripe banner, the outcome of a return
 * from Stripe when the hub no longer offers it, the Stripe list with the
 * refused products, Channels (Stripe's agents, Discovery, the
 * programs with the access paste), the four Controls switches, Settings and
 * the side nav.
 */
(function (L) {
	'use strict';
	if (L.off) {
		return;
	}

	var cfg = L.cfg;
	var S = cfg.stripe || {};
	var T = cfg.screen || {};
	// Confirmations only where a click has real consequences (the old screen's two).
	var confirms = {
		'realtime:off': cfg.i18n.confirmRealtimeOff,
		'checkout:on': cfg.i18n.confirmCheckoutOn
	};

	function stripeState() {
		return (L.state.settings && L.state.settings.stripe_acs) || {};
	}

	function money(cents, currency) {
		var code = String(currency || cfg.storeCurrency || 'USD').toUpperCase();
		try {
			return new Intl.NumberFormat(document.documentElement.lang || undefined, { style: 'currency', currency: code }).format(cents / 100);
		} catch (e) {
			return (cents / 100).toFixed(2) + ' ' + code;
		}
	}

	function paintBanner() {
		var open = L.stripeAvailable() && (!L.stripeDone() || !L.state.wizard.assistants_done);
		L.q('[data-lutecia-banner]').hidden = !open;
	}

	function paintDiscovery() {
		var live = !!(L.state.status && L.state.status.discovery_ok);
		L.q('[data-lutecia-discovery]').classList.toggle('is-ok', live);
	}

	function paintRefusals(refusals) {
		var list = L.q('[data-lutecia-refusals]');
		list.textContent = '';
		refusals.forEach(function (r) {
			var item = document.createElement('li');
			var known = S['refusal_' + r.field];
			if (r.edit) {
				var link = document.createElement('a');
				link.href = r.edit;
				link.textContent = r.title || r.edit;
				item.appendChild(link);
			} else {
				item.appendChild(document.createTextNode(r.title || ''));
			}
			item.appendChild(document.createTextNode((r.title || r.edit ? ': ' : '') + (known || S.refusal_other)));
			if (!known && r.reason) {
				// Stripe's own words, in brackets: nothing is shown on hover only.
				var why = document.createElement('span');
				why.className = 'lutecia-muted';
				why.textContent = ' (' + r.reason + ')';
				item.appendChild(why);
			}
			list.appendChild(item);
		});
	}

	function paintStripe() {
		var stripe = stripeState();
		var connected = !!(stripe.available && stripe.connected);
		var refused = connected ? (stripe.products_refused || 0) : 0;
		var notSent = L.q('[data-lutecia-not-sent]');
		L.q('[data-lutecia-stripe-section]').hidden = !connected;
		L.q('[data-lutecia-stripe-settings]').hidden = !connected;
		var stripeDot = L.q('[data-lutecia-stripe-dot]');
		stripeDot.classList.toggle('is-ok', L.stripeDone());
		stripeDot.classList.toggle('is-warn', connected && !L.stripeDone());
		notSent.textContent = S.not_sent.replace('%s', refused);
		notSent.hidden = !refused;
		if (!refused) {
			notSent.setAttribute('aria-expanded', 'false');
			L.q('[data-lutecia-refused]').hidden = true;
		}
		paintRefusals(connected ? (stripe.refusals || []) : []);
	}

	function applyChannels(channels) {
		Object.keys(channels || {}).forEach(function (name) {
			var sw = L.q('[data-lutecia-channel="' + name + '"]');
			if (sw) {
				sw.setAttribute('aria-checked', String(!!channels[name]));
				sw.disabled = false;
			}
		});
	}

	/** The access was handed over: the received sentence, visible, and the Accepted chip. */
	/** Credentials received: a green dot, and a link to send others. */
	function markReceived(row) {
		L.q('[data-lutecia-received]', row).hidden = false;
		L.q('[data-lutecia-apply]', row).hidden = true;
		L.q('[data-lutecia-accepted]', row).hidden = true;
		L.q('[data-lutecia-replace]', row).hidden = false;
		L.q('[data-lutecia-access-form]', row).hidden = true;
	}

	function paintSettings(payload) {
		applyChannels(payload.channels);
		var access = payload.channel_access || {};
		L.qa('[data-lutecia-access]').forEach(function (row) {
			var key = row.getAttribute('data-lutecia-access');
			if (access[key]) {
				markReceived(row);
			}
		});
		paintStripe();
		paintBanner();
	}

	// Side nav: scroll to the section and mark the link.
	var navLinks = L.qa('[data-lutecia-nav]');
	navLinks.forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			navLinks.forEach(function (other) {
				other.classList.toggle('is-on', other === link);
				if (other === link) {
					other.setAttribute('aria-current', 'true');
				} else {
					other.removeAttribute('aria-current');
				}
			});
			var target = document.getElementById(link.getAttribute('data-lutecia-nav'));
			if (target) {
				target.scrollIntoView({ block: 'start', behavior: 'smooth' });
				target.focus({ preventScroll: true });
			}
		});
	});

	L.q('[data-lutecia-banner-go]').addEventListener('click', function () {
		L.openSetup(L.resumeStep());
	});

	// Rows that unfold (Discovery, each program): the name is the control.
	L.qa('[data-lutecia-unfold]').forEach(function (row) {
		var control = L.q('.lutecia-unfold', row);
		control.addEventListener('click', function () {
			var open = control.getAttribute('aria-expanded') !== 'true';
			control.setAttribute('aria-expanded', String(open));
			row.classList.toggle('is-open', open);
		});
	});

	var notSentChip = L.q('[data-lutecia-not-sent]');
	notSentChip.addEventListener('click', function () {
		var open = notSentChip.getAttribute('aria-expanded') !== 'true';
		notSentChip.setAttribute('aria-expanded', String(open));
		L.q('[data-lutecia-refused]').hidden = !open;
	});

	// Switches of Controls.
	L.qa('[data-lutecia-channel]').forEach(function (sw) {
		sw.addEventListener('click', function () {
			var name = sw.getAttribute('data-lutecia-channel');
			var enable = sw.getAttribute('aria-checked') !== 'true';
			var ask = confirms[name + ':' + (enable ? 'on' : 'off')];
			var go = ask ? L.confirm(sw, ask, enable ? cfg.i18n.turnOn : cfg.i18n.turnOff, !enable) : Promise.resolve(true);
			go.then(function (yes) {
				if (!yes) {
					return;
				}
				sw.disabled = true;
				L.api('channels', 'POST', { channel: name, enabled: enable }).then(function (res) {
					sw.disabled = false;
					if (res.ok) {
						applyChannels(res.json.channels);
					} else {
						L.notice(sw, L.failure(res));
					}
				}).catch(function () {
					sw.disabled = false;
					L.notice(sw, cfg.i18n.genericError);
				});
			});
		});
	});

	// "I've been accepted": the paste form under the row, sent to the hub
	// (stored encrypted), then the Accepted chip.
	L.qa('[data-lutecia-access]').forEach(function (row) {
		var form = L.q('[data-lutecia-access-form]', row);
		var textarea = L.q('textarea', form);
		var errorEl = L.q('[data-lutecia-access-error]', row);

		function openForm() {
			form.hidden = false;
			textarea.focus();
		}

		L.q('[data-lutecia-accepted]', row).addEventListener('click', openForm);
		L.q('[data-lutecia-replace]', row).addEventListener('click', openForm);
		L.q('[data-lutecia-access-cancel]', row).addEventListener('click', function () {
			form.hidden = true;
			errorEl.hidden = true;
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var submit = L.q('[type="submit"]', form);
			errorEl.hidden = true;
			submit.disabled = true;
			L.api('channel-access', 'POST', {
				channel: row.getAttribute('data-lutecia-access'),
				access: textarea.value
			}).then(function (res) {
				submit.disabled = false;
				if (res.ok) {
					textarea.value = '';
					markReceived(row);
				} else {
					errorEl.textContent = L.failure(res);
					errorEl.hidden = false;
				}
			}).catch(function () {
				submit.disabled = false;
				errorEl.textContent = cfg.i18n.genericError;
				errorEl.hidden = false;
			});
		});
	});

	var stripeDisconnect = L.q('[data-lutecia-stripe-disconnect]');
	stripeDisconnect.addEventListener('click', function () {
		L.confirm(stripeDisconnect, S.confirm_disconnect, S.disconnect, true).then(function (yes) {
			if (!yes) {
				return;
			}
			stripeDisconnect.disabled = true;
			L.api('stripe/disconnect', 'POST').then(function (res) {
				stripeDisconnect.disabled = false;
				if (!res.ok) {
					L.notice(stripeDisconnect, L.failure(res));
					return;
				}
				L.state.wizard.assistants_done = false;
				L.state.settings = res.json;
				L.emit('settings', res.json);
			}).catch(function () {
				stripeDisconnect.disabled = false;
				L.notice(stripeDisconnect, cfg.i18n.genericError);
			});
		});
	});

	var disconnectBtn = L.q('[data-lutecia-disconnect]');
	disconnectBtn.addEventListener('click', function () {
		L.confirm(disconnectBtn, cfg.i18n.confirmDisconnect, disconnectBtn.textContent, true).then(function (yes) {
			if (!yes) {
				return;
			}
			disconnectBtn.disabled = true;
			L.api('disconnect', 'POST').then(function (res) {
				disconnectBtn.disabled = false;
				if (!res.ok) {
					L.notice(disconnectBtn, L.failure(res));
					return;
				}
				L.app.setAttribute('data-connected', '0');
				L.state.status = null;
				L.state.settings = null;
				L.state.wizard = { step: 0, assistants_done: false };
				L.showWelcome();
			}).catch(function () {
				disconnectBtn.disabled = false;
				L.notice(disconnectBtn, cfg.i18n.genericError);
			});
		});
	});

	L.on('settings', paintSettings);
	// Back from Stripe to a hub that no longer offers it (lutecia-stripe.js):
	// the outcome of the return, shown on Home.
	L.on('stripe-return', function (outcome) {
		var line = L.q('[data-lutecia-stripe-return]');
		line.textContent = outcome.text;
		line.classList.toggle('lutecia-error', !outcome.ok);
		line.hidden = false;
	});
	L.on('status', function () {
		paintDiscovery();
		paintBanner();
	});
	L.on('progress', function () {
		paintBanner();
	});
	L.on('screen', function (name) {
		if (name === 'home') {
			paintBanner();
		}
	});
})(window.luteciaScreen);
