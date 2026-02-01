/* KUMBIA.JS v3.0 - Micro-Framework data-*
 * "Menos es más": Autonómo, reactivo y ultraligero.
 */
((window, document) => {
	"use strict";

	const CONFIG = { debug: false, headers: { "X-Requested-With": "XMLHttpRequest" } };
	const LOG = (...a) => CONFIG.debug && console.log("[Kumbia]", ...a);
	const WARN = (...a) => console.warn("[Kumbia]", ...a);

	// --- UTILS & CORE ---
	const $ = (sel, root = document) => typeof sel === "string" ? root.querySelector(sel) : sel;
	const $$ = (sel, root = document) => root.querySelectorAll(sel);

	const DATA = (el, key) => {
		const v = el.dataset[key] || el.getAttribute(`data-${key.replace(/([A-Z])/g, "-$1").toLowerCase()}`);
		return v === "true" ? true : v === "false" ? false : v;
	};

	const REQ = async (url, opts = {}) => {
		try {
			const res = await fetch(url, {
				method: opts.method || "GET",
				headers: { ...CONFIG.headers, ...opts.headers },
				body: opts.body,
				cache: "no-store"
			});
			if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
			return await res.text();
		} catch (e) { WARN("Fetch error:", url, e); throw e; }
	};

	const HTML = (html) => {
		const t = document.createElement("template");
		t.innerHTML = html;
		// Sanitizado básico: remover scripts inline peligrosos si fuera necesario, 
		// pero confiamos en que el backend envía HTML seguro o usamos una librería externa si se requiere rigor.
		// Aquí mantenemos la filosofía "ligera": scripts en el HTML inyectado NO se ejecutan automáticamente por innerHTML,
		// pero si necesitamos que se ejecuten (como en la v2.5), debemos hacerlo manualmente.
		// La v2.5 borraba scripts. Mantengamos eso por seguridad.
		t.content.querySelectorAll("script").forEach(s => s.remove());
		return t.content;
	};

	// --- UI & FX ---
	const UI = {
		lock: (on) => document.body.style.overflow = on ? "hidden" : "",
		isOverlay: (el) => el.matches?.(".overlay, [role='dialog']"),
		checkOverlays: () => UI.lock($$(".overlay, [role='dialog']").length > 0 && Array.from($$(".overlay, [role='dialog']")).some(el => el.offsetWidth > 0 && el.offsetHeight > 0)),

		toggle: (el, show) => {
			if (!el) return;
			const isVis = show ?? (getComputedStyle(el).display === "none" || el.classList.contains("hide"));

			// Guardar estado previo si se va a ocultar y no tiene
			if (!isVis && !el.dataset.display) el.dataset.display = getComputedStyle(el).display;

			el.classList.toggle("hide", !isVis);
			el.style.display = isVis ? (el.dataset.display === "none" ? "" : el.dataset.display || "") : "none";

			if (isVis && el.hasAttribute("data-autoscroll")) SCROLL.toBottom(el);
			if (UI.isOverlay(el)) UI.checkOverlays();
		},

		fade: (el, ms = 200) => {
			el.style.transition = `opacity ${ms}ms`;
			el.style.opacity = 0;
			setTimeout(() => { UI.toggle(el, false); el.style.opacity = ""; el.style.transition = ""; }, ms);
		},

		slide: (el, ms = 200) => {
			if (getComputedStyle(el).display === "none") UI.toggle(el, true);
			const h = el.scrollHeight;
			el.style.overflow = "hidden";
			el.style.maxHeight = "0";
			el.style.transition = `max-height ${ms}ms ease`;
			requestAnimationFrame(() => {
				el.style.maxHeight = h + "px";
				setTimeout(() => { el.style.maxHeight = ""; el.style.overflow = ""; el.style.transition = ""; }, ms);
			});
		}
	};

	// --- AUTOSCROLL ---
	const SCROLL = {
		toBottom: (el) => {
			if (!el) return;
			requestAnimationFrame(() => { el.scrollTop = el.scrollHeight; });
		},
		scan: () => $$("[data-autoscroll]").forEach(el => {
			if (el._kObs) return;
			(el._kObs = new MutationObserver(() => SCROLL.toBottom(el))).observe(el, { childList: true, subtree: true });
			SCROLL.toBottom(el);
		})
	};

	// --- ENGINE ---
	const ENGINE = {
		process: async (el, type, opts = {}) => {
			const targetSel = DATA(el, "ajax") || DATA(el, "target");
			const target = $(targetSel);
			if (!target && type !== "req") return; // "req" podría no necesitar target visual inmediato si es solo lógica

			let url = opts.url || el.href || el.getAttribute("action") || DATA(el, "href");
			if (!url) return;

			// Modificadores
			if (type === "select") url = `${url.replace(/\/$/, "")}/${encodeURIComponent(el.value)}`;
			if (type === "live") url = `${url}${url.includes("?") ? "&" : "?"}keywords=${encodeURIComponent(el.value)}`;

			LOG(type.toUpperCase(), url, "->", targetSel);

			try {
				if (target) target.classList.add("loading");
				const html = await REQ(url, opts);

				if (target) {
					const content = HTML(html);
					const mode = DATA(el, "ajax-append") ? "append" : DATA(el, "ajax-prepend") ? "prepend" : "replace";

					if (mode === "replace") target.innerHTML = "";
					mode === "prepend" ? target.prepend(content) : target.append(content);

					UI.toggle(target, true);
					target.classList.remove("loading");

					// Reactivar comportamientos
					SCROLL.scan();
					if (UI.isOverlay(target)) UI.checkOverlays();
				}
			} catch (e) {
				if (target) target.classList.remove("loading");
			}
		},

		submit: async (form, btn) => {
			const targetSel = DATA(form, "ajax") || DATA(form, "ajax-append") || DATA(form, "ajax-prepend");
			if (!targetSel) return;

			const fd = new FormData(form);
			if (btn?.name) fd.append(btn.name, btn.value);

			// Limpiar inputs ocultos no deseados (lógica legacy simplificada)
			$$(".hide input, [hidden] input", form).forEach(i => !i.dataset.keep && i.type !== "hidden" && fd.delete(i.name));

			const btns = $$("[type='submit']", form);
			btns.forEach(b => b.disabled = true);

			await ENGINE.process(form, "form", {
				method: "POST",
				body: fd,
				url: form.getAttribute("action") || location.href
			});

			btns.forEach(b => b.disabled = false);
		}
	};

	// --- EVENT HANDLERS ---
	const HANDLERS = {
		click: (e) => {
			const t = e.target.closest("a, button, [data-click], [data-toggle], [data-show], [data-hide], [data-remove]");
			if (!t) return;

			// AJAX Link
			if (t.matches("a[data-ajax]")) {
				e.preventDefault();
				ENGINE.process(t, "link");
				return;
			}

			// Submit Button (handled in submit event usually, but if outside form or specific logic needed)
			// Aquí dejamos que el evento 'submit' del form lo maneje, salvo que sea un botón fuera de form.

			// UI Effects
			if (t.dataset.toggle) UI.toggle($(t.dataset.toggle));
			if (t.dataset.show) UI.toggle($(t.dataset.show), true);
			if (t.dataset.hide) UI.toggle($(t.dataset.hide), false);
			if (t.dataset.fadeout) UI.fade($(t.dataset.fadeout));
			if (t.dataset.slidedown) UI.slide($(t.dataset.slidedown));

			// Remove
			if (t.dataset.remove) {
				const sels = t.dataset.remove.split(",");
				sels.forEach(s => {
					s = s.trim();
					const targets = s === "parent" ? [t.parentElement] : s === "this" ? [t] : $$(s);
					targets.forEach(n => n.remove());
				});
				UI.checkOverlays();
			}

			// Confirm / Alert
			if (t.dataset.confirm && !confirm(t.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); return; }
			if (t.dataset.alert) alert(t.dataset.alert);

			// Active class toggle
			if (t.dataset.active) {
				const p = t.closest("nav, ul") || t.parentElement;
				$$(".active", p).forEach(n => n.classList.remove("active"));
				t.classList.add("active");
			}
		},

		submit: (e) => {
			if (e.target.matches("form[data-ajax], form[data-ajax-append], form[data-ajax-prepend]")) {
				e.preventDefault();
				ENGINE.submit(e.target, e.submitter);
			}
		},

		change: (e) => {
			const t = e.target;
			if (t.matches("select[data-ajax]")) ENGINE.process(t, "select");
			if (t.matches("select[data-redirect]")) location.href = `${DATA(t, "redirect").replace(/\/$/, "")}/${encodeURIComponent(t.value)}`;
		},

		input: (e) => {
			const t = e.target;
			if (t.matches("[data-live]")) {
				clearTimeout(t._to);
				t._to = setTimeout(() => ENGINE.process(t, "live"), 300);
			}
		}
	};

	// --- INIT ---
	const K = {
		version: "3.0.0",
		config: (o) => Object.assign(CONFIG, o),
		init: () => {
			document.addEventListener("click", HANDLERS.click);
			document.addEventListener("submit", HANDLERS.submit);
			document.addEventListener("change", HANDLERS.change);
			document.addEventListener("input", HANDLERS.input);

			// Global Observer para inyecciones dinámicas
			new MutationObserver((muts) => {
				muts.forEach(m => m.addedNodes.forEach(n => {
					if (n.nodeType !== 1) return;
					if (n.matches("[data-autoscroll]")) SCROLL.toBottom(n);
					$$("[data-autoscroll]", n).forEach(SCROLL.toBottom);
					// Deferred remove check
					if (n.dataset.removeId) $(`[data-id="${n.dataset.removeId}"]`)?.remove();
					$$("[data-remove-id]", n).forEach(r => $(`[data-id="${r.dataset.removeId}"]`)?.remove());
				}));
			}).observe(document.documentElement, { childList: true, subtree: true });

			SCROLL.scan();
			LOG("Ready");
		}
	};

	window.Kumbia = K;
	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", K.init); else K.init();

})(window, document);
