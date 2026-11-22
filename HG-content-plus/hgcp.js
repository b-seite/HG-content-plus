/* HG Content Plus – Frontend (v3.1.0: SVG-Plus, Klick-Blocker Popup, Batch-Ajax für gemeinsames Erscheinen) */
(function () {
	'use strict';

	function conf() {
		return window.HGCPlus || {};
	}

	function num(v, fallback) {
		return (typeof v === 'number' && isFinite(v)) ? v : fallback;
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

	/* Position: links/rechts (side) und oben/unten (vert) bestimmen. */
	function positionOf(containerEl) {
		var c = conf();
		var side = (typeof c.sidePos === 'string') ? c.sidePos : 'rechts';
		var vert = (typeof c.vertPos === 'string') ? c.vertPos : 'unten';

		var scope = containerEl || null;
		if (scope && scope.classList) {
			if (scope.classList.contains('mpii-links')) { side = 'links'; }
			else if (scope.classList.contains('mpii-rechts')) { side = 'rechts'; }
			if (scope.classList.contains('mpii-oben')) { vert = 'oben'; }
			else if (scope.classList.contains('mpii-unten')) { vert = 'unten'; }
		}
		return { side: side, vert: vert };
	}

	function placeButton(btn, containerEl) {
		var c = conf();
		var pos = positionOf(containerEl);
		var size = num(c.size, 50);
		var distS = num(c.distSide, 20);
		var distV = num(c.distVert, 20);

		btn.style.top = '';
		btn.style.bottom = '';
		btn.style.left = '';
		btn.style.right = '';
		btn.style.width = size + 'px';
		btn.style.height = size + 'px';
		btn.style.margin = '0';

		if (pos.vert === 'oben') { btn.style.top = distV + 'px'; }
		else { btn.style.bottom = distV + 'px'; }
		if (pos.side === 'links') { btn.style.left = distS + 'px'; }
		else { btn.style.right = distS + 'px'; }
		return pos;
	}

	/* Popup platzieren: Bezugspunkt = Mittelpunkt des +-Buttons, plus Offsets.
	   0/0 = Kante des Popups am Mittelpunkt; das Popup öffnet automatisch zur
	   gegenüberliegenden Seite des Buttons. Rechnet rein auf Layout-Basis
	   (offsetLeft/offsetTop im Host-Koordinatensystem), dadurch robust gegen
	   Transforms, kollabierte Vorfahren und Lazy-Load. */
	function placeBox(btn, box, img, pos) {
		var c = conf();
		var offX = num(c.offX, 0);
		var offY = num(c.offY, 0);

		var w = box.offsetWidth;
		var h = box.offsetHeight;
		if (!w || !h) { return; }

		/* Mittelpunkt des Buttons in Host-Koordinaten. */
		var bx = btn.offsetLeft + btn.offsetWidth / 2 + offX;
		var by = btn.offsetTop + btn.offsetHeight / 2 + offY;

		var left, top;
		if (pos.side === 'rechts') { left = bx - w; }
		else { left = bx; }
		if (pos.vert === 'unten') { top = by - h; }
		else { top = by; }

		/* Innerhalb der Bildfläche (+ Button-Bereich) halten. */
		var imgL = img.offsetLeft;
		var imgT = img.offsetTop;
		var minX = Math.min(imgL, btn.offsetLeft);
		var minY = Math.min(imgT, btn.offsetTop);
		var maxX = Math.max(imgL + img.offsetWidth, btn.offsetLeft + btn.offsetWidth) - w;
		var maxY = Math.max(imgT + img.offsetHeight, btn.offsetTop + btn.offsetHeight) - h;

		left = Math.min(Math.max(left, minX), Math.max(minX, maxX));
		top = Math.min(Math.max(top, minY), Math.max(minY, maxY));

		box.style.left = Math.round(left) + 'px';
		box.style.top = Math.round(top) + 'px';
	}

	/* SVG-Plus: exakt zentriert, unabhaengig von Fonts. Balkendicke (+-width) und
	   Balkenlaenge (+-len) kommen aus den Einstellungen (in % des Buttons, also
	   immer proportional zum Kreis). */
	function plusSvg() {
		var c = conf();
		var strokeWidth = Math.max(1, Math.min(80, Math.round(num(c.plusWidth, 10))));
		var barLen = Math.max(1, Math.min(80, Math.round(num(c.plusLen, 56))));
		var inset = (100 - barLen) / 2; /* Balkenlaenge zentriert im 100x100-ViewBox */

		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 100 100');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('focusable', 'false');

		function bar(d) {
			var line = document.createElementNS(ns, 'path');
			line.setAttribute('d', d);
			line.setAttribute('stroke', 'rgba(0,0,0,0.7)');
			line.setAttribute('stroke-width', strokeWidth);
			line.setAttribute('stroke-linecap', 'round');
			line.setAttribute('fill', 'none');
			return line;
		}

		/* [0] = vertikaler Balken (wird bei "-" ausgeblendet), [1] = horizontaler. */
		var vertical = bar('M50 ' + inset + ' V' + (100 - inset));
		var horizontal = bar('M' + inset + ' 50 H' + (100 - inset));
		svg.appendChild(vertical);
		svg.appendChild(horizontal);
		return { svg: svg, vertical: vertical };
	}

	function build(img, container, html) {
		var c = conf();
		/* Host = der direkte Anker/das direkte Elternelement des Bildes; Inline-Styles
		   erzwingen einen sauberen Positionskontext (robust gegen Theme-Layouts). */
		var direct = img.parentNode;
		var host = direct && direct.tagName === 'A' ? direct : (direct || container);

		host.classList.add('mpii-host');
		host.style.position = 'relative';
		if (host.ownerDocument.defaultView.getComputedStyle(host).display === 'inline') {
			host.style.display = 'block';
		}

		var box = document.createElement('div');
		box.className = 'mpii-box';
		box.innerHTML = '<div class="mpii-box-inner">' + html + '</div>';
		box.setAttribute('hidden', 'hidden');
		box.style.margin = '0';

		/* Klicks im Popup duerfen NICHT zur Lightbox/durch den Anker durchreichen. */
		box.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
		}, false);
		box.addEventListener('mousedown', function (e) { e.stopPropagation(); }, false);

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'mpii-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-label', 'Bildinformationen anzeigen');
		var parts = plusSvg();
		btn.appendChild(parts.svg);
		var showMinus = !!conf().plusMinus;

		host.appendChild(btn);
		host.appendChild(box);

		var pos = placeButton(btn, container);
		var fade = num(c.fade, 500);

		function isOpen() { return !box.hasAttribute('hidden'); }

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (isOpen()) {
				box.classList.remove('mpii-open');
				window.setTimeout(function () { box.setAttribute('hidden', 'hidden'); }, fade);
				btn.setAttribute('aria-expanded', 'false');
				if (showMinus) { parts.vertical.style.display = ''; } /* wieder + */
			} else {
				/* Erst unsichtbar Platz messen, dann positionieren, dann einblenden. */
				box.style.visibility = 'hidden';
				box.removeAttribute('hidden');
				placeBox(btn, box, img, pos);
				box.style.visibility = '';
				void box.offsetWidth; /* Reflow, damit die Opacity-Transition greift */
				box.classList.add('mpii-open');
				btn.setAttribute('aria-expanded', 'true');
				if (showMinus) { parts.vertical.style.display = 'none'; } /* wird zu - */
			}
		});
	}

	/* ---------- Batch: alle Bilder einer Seite in EINEM Ajax-Request ---------- */

	var pending = new Map(); /* src -> [img, ...] */
	var flushTimer = null;

	function queueImg(img) {
		if (img.dataset.hgcpDone) { return; }
		var container = img.closest('.mpii');
		if (!container) { return; }
		img.dataset.hgcpDone = '1';

		var src = identify(img);
		if (!src) { img.dataset.hgcpDone = ''; return; }

		if (!pending.has(src)) { pending.set(src, []); }
		pending.get(src).push(img);

		/* Kurzer Sammeltakt: Bilder, die fast gleichzeitig gefunden werden, gehen in einen Request. */
		if (flushTimer) { window.clearTimeout(flushTimer); }
		flushTimer = window.setTimeout(flush, 150);
	}

	function flush() {
		flushTimer = null;
		if (pending.size === 0) { return; }

		var data = new FormData();
		data.append('action', 'mpii_get_batch');
		data.append('nonce', (conf().nonce) || '');
		pending.forEach(function (imgs, src) {
			data.append('srcs[]', src);
		});
		var batch = new Map(pending);
		pending.clear();

		fetch((conf().ajax) || '/wp-admin/admin-ajax.php', { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var map = res && res.success && res.data && res.data.map ? res.data.map : {};
				batch.forEach(function (imgs, src) {
					var html = map[src] || '';
					if (!html) { return; }
					imgs.forEach(function (img) {
						if (!img.isConnected) { return; }
						var container = img.closest('.mpii');
						if (container) { build(img, container, html); }
					});
				});
			})
			.catch(function () { /* still: kein Button */ });
	}

	function scan() {
		document.querySelectorAll('.mpii').forEach(function (container) {
			container.querySelectorAll('img').forEach(queueImg);
		});
	}

	function init() {
		scan();
		/* Justified-Gallery/Lazy-Load: Bilder erscheinen teils erst spaeter.
		   Schneller Scan-Takt (300 ms, bis ~18 s), damit Buttons frueh auftauchen. */
		var rounds = 0;
		var timer = window.setInterval(function () {
			scan();
			rounds += 1;
			if (rounds >= 60) { window.clearInterval(timer); }
		}, 300);

		if (window.MutationObserver) {
			new MutationObserver(function (muts) {
				muts.forEach(function (m) {
					m.addedNodes && m.addedNodes.forEach(function (n) {
						if (n.nodeType !== 1) { return; }
						if (n.tagName === 'IMG' && n.closest('.mpii')) {
							queueImg(n);
						} else if (n.querySelectorAll) {
							n.querySelectorAll('.mpii img').forEach(queueImg);
						}
					});
				});
			}).observe(document.body, { childList: true, subtree: true });
		}

		/* Nach vollständigem Laden (Bilder/JG fertig) direkt nochmal scuern und flushen. */
		window.addEventListener('load', function () { scan(); flush(); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
