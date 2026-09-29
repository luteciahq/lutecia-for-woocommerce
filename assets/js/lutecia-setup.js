/**
 * Setup screen: the rail and the Store, Catalog and Assistants steps (the
 * Stripe step is lutecia-stripe.js). The step on screen is saved in
 * WordPress options, so the merchant comes back where they left off.
 */
(function (L) {
	'use strict';
	if (L.off) {
		return;
	}

	var T = L.cfg.screen || {};
	var HINT_AFTER_MS = 45000;
	var LAST_STEP = 4;
	var CATALOG_POLL_MS = 4000;
	var CATALOG_POLL_MAX = 150;
	var sawUnsynced = false;
	var catalogPolls = 0;
	var catalogTimer = null;
	var catalogLoading = false;
	L.currentStep = 0;

	function synced() {
		return !!(L.state.status && L.state.status.discovery_ok);
	}

	/**
	 * The store answers and the hub has read its catalog: before the first
	 * sync lands, the hub's counts are computed on zero products.
	 */
	function catalogReady() {
		var catalog = L.state.settings && L.state.settings.catalog;
		return synced() && !!(catalog && catalog.synced);
	}

	/**
	 * While the Catalog step is on screen and its first sync has not landed,
	 * reads the hub settings again every few seconds, a bounded number of
	 * times. Stops once the catalog is ready or the step is left.
	 */
	function watchCatalog() {
		var waiting = L.currentStep === 2 && !catalogReady() && catalogPolls < CATALOG_POLL_MAX;
		if (!waiting) {
			if (catalogTimer) {
				window.clearTimeout(catalogTimer);
				catalogTimer = null;
			}
			return;
		}
		if (catalogTimer || catalogLoading) {
			return;
		}
		catalogTimer = window.setTimeout(function () {
			catalogTimer = null;
			if (L.currentStep !== 2 || catalogReady()) {
				return;
			}
			catalogPolls += 1;
			catalogLoading = true;
			L.loadSettings().catch(function () {}).then(function () {
				catalogLoading = false;
				watchCatalog();
			});
		}, CATALOG_POLL_MS);
	}

	function stepDone(n) {
		if (n === 1) {
			return L.app.getAttribute('data-connected') === '1';
		}
		if (n === 2) {
			return catalogReady();
		}
		if (n === 3) {
			return L.stripeDone();
		}
		return !!L.state.wizard.assistants_done;
	}

	function canOpen(n) {
		if (n <= 2 || n === L.currentStep) {
			return true;
		}
		return n === 3 ? catalogReady() : L.stripeDone();
	}

	/** Ticks the done steps; later steps wait until the ones before are done. */
	function paintRail() {
		var stripeShown = L.stripeAvailable();
		L.qa('[data-lutecia-rail-step]').forEach(function (li) {
			var n = Number(li.getAttribute('data-lutecia-rail-step'));
			var button = L.q('button', li);
			li.hidden = n > 2 && !stripeShown;
			li.classList.toggle('is-current', n === L.currentStep);
			li.classList.toggle('is-done', stepDone(n));
			button.disabled = !canOpen(n);
			if (n === L.currentStep) {
				button.setAttribute('aria-current', 'step');
			} else {
				button.removeAttribute('aria-current');
			}
		});
	}

	/**
	 * Product count, sync progress and catalog readiness of the Catalog step:
	 * brand and GTIN for the programs, with where to fill each one while some
	 * products miss it; barcode or SKU for Stripe, where the hub offers it.
	 */
	function paintCatalog() {
		var s = L.state.status;
		if (s) {
			L.q('[data-lutecia-product-total]').textContent = L.count(s.product_count, T.products, T.products_one);
		}
		var isSynced = catalogReady();
		L.q('[data-lutecia-syncing]').hidden = isSynced;
		L.q('[data-lutecia-sync-hint]').hidden = isSynced || L.syncElapsed() < HINT_AFTER_MS;
		var catalog = L.state.settings && L.state.settings.catalog;
		L.q('[data-lutecia-readiness]').hidden = !isSynced || !catalog;
		if (catalog) {
			[
				['brand', catalog.missing_brand],
				['gtin', catalog.missing_gtin],
				['identifier', catalog.missing_identifier]
			].forEach(function (pair) {
				var chip = L.q('[data-lutecia-ready="' + pair[0] + '"]');
				var help = L.q('[data-lutecia-ready-help="' + pair[0] + '"]');
				var missing = Number(pair[1] || 0);
				chip.textContent = L.ofTotal(catalog.products - missing, catalog.products);
				chip.classList.toggle('is-ok', missing === 0);
				if (help) {
					help.hidden = missing === 0;
				}
			});
			L.q('[data-lutecia-ready-row="identifier"]').hidden = !L.stripeAvailable();
		}
		L.q('[data-lutecia-catalog-next]').disabled = !isSynced;
		paintRail();
		watchCatalog();
	}

	/** After Catalog: the Stripe step where the hub offers Stripe, else Home. */
	function afterCatalog() {
		if (L.stripeAvailable()) {
			L.openSetup(3);
		} else {
			L.finishLater();
		}
	}

	/** Opens the setup screen on step n and remembers it. */
	L.openSetup = function (n) {
		L.currentStep = n;
		L.qa('[data-lutecia-step]').forEach(function (el) {
			el.hidden = Number(el.getAttribute('data-lutecia-step')) !== n;
		});
		if (L.app.getAttribute('data-lutecia-current') !== 'setup') {
			L.showScreen('setup');
		}
		paintRail();
		var heading = L.q('[data-lutecia-step="' + n + '"] h2');
		if (heading) {
			heading.focus();
		}
		if (L.state.wizard.step !== n) {
			L.saveWizard({ step: n }).catch(function () {});
		}
		L.emit('progress');
	};

	/** Leaves the wizard for Home; the banner brings the merchant back. */
	L.finishLater = function () {
		L.currentStep = 0;
		L.saveWizard({ step: 0 }).catch(function () {});
		L.showScreen('home');
	};

	/** First step still to do, for the banner's Continue. */
	L.resumeStep = function () {
		for (var n = 1; n <= LAST_STEP; n += 1) {
			if (!stepDone(n)) {
				return n;
			}
		}
		return LAST_STEP;
	};

	L.qa('[data-lutecia-rail-go]').forEach(function (button) {
		button.addEventListener('click', function () {
			L.openSetup(Number(button.getAttribute('data-lutecia-rail-go')));
		});
	});

	L.qa('[data-lutecia-goto]').forEach(function (button) {
		button.addEventListener('click', function () {
			L.openSetup(Number(button.getAttribute('data-lutecia-goto')));
		});
	});

	var catalogNext = L.q('[data-lutecia-catalog-next]');
	catalogNext.addEventListener('click', function () {
		// Whether Stripe comes next is the hub's answer: never chosen before
		// its settings are read. Not read yet (first read still running, or
		// it failed): read them once more, then choose.
		if (L.state.settings) {
			afterCatalog();
			return;
		}
		catalogNext.disabled = true;
		L.loadSettings().catch(function () {}).then(function () {
			catalogNext.disabled = false;
			afterCatalog();
		});
	});

	L.q('[data-lutecia-assistants-done]').addEventListener('click', function () {
		L.currentStep = 0;
		L.saveWizard({ assistants_done: true, step: 0 }).catch(function () {});
		L.showScreen('home');
	});

	L.on('status', function (s) {
		// The sync just finished: the hub now knows the catalog's readiness.
		if (!s.discovery_ok) {
			sawUnsynced = true;
		} else if (sawUnsynced) {
			sawUnsynced = false;
			L.loadSettings().catch(function () {});
		}
		paintCatalog();
	});
	L.on('settings', paintCatalog);
	L.on('progress', function () {
		paintRail();
		watchCatalog();
	});
	L.on('screen', watchCatalog);
})(window.luteciaScreen);
