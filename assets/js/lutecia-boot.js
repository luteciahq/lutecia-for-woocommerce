/**
 * Opens the screen the page was rendered on, then loads the store's status
 * and the hub settings. Loaded last: every other script is in place.
 */
(function (L) {
	'use strict';
	if (L.off) {
		return;
	}

	var app = L.app;
	try {
		L.state.wizard = JSON.parse(app.getAttribute('data-lutecia-wizard') || '{}');
	} catch (e) {
		L.state.wizard = {};
	}
	var connected = app.getAttribute('data-connected') === '1' && app.getAttribute('data-duplicate') !== '1';
	var screen = app.getAttribute('data-lutecia-current');

	if (connected && L.stripeReturned()) {
		// Back from Stripe: the Stripe step shows the outcome (Home does,
		// once the settings say the hub no longer offers Stripe).
		L.openSetup(3);
	} else if (screen === 'setup') {
		L.openSetup(L.state.wizard.step || 1);
	} else {
		L.showScreen(screen);
	}

	if (connected) {
		L.watchSync();
		L.loadSettings().catch(function () {});
	}
})(window.luteciaScreen);
