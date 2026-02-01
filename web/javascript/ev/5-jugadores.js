/* 5-jugadores.js - Vanilla JS version */

const dataPartidasIdu = document.querySelector('[data-partidas_idu]');

if (dataPartidasIdu) {
	const partidasIdu = dataPartidasIdu.getAttribute('data-partidas_idu');
	const PING_MS = 540000; // 9 minutos

	function ping(url) {
		fetch(url).catch(() => {});
	}

	document.addEventListener('DOMContentLoaded', () => {
		const url = '/ev/panel/conectado/' + partidasIdu;
		ping(url);
		setInterval(() => ping(url), PING_MS);

		const sendFinal = () => {
			if (navigator.sendBeacon) {
				navigator.sendBeacon(url);
			} else {
				fetch(url, { method: 'GET', keepalive: true, cache: 'no-store' }).catch(() => {});
			}
		};
		window.addEventListener('pagehide', sendFinal);
		window.addEventListener('beforeunload', sendFinal);
	});
}
