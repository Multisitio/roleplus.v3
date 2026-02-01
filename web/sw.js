// /sw.js — minimal para red social (precarga segura; sin cache dinámico)
'use strict';

// Version tomada del query (?v=...) para invalidar caché al desplegar
const VERSION = new URL(self.location.href).searchParams.get('v') || '0';
const CACHE = `roleplus-precache-v${VERSION}`;
const ORIGIN = self.location.origin;

// Helper para añadir la query de version a estaticos versionados
const v = (p) => `${p}?v=${VERSION}`;

// Solo estos estaticos se precargan. Nada mas se cachea.
const PRECACHE = [
	v('/manifest.json'),
	v('/css/principal.min.css'),
	v('/css/masonry.min.css'),
	v('/css/ev.min.css'),
	v('/javascript/principal.min.js'),
	v('/javascript/ev.min.js'),
	v('/javascript/worker.js'),
	v('/javascript/masonry.min.js'),
	'/img/logos/icon-192x192.png',
	'/img/logos/apple-touch-icon.png'
];

// Lista blanca absoluta (URLs completas) para el handler de fetch
const WHITELIST = new Set(PRECACHE.map((p) => new URL(p, ORIGIN).href));

async function precacheBestEffort() {
	const c = await caches.open(CACHE);

	// Best-effort: si un recurso falla, NO rompe el install.
	await Promise.all(PRECACHE.map(async function (path) {
		try {
			const req = new Request(path, { cache: 'reload' });
			const res = await fetch(req);
			if (!res || !res.ok || res.type !== 'basic') return;
			await c.put(req, res.clone());
		} catch (_) { }
	}));
}

self.addEventListener('install', (event) => {
	event.waitUntil(precacheBestEffort());
	self.skipWaiting();
});

self.addEventListener('activate', (event) => {
	event.waitUntil((async () => {
		const names = await caches.keys();
		await Promise.all(names.filter((n) => n !== CACHE).map((n) => caches.delete(n)));
		await self.clients.claim();
	})());
});

// Solo respondemos desde cache a la whitelist exacta.
self.addEventListener('fetch', (event) => {
	const req = event.request;
	if (req.method !== 'GET') return;

	const url = new URL(req.url);
	if (url.origin !== ORIGIN) return;
	if (req.mode === 'navigate') return;
	if (!WHITELIST.has(url.href)) return;

	event.respondWith(
		caches.match(req, { ignoreSearch: false }).then((cached) => {
			if (cached) return cached;

			return fetch(req).then((res) => {
				if (res && res.ok && res.type === 'basic') {
					caches.open(CACHE).then((c) => c.put(req, res.clone())).catch(() => { });
				}
				return res;
			}).catch(() => caches.match(req));
		})
	);
});

// Mensajes minimos para control y salud
self.addEventListener('message', (event) => {
	const d = event.data || {};

	// Respuesta de salud para diagnostico
	if (d.rp_ping) {
		try {
			if (event.source && 'postMessage' in event.source) {
				event.source.postMessage({ rp_pong: d.rp_ping, ts: Date.now(), note: `sw-alive v${VERSION}` });
			}
		} catch (_) { }
		return;
	}

	if (d.type === 'SKIP_WAITING') self.skipWaiting();

	// "Kill switch" remoto opcional: desregistrar y limpiar caches
	if (d.type === 'UNREGISTER') {
		event.waitUntil((async () => {
			const keys = await caches.keys();
			await Promise.all(keys.map((k) => caches.delete(k)));
			const reg = await self.registration.unregister();
			const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
			clients.forEach((c) => c.postMessage({ type: 'SW_UNREGISTERED', ok: reg }));
		})());
	}
});

// Push: muestra notificacion simple (servidor puede enviar title/body/icon/url)
self.addEventListener('push', (event) => {
	let data = {};
	if (event.data) { try { data = event.data.json(); } catch (_) { } }

	const title = data.title || 'Nueva notificacion';
	const options = {
		body: data.body || '',
		icon: data.icon || '/img/logos/icon-192x192.png',
		badge: data.badge || '/img/logos/icon-192x192.png',
		data: { url: data.url || '/' },
		requireInteraction: !!data.requireInteraction
	};

	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const target = new URL((event.notification.data && event.notification.data.url) || '/', ORIGIN).href;

	event.waitUntil((async () => {
		const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

		// Si ya hay ventanas abiertas, NO abras una nueva: enfoca una y navega.
		if (list && list.length) {
			let chosen = null;

			for (const c of list) {
				if (!c || !c.url) continue;
				if (new URL(c.url, ORIGIN).href === target) {
					chosen = c;
					break;
				}
			}
			if (!chosen) chosen = list[0];

			try { if (chosen && 'focus' in chosen) await chosen.focus(); } catch (_) { }
			try {
				if (chosen && 'navigate' in chosen && chosen.url !== target) {
					await chosen.navigate(target);
				}
			} catch (_) { }

			try {
				if (chosen && 'postMessage' in chosen) {
					chosen.postMessage({ type: 'SW_NAVIGATE', url: target });
				}
			} catch (_) { }

			return;
		}

		// Si no hay ninguna ventana, no queda otra que abrir una.
		if (self.clients.openWindow) return self.clients.openWindow(target);
	})());
});
