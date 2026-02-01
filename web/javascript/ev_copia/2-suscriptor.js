/* 2-suscriptor.js
 * Suscriptor de tiempo real por canal.
 *
 * CONTRATO (estricto):
 * - Params válidos: ch, url, box, append, mode.
 * - En el FRONT, box NO es opcional ⇒ SIEMPRE es el propio <tag data-ch="...">.
 *   (Se ignora cualquier "box" del payload).
 * - Transporte:
 *     SSE (defecto):  /chat/sse?ch=ID
 *     WS  (opcional): /chat/ws?ch=ID
 * - Mensaje desde standalone (JSON):
 *     { "url": "/ruta", "append": true|false }
 *
 * LOGS:
 *   "SSE CONNECTED TO <canal>"              |  "SSE CONNECTED TO <canal> APPEND"
 *   "WS CONNECTED TO <canal>"               |  "WS CONNECTED TO <canal> APPEND"
 *   "FETCH <url> TO <canal>"                |  "FETCH <url> TO <canal> APPEND"
 */

(function () {
	'use strict';

	// --- Estado ---------------------------------------------------
	// Evita re-suscribir el mismo nodo
	const subscribed = new WeakSet();
	// Guardar conexiones para cierre limpio
	const conns = new Map(); // el -> {type:'sse'|'ws', conn:EventSource|WebSocket}

	// --- Utils ----------------------------------------------------
	function parsePayload(raw) {
		let o = raw;
		try { if (typeof o === 'string') o = JSON.parse(o); } catch (_) { }
		if (!o || typeof o !== 'object') return null;

		const url = String(o.url || '');
		if (!url ||
			url.startsWith('http://') ||
			url.startsWith('https://') ||
			url.startsWith('javascript:')) {
			return null;
		}

		// append aquí solo sirve como override puntual en el mensaje
		let append = null;
		if (typeof o.append !== 'undefined') {
			append = !!o.append;
		}

		return { url, append };
	}

	async function fetchAndInject(baseEl, canal, url, appendFlag) {
		try {
			const resp = await fetch(url, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			});
			if (!resp.ok) {
				console.warn('[suscriptor] FETCH FAIL', { ch: canal, status: resp.status, url });
				return;
			}
			const html = await resp.text();

			if (appendFlag) {
				baseEl.insertAdjacentHTML('beforeend', html);
				console.info(`FETCH ${url} TO ${canal} APPEND`);
			} else {
				baseEl.innerHTML = html;
				console.info(`FETCH ${url} TO ${canal}`);
			}
		} catch (e) {
			console.warn('[suscriptor] FETCH ERROR', { ch: canal, err: (e && e.message) || String(e), url });
		}
	}

	function startSSE(baseEl, canal, defAppend) {
		const es = new EventSource('/chat/sse?ch=' + encodeURIComponent(canal));
		let opened = false;

		es.onopen = () => {
			opened = true;
			if (defAppend) {
				console.info(`SSE CONNECTED TO ${canal} APPEND`);
			} else {
				console.info(`SSE CONNECTED TO ${canal}`);
			}
		};
		es.onerror = () => {
			if (!opened) console.warn('[suscriptor] SUBSCRIBE FAIL', { ch: canal, via: 'SSE' });
			// EventSource reintenta solo
		};
		es.onmessage = (ev) => {
			const p = parsePayload(ev.data);
			if (!p) return;
			const append = (p.append !== null) ? p.append : defAppend;
			fetchAndInject(baseEl, canal, p.url, append);
		};

		return { type: 'sse', conn: es };
	}

	function startWS(baseEl, canal, defAppend) {
		const proto = (location.protocol === 'https:') ? 'wss:' : 'ws:';
		const ws = new WebSocket(proto + '//' + location.host + '/chat/ws?ch=' + encodeURIComponent(canal));
		let opened = false;

		ws.addEventListener('open', () => {
			opened = true;
			if (defAppend) {
				console.info(`WS CONNECTED TO ${canal} APPEND`);
			} else {
				console.info(`WS CONNECTED TO ${canal}`);
			}
		});
		ws.addEventListener('error', () => {
			if (!opened) console.warn('[suscriptor] SUBSCRIBE FAIL', { ch: canal, via: 'WS' });
		});
		ws.addEventListener('message', (ev) => {
			const p = parsePayload(ev.data);
			if (!p) return;
			const append = (p.append !== null) ? p.append : defAppend;
			fetchAndInject(baseEl, canal, p.url, append);
		});

		return { type: 'ws', conn: ws };
	}

	// --- Suscripción por nodo ------------------------------------
	function subscribeNode(el) {
		if (!el || subscribed.has(el)) return;
		const canal = (el.dataset.ch || '').trim();
		if (!canal) return;

		// defAppend = true si el atributo data-append existe en el nodo,
		// da igual cuál sea su valor.
		const defAppend = el.hasAttribute('data-append');

		const modeAttr = (el.dataset.mode || 'sse').toLowerCase();
		const mode = (modeAttr === 'ws') ? 'ws' : 'sse';

		// Arranca conexión y guarda handler para cierre
		const handle = (mode === 'ws')
			? startWS(el, canal, defAppend)
			: startSSE(el, canal, defAppend);

		conns.set(el, handle);
		subscribed.add(el);
	}

	function scan(root) {
		const list = (root || document).querySelectorAll('[data-ch]');
		if (!list.length) return;
		list.forEach(subscribeNode);
	}

	// --- Boot + re-scan hooks ------------------------------------
	function boot() { scan(document); }

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}

	// Re-escaneo automático cuando llegan nodos vía AJAX/DOM
	const mo = new MutationObserver((muts) => {
		for (let i = 0; i < muts.length; i++) {
			const m = muts[i];
			if (m.type !== 'childList') continue;

			// Nuevos nodos directos
			m.addedNodes && m.addedNodes.forEach((n) => {
				if (n.nodeType !== 1) return; // ELEMENT_NODE
				if (n.matches && n.matches('[data-ch]')) subscribeNode(n);
				// Descendientes con data-ch
				const qs = n.querySelectorAll ? n.querySelectorAll('[data-ch]') : [];
				if (qs && qs.length) qs.forEach(subscribeNode);
			});
		}
	});
	try { mo.observe(document.documentElement, { childList: true, subtree: true }); } catch (_) { }

	// API pública para forzar re-scan manual
	window.rpScanHooks = function (root) {
		try { scan(root || document); } catch (_) { }
	};
	document.addEventListener('rp:scan', function (ev) {
		try { scan((ev && ev.detail && ev.detail.root) || document); } catch (_) { }
	});

	// Cierre limpio al abandonar la página
	window.addEventListener('pagehide', function () {
		try { mo.disconnect(); } catch (_) { }
		conns.forEach((h) => {
			try { h && h.conn && h.conn.close && h.conn.close(); } catch (_) { }
		});
		conns.clear();
	}, { once: true });
})();
