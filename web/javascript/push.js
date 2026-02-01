// /javascript/push.js — mínimo para ROLEplus
(function () {
	'use strict';

	// Evita doble init en cargas vía AJAX
	if (window.__rpPushInit) return;
	window.__rpPushInit = true;

	const VAPID_PUBLIC = 'BKqpZbaUN6i-UhOBU2vKvTFg1H3iEL_KgSR8ai1C1t6TV_DaFxgXvITtG5PLNq3s5d4D8EqhTB0r1QzWC5y6n50';
	// ⇩⇩ Endpoint del controlador que has pasado
	const SUB_URL = '/registrados/notificaciones/suscribirse';

	function urlB64ToUint8Array(base64String) {
		const padding = '='.repeat((4 - base64String.length % 4) % 4);
		const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		const raw = atob(base64);
		const out = new Uint8Array(raw.length);
		for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
		return out;
	}

	async function ensurePermission() {
		if (!('Notification' in window)) return false;
		if (Notification.permission === 'denied') return false;
		if (Notification.permission === 'default') {
			const r = await Notification.requestPermission();
			if (r !== 'granted') return false;
		}
		return true;
	}

	async function sendSubscription(sub) {
		// El controlador lee JSON crudo desde php://input
		return fetch(SUB_URL, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin',
			body: JSON.stringify(sub) // PushSubscription -> toJSON()
		});
	}

	async function subscribeAndSend() {
		if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

		// Permisos
		if (!(await ensurePermission())) return;

		// Espera a que el SW esté listo
		const reg = await navigator.serviceWorker.ready;

		// Obtiene o crea la suscripción
		let sub = await reg.pushManager.getSubscription();
		if (!sub) {
			try {
				sub = await reg.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: urlB64ToUint8Array(VAPID_PUBLIC)
				});
			} catch (_) {
				return; // usuario canceló o el navegador falló
			}
		}

		// Envía al servidor (silencioso salvo errores importantes)
		try {
			const r = await sendSubscription(sub);
			if (r.status === 401) return; // no logueado
			if (!r.ok) console.warn('[PUSH] Alta fallida:', r.status);
		} catch (e) {
			console.warn('[PUSH] Error de red en alta:', (e && e.message) || e);
		}
	}

	function start() {
		subscribeAndSend();

		// Re-sincroniza cuando un SW actualizado toma el control
		navigator.serviceWorker.addEventListener('controllerchange', () => {
			navigator.serviceWorker.ready.then(() => subscribeAndSend());
		});
	}

	if (document.readyState === 'complete') start();
	else window.addEventListener('load', start, { once: true });

	// Helper opcional para consola
	window.RoleplusPush = { resync: subscribeAndSend };
})();
