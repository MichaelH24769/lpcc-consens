/**
 * LP-CC Consent – Frontend
 *
 * Ablauf:
 *   1. Cookie lesen. Gültig -> Consent anwenden, fertig (kein Netz-Request).
 *   2. Kein Cookie -> GET /wp-json/lpcc/v1/geo (uncached).
 *        region "row" -> alles erlauben, Cookie setzen, kein Banner.
 *        region "eu"  -> Banner zeigen, Scripts bleiben blockiert.
 *   3. Aktivierung: <script type="text/plain" data-lpcc="cat"> wird zu
 *      echtem Script; <iframe data-src data-lpcc="cat"> bekommt src.
 *      Nicht freigegebene iframes erhalten einen 2-Klick-Platzhalter.
 *
 * Public API: window.lpcc.open() / .get() / .set({statistics,marketing})
 */
(function () {
	'use strict';

	var cfg = window.lpccCfg || {};
	var COOKIE = cfg.cookie || 'lpcc_consent';
	var VERSION = cfg.version || 1;
	var T = cfg.texts || {};
	var DAYS = 180;

	var state = null; // {v, region, statistics, marketing}
	var loaded = { statistics: false, marketing: false }; // was in dieser Seite bereits ausgeführt wurde

	// ------------------------------------------------------------------ Cookie

	function readCookie() {
		var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE + '=([^;]*)'));
		if (!m) return null;
		try {
			var data = JSON.parse(decodeURIComponent(m[1]));
			if (data && data.v === VERSION) return data;
		} catch (e) { /* kaputtes Cookie ignorieren */ }
		return null;
	}

	function writeCookie(data) {
		var d = new Date();
		d.setTime(d.getTime() + DAYS * 864e5);
		document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(data)) +
			'; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' +
			(location.protocol === 'https:' ? '; Secure' : '');
	}

	// ------------------------------------------------------------- Aktivierung

	function activateScripts(cat) {
		var nodes = document.querySelectorAll('script[type="text/plain"][data-lpcc="' + cat + '"]');
		nodes.forEach(function (old) {
			var s = document.createElement('script');
			var src = old.getAttribute('data-lpcc-src');
			if (src) {
				s.src = src;
				s.async = true;
			} else {
				s.textContent = old.textContent;
			}
			old.parentNode.replaceChild(s, old);
		});
	}

	function activateIframes(cat) {
		document.querySelectorAll('iframe[data-src][data-lpcc="' + cat + '"]').forEach(loadIframe);
	}

	function loadIframe(f) {
		var ph = f._lpccPlaceholder;
		if (ph && ph.parentNode) ph.parentNode.removeChild(ph);
		f.src = f.getAttribute('data-src');
		f.removeAttribute('data-src');
		f.hidden = false;
	}

	function apply() {
		['statistics', 'marketing'].forEach(function (cat) {
			if (state[cat] && !loaded[cat]) {
				activateScripts(cat);
				activateIframes(cat);
				loaded[cat] = true;
			}
		});
		buildPlaceholders();
	}

	// --------------------------------------------------------- 2-Klick-Embeds

	function buildPlaceholders() {
		document.querySelectorAll('iframe[data-src][data-lpcc]').forEach(function (f) {
			var cat = f.getAttribute('data-lpcc');
			if (state && state[cat]) return;      // wird gleich regulär geladen
			if (f._lpccPlaceholder) return;        // schon vorhanden

			var ph = document.createElement('div');
			ph.className = 'lpcc-embed';
			var h = f.getAttribute('height') || '';
			if (h.indexOf('%') !== -1) {
				ph.style.height = h; // z.B. Matterport in aspect-ratio-Container
			} else {
				ph.style.minHeight = (parseInt(h, 10) || 300) + 'px';
			}
			ph.innerHTML =
				'<p class="lpcc-embed__note">' + (T.embed_note || '') + '</p>' +
				'<button type="button" class="lpcc-btn lpcc-btn--primary">' + (T.load_embed || 'Load') + '</button>' +
				'<label class="lpcc-embed__always"><input type="checkbox"> ' + (T.embed_always || '') + '</label>';

			ph.querySelector('button').addEventListener('click', function () {
				if (ph.querySelector('input').checked) {
					// dauerhaft: Kategorie freigeben (aktiviert auch Scripts der Kategorie)
					var upd = {};
					upd[cat] = true;
					setConsent(upd);
				} else {
					loadIframe(f); // nur dieses eine Embed, ohne Cookie-Änderung
				}
			});

			f.hidden = true;
			f._lpccPlaceholder = ph;
			f.parentNode.insertBefore(ph, f);
		});
	}

	// ----------------------------------------------------------------- Banner

	var banner, prefs, prefsOpen = false;

	function showBanner(withPrefs) {
		banner = document.getElementById('lpcc-banner');
		if (!banner) return;
		prefs = document.getElementById('lpcc-prefs');

		// Checkboxen auf aktuellen Stand
		document.getElementById('lpcc-cat-statistics').checked = !!(state && state.statistics);
		document.getElementById('lpcc-cat-marketing').checked = !!(state && state.marketing);

		if (withPrefs) togglePrefs(true);
		banner.hidden = false;
	}

	function hideBanner() {
		if (banner) banner.hidden = true;
	}

	function togglePrefs(open) {
		prefsOpen = open;
		prefs.hidden = !open;
		var btn = document.getElementById('lpcc-toggle-prefs');
		if (open) btn.textContent = btn.getAttribute('data-save-label');
	}

	function bindBanner() {
		var accept = document.getElementById('lpcc-accept-all');
		var deny = document.getElementById('lpcc-deny');
		var toggle = document.getElementById('lpcc-toggle-prefs');
		if (!accept) return;

		accept.addEventListener('click', function () {
			setConsent({ statistics: true, marketing: true });
		});
		deny.addEventListener('click', function () {
			setConsent({ statistics: false, marketing: false });
		});
		toggle.addEventListener('click', function () {
			if (!prefsOpen) {
				togglePrefs(true);
			} else {
				setConsent({
					statistics: document.getElementById('lpcc-cat-statistics').checked,
					marketing: document.getElementById('lpcc-cat-marketing').checked
				});
			}
		});
	}

	// ------------------------------------------------------------- Consent-Set

	function setConsent(update) {
		var revoked =
			(loaded.statistics && update.statistics === false) ||
			(loaded.marketing && update.marketing === false);

		state = state || { v: VERSION, region: 'eu', statistics: false, marketing: false };
		if ('statistics' in update) state.statistics = !!update.statistics;
		if ('marketing' in update) state.marketing = !!update.marketing;
		state.v = VERSION;

		writeCookie(state);
		hideBanner();

		if (revoked) {
			// Bereits geladene Tracker lassen sich nicht sauber entladen -> Reload
			location.reload();
			return;
		}
		apply();
	}

	// ------------------------------------------------------------------- Init

	function init() {
		bindBanner();
		state = readCookie();

		if (state) {
			apply();
			return;
		}

		// Platzhalter sofort aufbauen (state noch null => alles blockiert)
		state = null;
		buildPlaceholders();

		fetch(cfg.geoEndpoint, { cache: 'no-store', credentials: 'omit' })
			.then(function (r) { return r.json(); })
			.then(function (geo) {
				if (geo && geo.region === 'row') {
					// Opt-out-Region: alles aktiv, kein Banner
					state = { v: VERSION, region: 'row', statistics: true, marketing: true };
					writeCookie(state);
					apply();
				} else {
					state = { v: VERSION, region: 'eu', statistics: false, marketing: false };
					showBanner(false);
				}
			})
			.catch(function () {
				// Endpoint nicht erreichbar -> Fail-safe: EU-Verhalten
				state = { v: VERSION, region: 'eu', statistics: false, marketing: false };
				showBanner(false);
			});
	}

	// ------------------------------------------------------------- Public API

	window.lpcc = {
		open: function () { showBanner(true); },
		get: function () { return state ? JSON.parse(JSON.stringify(state)) : null; },
		set: setConsent
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
