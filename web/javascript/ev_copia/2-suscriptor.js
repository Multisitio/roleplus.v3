/* 2-suscriptor.js
 * Suscriptor de tiempo real por canal (SSE/WS).
 *
 * CONTRATO (estricto):
 * - Params validos: ch, url, box, append, mode.
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

	// el -> key (para limpiar rapido)
	const elkey = new WeakMap();

	// key = "<mode>:<canal>" -> handle
	// handle: { type, conn, canal, mode, els:Set<Element>, opened, queue:Promise }
	const channels = new Map();

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

		let append = null;
		if (typeof o.append !== 'undefined') append = !!o.append;

		return { url, append };
	}

	async function fetchHtml(url, canal) {
		const resp = await fetch(url, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		});
		if (!resp.ok) {
			console.warn('[suscriptor] FETCH FAIL', { ch: canal, status: resp.status, url });
			return null;
		}
		return await resp.text();
	}

	function inject(el, html, appendFlag) {
		if (appendFlag) {
			el.insertAdjacentHTML('beforeend', html);
		} else {
			el.innerHTML = html;
		}
	}

	function logConnected(type, canal, anyAppend) {
		if (anyAppend) {
			console.info(`${type} CONNECTED TO ${canal} APPEND`);
		} else {
			console.info(`${type} CONNECTED TO ${canal}`);
		}
	}

	function keyOf(mode, canal) {
		return mode + ':' + canal;
	}

	function anyElementAppend(els) {
		for (const el of els) {
			if (el && el.hasAttribute && el.hasAttribute('data-append')) return true;
		}
		return false;
	}

	function dispatch(handle, payload) {
		if (!handle || !payload) return;
		if (!handle.els || handle.els.size === 0) return;

		const canal = handle.canal;
		const url = payload.url;
		const overrideAppend = (payload.append !== null) ? !!payload.append : null;

		handle.queue = handle.queue.then(async function () {
			if (!handle.els || handle.els.size === 0) return;

			const html = await fetchHtml(url, canal);
			if (html === null) return;

			let didAppend = false;
			for (const el of handle.els) {
				if (!el || !el.isConnected) continue;

				const defAppend = el.hasAttribute('data-append');
				const appendFlag = (overrideAppend !== null) ? overrideAppend : defAppend;
				inject(el, html, appendFlag);
				if (appendFlag) didAppend = true;
			}

			if (didAppend) {
				console.info(`FETCH ${url} TO ${canal} APPEND`);
			} else {
				console.info(`FETCH ${url} TO ${canal}`);
			}
		}).catch(function (e) {
			console.warn('[suscriptor] DISPATCH ERROR', { ch: canal, err: (e && e.message) || String(e), url });
		});
	}

	function startSSE(handle) {
		const canal = handle.canal;
		const es = new EventSource('/chat/sse?ch=' + encodeURIComponent(canal));

		es.onopen = function () {
			handle.opened = true;
			logConnected('SSE', canal, anyElementAppend(handle.els));
		};
		es.onerror = function () {
			if (!handle.opened) console.warn('[suscriptor] SUBSCRIBE FAIL', { ch: canal, via: 'SSE' });
			// EventSource reintenta solo
		};
		es.onmessage = function (ev) {
			const p = parsePayload(ev.data);
			if (!p) return;
			dispatch(handle, p);
		};

		handle.type = 'sse';
		handle.conn = es;
	}

	function startWS(handle) {
		const canal = handle.canal;
		const proto = (location.protocol === 'https:') ? 'wss:' : 'ws:';
		const ws = new WebSocket(proto + '//' + location.host + '/chat/ws?ch=' + encodeURIComponent(canal));

		ws.addEventListener('open', function () {
			handle.opened = true;
			logConnected('WS', canal, anyElementAppend(handle.els));
		});
		ws.addEventListener('error', function () {
			if (!handle.opened) console.warn('[suscriptor] SUBSCRIBE FAIL', { ch: canal, via: 'WS' });
		});
		ws.addEventListener('message', function (ev) {
			const p = parsePayload(ev.data);
			if (!p) return;
			dispatch(handle, p);
		});

		handle.type = 'ws';
		handle.conn = ws;
	}

	function ensureChannel(mode, canal) {
		const key = keyOf(mode, canal);
		let handle = channels.get(key);
		if (handle) return handle;

		handle = {
			canal: canal,
			conn: null,
			els: new Set(),
			mode: mode,
			opened: false,
			queue: Promise.resolve(),
			type: mode
		};

		channels.set(key, handle);

		if (mode === 'ws') {
			startWS(handle);
		} else {
			startSSE(handle);
		}

		return handle;
	}

	// --- Suscripcion por nodo ------------------------------------
	function subscribeNode(el) {
		if (!el || subscribed.has(el)) return;

		const canal = (el.dataset.ch || '').trim();
		if (!canal) return;

		const modeAttr = (el.dataset.mode || 'sse').toLowerCase();
		const mode = (modeAttr === 'ws') ? 'ws' : 'sse';

		const handle = ensureChannel(mode, canal);
		handle.els.add(el);

		elkey.set(el, keyOf(mode, canal));
		subscribed.add(el);
	}

	function unsubscribeNode(el) {
		if (!el) return;

		const key = elkey.get(el);
		if (!key) return;

		const handle = channels.get(key);
		if (!handle) return;

		if (handle.els && handle.els.has(el)) handle.els.delete(el);
		elkey.delete(el);

		if (!handle.els || handle.els.size !== 0) return;

		try { handle.conn && handle.conn.close && handle.conn.close(); } catch (_) { }
		channels.delete(key);
	}

	function scan(root) {
		const list = (root || document).querySelectorAll('[data-ch]');
		if (!list.length) return;
		list.forEach(subscribeNode);
	}

	function unscan(root) {
		const list = (root || document).querySelectorAll ? (root || document).querySelectorAll('[data-ch]') : [];
		if (!list.length) return;
		list.forEach(unsubscribeNode);
	}

	// --- Boot + re-scan hooks ------------------------------------
	function boot() { scan(document); }

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}

	// Re-escaneo automatico cuando llegan/quitan nodos via AJAX/DOM
	const mo = new MutationObserver(function (muts) {
		for (let i = 0; i < muts.length; i++) {
			const m = muts[i];
			if (m.type !== 'childList') continue;

			m.addedNodes && m.addedNodes.forEach(function (n) {
				if (n.nodeType !== 1) return;
				if (n.matches && n.matches('[data-ch]')) subscribeNode(n);
				const qs = n.querySelectorAll ? n.querySelectorAll('[data-ch]') : [];
				if (qs && qs.length) qs.forEach(subscribeNode);
			});

			m.removedNodes && m.removedNodes.forEach(function (n) {
				if (n.nodeType !== 1) return;
				if (n.matches && n.matches('[data-ch]')) unsubscribeNode(n);
				unscan(n);
			});
		}
	});
	try { mo.observe(document.documentElement, { childList: true, subtree: true }); } catch (_) { }

	// API publica para forzar re-scan manual
	window.rpScanHooks = function (root) {
		try { scan(root || document); } catch (_) { }
	};
	document.addEventListener('rp:scan', function (ev) {
		try { scan((ev && ev.detail && ev.detail.root) || document); } catch (_) { }
	});

	// Cierre limpio al abandonar la pagina
	window.addEventListener('pagehide', function () {
		try { mo.disconnect(); } catch (_) { }

		channels.forEach(function (h) {
			try { h && h.conn && h.conn.close && h.conn.close(); } catch (_) { }
		});
		channels.clear();
	}, { once: true });
})();
