var data_partidas_idu = document.querySelector('[data-partidas_idu]');

(function () {
	// DEBUG global opcional (si ya existe, lo respetamos)
	if (typeof window.DEBUG === 'undefined') window.DEBUG = false;
	if (typeof window.dlog !== 'function') {
		window.dlog = function () {
			if (window.DEBUG) { try { console.debug.apply(console, arguments); } catch (_) {}
			}
		};
	}
})();

if (data_partidas_idu) {
	var partidas_idu = data_partidas_idu.dataset.partidas_idu;
	var PING_MS = 540000; // 9 minutos

	function ping(url) {
		// En producción, silencioso; si DEBUG=true verás el mensaje.
		dlog('[ev/panel] ping ->', url);

		// jQuery GET silencioso
		$.get(url).fail(function () {
			// Silenciamos errores de red para no ensuciar consola
			dlog('[ev/panel] ping failed');
		});
	}

	$(function () {
		var url = '/ev/panel/conectado/' + partidas_idu;

		// Evitar intervalos duplicados si el partial se monta más de una vez
		if (window._rp_conn_ping) {
			clearInterval(window._rp_conn_ping);
			window._rp_conn_ping = null;
		}

		// Primer ping al cargar
		ping(url);

		// Re-ping cada 9 minutos
		window._rp_conn_ping = setInterval(function () {
			ping(url);
		}, PING_MS);

		// En navegadores modernos, intentamos asegurar último ping al navegar
		var sendFinal = function () {
			try {
				if (navigator.sendBeacon) {
					navigator.sendBeacon(url);
				} else {
					// best effort sin ensuciar consola
					fetch(url, { method: 'GET', keepalive: true, cache: 'no-store' }).catch(function () {});
				}
			} catch (_) {}
		};
		window.addEventListener('pagehide', sendFinal);
		window.addEventListener('beforeunload', sendFinal);
	});
}
