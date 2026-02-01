/* 4-masonry.js – MASONRY SIN DEPENDENCIAS, SIN TIMERS */
(() => {
	"use strict";

	/* ===== CONFIG ===== */
	const SELECTOR = "[data-columns]"; // Contenedores grid
	const EVENTS = ["DOMContentLoaded", "load", "kumbia:inject", "rp:dom:changed"]; // Re-enganche
	// Nota: el alto de fila real se lee del CSS (grid-auto-rows). No hardcodeamos aquí.

	/* ===== STATE ===== */
	const containers = new Set();          // <grid> conocidos
	const roPerItem = new WeakMap();       // ResizeObserver por item

	/* ===== CORE ===== */

	// Calcula y aplica el span de filas de un item en un container (usa row-gap real)
	function applyRowSpan(container, item) {
		if (!container || !item) return; 

		// Fuerza layout antes de medir
		void item.offsetHeight; 

		const cs = window.getComputedStyle(container);
		let row = parseFloat(cs.gridAutoRows);
		let gap = 0;

		// row-gap estándar; si no existe, intenta 'gap'
		if (cs.rowGap && cs.rowGap !== "normal") {
			gap = parseFloat(cs.rowGap);
		} else if (cs.gap && cs.gap !== "normal") {
			gap = parseFloat(cs.gap);
		}

		// Altura real del item
		const h = item.getBoundingClientRect().height;

		// Evitar divisiones raras si el CSS no está aún aplicado
		if (!row || row < 1) row = 10;

		// Fórmula clásica: (alto + gap) / (row + gap)
		const span = Math.max(1, Math.ceil((h + gap) / (row + gap)));
		item.style.setProperty("--row-span", String(span));
	}

	// Aplica span a todos los hijos directos del container
	function layoutContainer(container) {
		if (!container) return;
		const items = container.querySelectorAll(":scope > *");
		for (let i = 0; i < items.length; i++) {
			applyRowSpan(container, items[i]);
		}
	}

	// Crea/adjunta ResizeObserver por item (contenido que cambia, imágenes, fuentes, etc.)
	function observeItem(container, item) {
		if (!container || !item) return;
		if (roPerItem.has(item)) return;

		// Recalcular cuando el item cambie de tamaño
		const ro = new ResizeObserver(function () {
			applyRowSpan(container, item);
		});
		try { ro.observe(item); } catch (e) { /* noop */ }
		roPerItem.set(item, ro);

		// Imágenes que cargan después
		const imgs = item.querySelectorAll("img");
		for (let i = 0; i < imgs.length; i++) {
			const img = imgs[i];
			if (img.complete) continue;
			img.addEventListener("load", function () { applyRowSpan(container, item); }, { once: true });
			img.addEventListener("error", function () { applyRowSpan(container, item); }, { once: true });
		}
	}

	// Recorre y engancha todos los items actuales del container
	function attachItems(container) {
		if (!container) return;
		const items = container.querySelectorAll(":scope > *");
		for (let i = 0; i < items.length; i++) {
			observeItem(container, items[i]);
		}
	}

	// Alta de un container
	function attachContainer(container) {
		if (!container || containers.has(container)) return;
		containers.add(container);

		attachItems(container);
		layoutContainer(container);
	}

	// Buscar e inicializar todos los containers vivos
	function scan() {
		const list = document.querySelectorAll(SELECTOR);
		for (let i = 0; i < list.length; i++) {
			attachContainer(list[i]);
		}
	}

	/* ===== OBSERVERS GLOBALES ===== */

	// Mutations: si entran containers o hijos nuevos, nos enganchamos
	const mo = new MutationObserver(function (mutations) {
		let needsLayout = false;

		for (let i = 0; i < mutations.length; i++) {
			const m = mutations[i];

			// Nuevos nodos
			if (m.type === "childList" && m.addedNodes && m.addedNodes.length) {
				for (let j = 0; j < m.addedNodes.length; j++) {
					const node = m.addedNodes[j];
					if (!node || node.nodeType !== 1) continue;

					// ¿Es un container nuevo?
					if (node.matches && node.matches(SELECTOR)) {
						attachContainer(node);
						needsLayout = true;
						continue;
					}

					// ¿Contiene containers?
					if (node.querySelectorAll) {
						const inner = node.querySelectorAll(SELECTOR);
						if (inner && inner.length) {
							for (let k = 0; k < inner.length; k++) attachContainer(inner[k]);
							needsLayout = true;
						}
					}

					// Si cae dentro de un container conocido, observar items
					containers.forEach(function (c) {
						if (c.contains(node)) {
							// Si el nodo es hijo directo, observarlo
							if (node.parentElement === c) {
								observeItem(c, node);
								applyRowSpan(c, node);
								needsLayout = true;
							} else {
								// O si añade varios hijos
								const direct = c.querySelectorAll(":scope > *");
								for (let d = 0; d < direct.length; d++) observeItem(c, direct[d]);
								needsLayout = true;
							}
						}
					});
				}
			}
		}

		if (needsLayout) layoutAll();
	});

	function layoutAll() {
		containers.forEach(function (c) { layoutContainer(c); });
	}

	/* ===== EVENTOS DE RE-ENGANCHE ===== */

	for (let i = 0; i < EVENTS.length; i++) {
		window.addEventListener(EVENTS[i], function () {
			scan();
			layoutAll();
		});
	}

	// Resize de ventana: sin timers; el propio ResizeObserver por item cubre la mayoría de casos.
	// Aun así, un relayout global garantiza coherencia cuando cambian breakpoints/columnas.
	window.addEventListener("resize", function () {
		layoutAll();
	});

	/* ===== STARTUP ===== */

	// Observamos el body para inyecciones (AJAX/SSE/WS)
	try { mo.observe(document.body, { childList: true, subtree: true }); } catch (e) { /* noop */ }

	// Arranque inmediato por si el DOM ya está poblado
	scan();
	layoutAll();

	/* ===== API ===== */
	window.MasonryGrid = {
		update: function () { scan(); layoutAll(); }
	};
})();
