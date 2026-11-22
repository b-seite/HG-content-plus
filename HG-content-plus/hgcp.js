/* HG Content Plus – Frontend (v3.0.0: Position/Offsets komplett konfigurierbar, Popup-Anker am +-Mittelpunkt) */
(function () {
	'use strict';

	function conf() {
		return window.HGCPlus || {};
	}

	function num(v, fallback) {
		return (typeof v === 'number' && isFinite(v)) ? v : fallback;
	}

	/* Position: links/rechts (side) und oben/unten (vert) für einen Host bestimmen. */
	function positionOf(hostEl, containerEl) {
		var c = conf();
		var side = (typeof c.sidePos === 'string') ? c.sidePos : 'rechts';
		var vert = (typeof c.vertPos === 'string') ? c.vertPos : 'unten';

		/* Pro-Element-Overrides per Klasse (auch am Galerie-Wrapper):
		   mpii-links / mpii-rechts, mpii-oben / mpii-unten */
		var scope = containerEl || hostEl;
		if (scope.classList.contains('mpii-links')) { side = 'links'; }
		else if (scope.classList.contains('mpii-rechts')) { side = 'rechts'; }
		if (scope.classList.contains('mpii-oben')) { vert = 'oben'; }
		else if (scope.classList.contains('mpii-unten')) { vert = 'unten'; }

		return { side: side, vert: vert };
	}

	/* Optimole o. ä. CDN-URLs enthalten die Original-URL eingebettet (.../https://domain/...). */
	function normalize(src) {
		if (!src) { return ''; }
		var m = src.match(/\/(https?:\/\/[^\/]+\/wp-content\/uploads\/.+)$/);
		return m ? m[1] : src;
	}

	/* Beste Identifikations-URL eines Bildes: Lightbox-Link (Original), sonst src/currentSrc. */
	function identify(img) {
		var link = img.closest('a');
		if (link && /\/wp-content\/uploads\//.test(link.href)) {
			return normalize(link.href);
		}
		return normalize(img.currentSrc || img.src || '');
	}

	function placeButton(btn, hostEl, containerEl) {
		var c = conf();
		var pos = positionOf(hostEl, containerEl);
		var size = num(c.size, 50);
		var distS = num(c.distSide, 20);
		var distV = num(c.distVert, 20);

		btn.style.top = '';
		btn.style.bottom = '';
		btn.style.left = '';
		btn.style.right = '';

		if (pos.vert === 'oben') {
			btn.style.top = distV + 'px';
		} else {
			btn.style.bottom = distV + 'px';
		}
		if (pos.side === 'links') {
			btn.style.left = distS + 'px';
		} else {
			btn.style.right = distS + 'px';
		}
		return pos;
	}

	/* Popup platzieren: Bezugspunkt = Mittelpunkt des +-Buttons, plus Offsets.
	   0/0 = Kante des Popups am Mittelpunkt; das Popup öffnet automatisch
	   zur gegenüberliegenden Seite des Buttons. */
	function placeBox(btn, box, hostEl, pos) {
		var c = conf();
		var offX = num(c.offX, 0);
		var offY = num(c.offY, 0);

		var hb = hostEl.getBoundingClientRect();
		var bb = btn.getBoundingClientRect();

		/* Mittelpunkt des Buttons in Host-Koordinaten. */
		var cx = bb.left - hb.left + bb.width / 2 + offX;
		var cy = bb.top - hb.top + bb.height / 2 + offY;

		var w = box.offsetWidth;
		var h = box.offsetHeight;

		var left, top;

		/* Horizontal: Button rechts -> Popup öffnet nach links (rechte Kante am Bezugspunkt),
		   Button links -> Popup öffnet nach rechts (linke Kante am Bezugspunkt). */
		if (pos.side === 'rechts') {
			left = cx - w;
		} else {
			left = cx;
		}

		/* Vertikal: Button unten -> Popup öffnet nach oben (untere Kante am Bezugspunkt),
		   Button oben -> Popup öffnet nach unten (obere Kante am Bezugspunkt). */
		if (pos.vert === 'unten') {
			top = cy - h;
		} else {
			top = cy;
		}

		/* Im Host halten (keine Überstände). */
		var maxLeft = Math.max(4, hb.width - w - 4);
		var maxTop = Math.max(4, hb.height - h - 4);
		left = Math.min(Math.max(left, 4), maxLeft);
		top = Math.min(Math.max(top, 4), maxTop);

		box.style.left = Math.round(left) + 'px';
		box.style.top = Math.round(top) + 'px';
	}

	function handleImg(img) {
		if (img.dataset.hgcpDone) { return; }
		img.dataset.hgcpDone = '1';

		var src = identify(img);
		if (!src) { img.dataset.hgcpDone = ''; return; }

		var container = img.closest('.mpii');
		if (!container) { return; }

		var data = new FormData();
		data.append('action', 'mpii_get');
		data.append('nonce', (conf().nonce) || '');
		data.append('src', src);

		fetch((conf().ajax) || '/wp-admin/admin-ajax.php', { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var html = res && res.success && res.data && res.data.html ? res.data.html : '';
				if (!html) { return; }
				build(img, container, html);
			})
			.catch(function () { /* still: kein Button */ });
	}

	function build(img, container, html) {
		var c = conf();
		/* Pro Bild: Button + Box in den nächsten Block-Wrapper des Bildes. */
		var host = img.closest('.element-inner') || img.closest('.element') || img.parentNode || container;
		host.classList.add('mpii-host');

		var box = document.createElement('div');
		box.className = 'mpii-box';
		box.innerHTML = '<div class="mpii-box-inner">' + html + '</div>';
		box.setAttribute('hidden', 'hidden');

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'mpii-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-label', 'Bildinformationen anzeigen');
		btn.innerHTML = '<span>+</span>';

		host.appendChild(btn);
		host.appendChild(box);

		var pos = placeButton(btn, host, container);
		var fade = num(c.fade, 500);

		function isOpen() { return !box.hasAttribute('hidden'); }

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (isOpen()) {
				box.classList.remove('mpii-open');
				window.setTimeout(function () { box.setAttribute('hidden', 'hidden'); }, fade);
				btn.setAttribute('aria-expanded', 'false');
			} else {
				placeBox(btn, box, host, positionOf(host, container));
				box.removeAttribute('hidden');
				placeBox(btn, box, host, positionOf(host, container));
				void box.offsetWidth; /* Reflow, damit die Opacity-Transition greift */
				box.classList.add('mpii-open');
				btn.setAttribute('aria-expanded', 'true');
			}
		});
	}

	function scan() {
		document.querySelectorAll('.mpii').forEach(function (container) {
			container.querySelectorAll('img').forEach(handleImg);
		});
	}

	function init() {
		scan();
		/* Justified-Gallery/Lazy-Load: Bilder erscheinen teils erst später.
		   Deshalb bis 20 s nach Seitenstart weiterhin nach neuen Bildern suchen. */
		var rounds = 0;
		var timer = window.setInterval(function () {
			scan();
			rounds += 1;
			if (rounds >= 25) { window.clearInterval(timer); }
		}, 800);

		if (window.MutationObserver) {
			new MutationObserver(function (muts) {
				muts.forEach(function (m) {
					m.addedNodes && m.addedNodes.forEach(function (n) {
						if (n.nodeType !== 1) { return; }
						if (n.tagName === 'IMG' && n.closest('.mpii')) {
							handleImg(n);
						} else if (n.querySelectorAll) {
							n.querySelectorAll('.mpii img').forEach(handleImg);
						}
					});
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
