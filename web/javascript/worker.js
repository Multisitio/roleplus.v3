// /javascript/worker.js — mínimo registro del Service Worker
(function () {
	'use strict';
	if (!('serviceWorker' in navigator)) return;

	// Evita doble init (páginas con inyecciones/reloads vía AJAX)
	if (window.__roleplus_sw_inited__) return;
	window.__roleplus_sw_inited__ = true;

	// Usa la misma query de versión que tu sistema (p.ej. ?v=<?= $version ?> en el template)
	var v = (document.currentScript && new URL(document.currentScript.src, location).searchParams.get('v')) || '';
	var swUrl = '/sw.js' + (v ? ('?v=' + v) : '');

	window.addEventListener('load', function () {
		navigator.serviceWorker.register(swUrl).catch(function (e) {
			// Silencioso en prod; dejar traza corta por si se necesita
			try { console.warn('SW register error', e && (e.message || e)); } catch (_) { }
		});
	});

	// Si el SW notifica que quedó desregistrado, recarga una vez
	navigator.serviceWorker.addEventListener('message', function (e) {
		var d = e && e.data || {};
		if (d.type === 'SW_UNREGISTERED') {
			try { location.reload(); } catch (_) { }
		}
	});
})();
