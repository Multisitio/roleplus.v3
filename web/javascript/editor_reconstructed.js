
/* --- Start of 0-dom-bus.js --- */
/* 0-dom-bus.js - Vanilla JS (ya era vanilla) */

(() => {
	"use strict";

	var BUS_EVT = "rp:dom:changed";

	function emit(root) {
		try {
			document.dispatchEvent(new CustomEvent(BUS_EVT, { detail: { root: root || document } }));
		} catch (_) { }
	}

	function on(handler) {
		if (typeof handler !== "function") return;
		try { handler({ detail: { root: document } }); } catch (_) { }
		document.addEventListener(BUS_EVT, handler, false);
	}

	try {
		var mo = new MutationObserver(function (muts) {
			for (var i = 0; i < muts.length; i++) {
				var nlist = muts[i].addedNodes;
				for (var j = 0; j < nlist.length; j++) {
					var n = nlist[j];
					if (n && n.nodeType === 1) emit(n);
				}
			}
		});
		mo.observe(document.documentElement, { childList: true, subtree: true });
	} catch (_) { }

	try {
		window.rpDom = Object.freeze({
			emit: emit,
			on: on
		});
	} catch (_) { }
})();


/* --- Start of 2-kumbia.js --- */
/* KUMBIA.JS v2.5
 * Framework data-* autónomo, ultraligero y sin dependencias.
 * Compatible con contenido dinámico (AJAX/SSE/WS).
 *
 * Reglas:
 * ✦ Selector obligatorio (data-ajax, data-live, etc.)
 * ✦ Sin optional chaining
 * ✦ data-autoscroll en el contenedor que scrollea
 * ✦ Overlays visibles bloquean el scroll del body
 * ✦ Respeta el display/opacity originales
 */

((global) => {
	"use strict";

	/* ========================================
	 * CONFIG & LOG
	 * ======================================== */
	const CONFIG = {
		debug: false,
		headers: { "X-Requested-With": "XMLHttpRequest" },
		credentials: "same-origin",
		cache: "no-store"
	};

	function log() {
		if (!CONFIG.debug) return;
		var a = Array.prototype.slice.call(arguments);
		a.unshift("[Kumbia]");
		console.log.apply(console, a);
	}
	function warn() {
		var a = Array.prototype.slice.call(arguments);
		a.unshift("[Kumbia]");
		console.warn.apply(console, a);
	}

	/* ========================================
	 * UTILS
	 * ======================================== */
	const Utils = {
		getData: function (el, key) {
			if (!el || !el.getAttribute) return null;
			var v = el.getAttribute("data-" + key);
			if (v !== null) return v;

			// fallback a camelCase si fallase el dash-case
			var camel = key.replace(/-([a-z])/g, function (g) { return g[1].toUpperCase(); });
			if (el.dataset && el.dataset[camel]) return el.dataset[camel];
			return null;
		},

		requireSelector: function (raw, label) {
			const sel = raw && raw.trim();
			if (!sel) {
				warn(label + ": falta selector");
				return null;
			}
			return sel;
		},

		selectorOf: function (el, fallback) {
			if (fallback && fallback.trim()) return fallback.trim();
			if (!el) return "(nodo nulo)";
			if (el.id) return "#" + el.id;

			const cls = (el.className || "").toString().trim().split(/\s+/).filter(Boolean)[0];
			if (cls) return el.tagName.toLowerCase() + "." + cls;

			const name = el.getAttribute ? el.getAttribute("name") : null;
			if (name) return el.tagName.toLowerCase() + '[name="' + name + '"]';

			return el.tagName ? el.tagName.toLowerCase() : "(nodo)";
		},

		toQuery: function (p) {
			if (!p) return "";
			const qs = new URLSearchParams(p).toString();
			return qs ? "?" + qs : "";
		},

		fetch: async function (url, opts) {
			opts = opts || {};
			const method = opts.method || "GET";
			const full = method === "GET" ? (url + Utils.toQuery(opts.params)) : url;

			const res = await fetch(full, {
				method: method,
				cache: CONFIG.cache,
				headers: Object.assign({}, CONFIG.headers, opts.headers || {}),
				body: opts.body,
				credentials: CONFIG.credentials
			});

			if (!res.ok) throw new Error(method + " " + full + " → " + res.status);
			return res.text();
		},

		// Construye una URL añadiendo UN segmento al pathname (sin permitir "/")
		buildUrlWithSegment: function (base, value) {
			var u = new URL(base, global.location ? global.location.href : "http://local/");
			// nunca permitir "/" en el segmento
			var seg = encodeURIComponent(String(value).replace(/\//g, ""));
			if (!u.pathname.endsWith("/")) u.pathname += "/";
			u.pathname += seg;
			return u.toString();
		},

		parseHTML: function (html) {
			const t = document.createElement("template");
			t.innerHTML = html;
			// quitar <script> crudos
			const scripts = t.content.querySelectorAll("script");
			for (var i = 0; i < scripts.length; i++) scripts[i].remove();
			// saneado extra (defensa en profundidad)
			Sanitize.cleanFragment(t.content);
			return t.content;
		},

		on: function (type, sel, handler, root) {
			root = root || document.body;
			root.addEventListener(type, function (ev) {
				const t = ev.target && ev.target.closest ? ev.target.closest(sel) : null;
				if (t && root.contains(t)) {
					handler.call(t, ev);
				}
			});
		}
	};

	/* ========================================
	 * SANITIZADO HTML (defensa cliente)
	 * ======================================== */
	const Sanitize = {
		cleanFragment: function (root) {
			if (!root || !root.querySelectorAll) return;

			// 1) remover atributos on*
			var all = root.querySelectorAll("*");
			for (var i = 0; i < all.length; i++) {
				var el = all[i];
				// copiar atributos para iterar de forma segura
				var attrs = el.attributes ? Array.prototype.slice.call(el.attributes) : [];
				for (var j = 0; j < attrs.length; j++) {
					var a = attrs[j];
					if (!a || !a.name) continue;
					var nm = a.name.toLowerCase();

					// on* (onclick, onerror, …)
					if (nm.indexOf("on") === 0) {
						el.removeAttribute(a.name);
						continue;
					}

					// href/src/xlink:href con javascript:
					if (nm === "href" || nm === "src" || nm === "xlink:href") {
						var val = a.value || "";
						if (/^\s*javascript\s*:/i.test(val)) {
							el.removeAttribute(a.name);
							continue;
						}
					}

					// style con url(javascript:...)
					if (nm === "style") {
						var sv = a.value || "";
						if (/url\s*\(\s*javascript\s*:/i.test(sv)) {
							el.removeAttribute("style");
							continue;
						}
					}

					// iframe[srcdoc] potencial
					if (el.tagName === "IFRAME" && nm === "srcdoc") {
						el.removeAttribute("srcdoc");
						continue;
					}
				}
			}
		}
	};

	/* ========================================
	 * OVERLAY & BODY SCROLL
	 * ======================================== */
	const Overlay = {
		isOverlayNode: function (el) {
			if (!el) return false;
			if (el.tagName === "ASIDE" || el.tagName === "DIALOG") {
				var pos = getComputedStyle(el).position;
				if (pos === "fixed" || pos === "absolute") return true;
			}
			if (el.classList && (
				el.classList.contains("overlay") ||
				el.classList.contains("modal") ||
				el.classList.contains("w3-modal") ||
				el.classList.contains("w3-modal-2")
			)) return true;
			if (el.getAttribute && el.getAttribute("role") === "dialog") return true;
			return false;
		},

		// visible real: no display:none, no visibility:hidden y con cajas renderizadas
		isVisible: function (el) {
			if (!el) return false;
			var cs = getComputedStyle(el);
			if (!cs) return false;
			if (cs.display === "none") return false;
			if (cs.visibility === "hidden") return false;
			// getClientRects cubre position:fixed (offsetParent puede ser null)
			if (el.getClientRects && el.getClientRects().length === 0) return false;
			return true;
		},

		anyVisible: function () {
			var list = document.querySelectorAll(".overlay, [role=\"dialog\"], .modal, aside, dialog, .w3-modal, .w3-modal-2");
			for (var i = 0; i < list.length; i++) {
				if (Overlay.isVisible(list[i]) && Overlay.isOverlayNode(list[i])) return true;
			}
			return false;
		},

		lock: function () {
			if (document.body.style.overflow !== "hidden") {
				document.body.style.overflow = "hidden";
			}
		},

		unlockIfNone: function () {
			if (!Overlay.anyVisible()) {
				document.body.style.overflow = "auto";
			}
		}
	};

	/* ========================================
	 * EFECTOS VISUALES
	 * ======================================== */
	const FX = {
		saveState: function (el) {
			// si cambia el display respecto a lo guardado, actualiza
			var cs = getComputedStyle(el);
			if (!cs) return;
			var curDisp = cs.display;
			var saved = el.getAttribute("data-display");
			if (curDisp && curDisp !== "none" && saved !== curDisp) {
				el.setAttribute("data-display", curDisp);
			}
			var curOp = cs.opacity;
			var savedOp = el.getAttribute("data-opacity");
			if (curOp && savedOp !== curOp) {
				el.setAttribute("data-opacity", curOp);
			}
		},

		restoreState: function (el) {
			var disp = el.getAttribute("data-display");
			var opac = el.getAttribute("data-opacity");
			if (disp) el.style.display = disp; else el.style.display = "";
			if (opac) el.style.opacity = opac; else el.style.opacity = "";
		},

		show: function (el) {
			if (el.classList && el.classList.contains("hide")) el.classList.remove("hide");
			FX.restoreState(el);
			if (Overlay.isOverlayNode(el)) Overlay.lock();
			if (el.hasAttribute("data-autoscroll")) AutoScroll.force(el);
		},

		hide: function (el) {
			FX.saveState(el);
			if (el.classList && !el.classList.contains("hide")) el.classList.add("hide");
			el.style.display = "none";
			// no forzamos opacity si ya se guardó correctamente
			if (Overlay.isOverlayNode(el)) Overlay.unlockIfNone();
		},

		toggle: function (el) {
			var visible = getComputedStyle(el).display !== "none" && !(el.classList && el.classList.contains("hide"));
			var wasOverlay = Overlay.isOverlayNode(el) && visible;
			if (visible) FX.hide(el); else FX.show(el);
			if (wasOverlay) Overlay.unlockIfNone();
		},

		fadeOut: function (el, ms) {
			ms = ms == null ? 200 : ms;
			el.style.transition = "opacity " + ms + "ms";
			el.style.opacity = "1";
			requestAnimationFrame(function () {
				el.style.opacity = "0";
				setTimeout(function () {
					// setTimeout es el único temporizador usado aquí: si prefieres, elimina y usa solo hide() sin animación.
					FX.hide(el);
					el.style.transition = "";
				}, ms);
			});
		},

		slideDown: function (el, ms) {
			ms = ms == null ? 200 : ms;
			var cs = getComputedStyle(el);
			if (cs.display === "none" || (el.classList && el.classList.contains("hide"))) {
				if (el.classList && el.classList.contains("hide")) el.classList.remove("hide");
				FX.restoreState(el);
			}
			var h = el.scrollHeight;
			el.style.overflow = "hidden";
			el.style.maxHeight = "0";
			el.style.transition = "max-height " + ms + "ms ease";
			requestAnimationFrame(function () {
				el.style.maxHeight = h + "px";
				setTimeout(function () {
					el.style.maxHeight = "";
					el.style.overflow = "";
					el.style.transition = "";
					if (el.hasAttribute("data-autoscroll")) AutoScroll.force(el);
				}, ms);
			});
		}
	};

	/* ========================================
	 * AUTOSCROLL
	 * ======================================== */
	const AutoScroll = (function () {
		const pending = new WeakMap();

		function force(box) {
			if (!box) return;
			if (pending.has(box)) return;
			pending.set(box, true);
			requestAnimationFrame(function () {
				var beforeTop = box.scrollTop;
				var beforeLeft = box.scrollLeft;
				box.scrollTop = box.scrollHeight;
				box.scrollLeft = box.scrollWidth;
				log("autoscroll", Utils.selectorOf(box, ""), { top: beforeTop, left: beforeLeft }, "→", { top: box.scrollTop, left: box.scrollLeft });
				pending.delete(box);
			});
		}

		function setup(box) {
			if (!box || box.__kumbiaObs) return;
			var obs = new MutationObserver(function (muts) {
				for (var i = 0; i < muts.length; i++) {
					var m = muts[i];
					if (m.type === "childList" && m.addedNodes && m.addedNodes.length) {
						force(box);
						break;
					}
				}
			});
			try {
				obs.observe(box, { childList: true, subtree: true });
				box.__kumbiaObs = obs;
			} catch (e) {
				warn("autoscroll observer fail:", e && e.message ? e.message : e);
			}
			force(box);
		}

		function disconnect(node) {
			if (node && node.__kumbiaObs) {
				try { node.__kumbiaObs.disconnect(); } catch (_) { }
				delete node.__kumbiaObs;
			}
		}

		function scan(root) {
			root = root || document;
			var boxes = root.querySelectorAll("[data-autoscroll]");
			for (var i = 0; i < boxes.length; i++) setup(boxes[i]);
		}

		return { setup: setup, scan: scan, force: force, disconnect: disconnect };
	})();

	/* ========================================
	 * DEFERRED REMOVE
	 * ======================================== */
	const DeferredRemove = {
		apply: function (root) {
			root = root || document;
			var marks = root.querySelectorAll("[data-remove_id]");
			for (var i = 0; i < marks.length; i++) {
				var mk = marks[i];
				var id = mk.getAttribute("data-remove_id");
				if (id) {
					var objetivo = document.querySelector('[data-id="' + CSS.escape(id) + '"]');
					if (objetivo) {
						AutoScroll.disconnect(objetivo);
						objetivo.remove();
					}
				}
				mk.remove();
			}
			Overlay.unlockIfNone();
		}
	};

	const Handlers = {
		// GET AJAX en enlaces
		ajaxLink: async function (ev) {
			ev.preventDefault();

			// Si coexiste data-confirm, preguntar antes de ejecutar
			var msg = Utils.getData(this, "confirm");
			if (msg && !confirm(msg)) return;
			var href = this.href;
			var sel = Utils.requireSelector(Utils.getData(this, "ajax"), "data-ajax");
			if (!href || !sel) return;

			var target = document.querySelector(sel);
			if (!target) return;

			log("AJAX GET", Utils.selectorOf(target, sel), "→", href);
			try {
				var html = await Utils.fetch(href);
				target.innerHTML = "";
				target.appendChild(Utils.parseHTML(html));
				target.style.display = "";
				AutoScroll.force(target);
				if (Overlay.isOverlayNode(target)) Overlay.lock();
			} catch (e) {
				warn("AJAX falló:", e && e.message ? e.message : e);
			}
		},

		// POST AJAX en formularios (por botón submit)
		ajaxForm: async function (ev) {
			ev.preventDefault();

			var form = this.closest("form");
			if (!form) return;

			// limpiar inputs en contenedores ocultos, pero preservando hidden y [data-keep]
			Handlers.cleanHiddenInputs(form);

			var append = form.hasAttribute("data-ajax_append");
			var prepend = form.hasAttribute("data-ajax_prepend");
			var sel = Utils.requireSelector(
				form.getAttribute("data-ajax_append") ||
				form.getAttribute("data-ajax_prepend") ||
				form.getAttribute("data-ajax"),
				"form"
			);
			if (!sel) return;

			var target = document.querySelector(sel);
			if (!target) return;

			var raw = (form.getAttribute("action") || "").trim();
			var url = raw ? raw : (form.action || location.href);

			var fd = new FormData(form);
			var nm = this.getAttribute("name");
			if (nm !== null) fd.append(nm, this.value);

			var btns = form.querySelectorAll('[type="submit"]');
			for (var i = 0; i < btns.length; i++) btns[i].disabled = true;

			log("AJAX POST", Utils.selectorOf(target, sel), "→", url);
			try {
				var html = await Utils.fetch(url, { method: "POST", body: fd });
				var frag = Utils.parseHTML(html);

				if (append) {
					target.appendChild(frag);
				} else if (prepend) {
					target.insertBefore(frag, target.firstChild);
				} else {
					target.innerHTML = "";
					target.appendChild(frag);
				}
				target.style.display = "";
				AutoScroll.force(target);
				if (Overlay.isOverlayNode(target)) Overlay.lock();
			} catch (e) {
				warn("POST falló:", e && e.message ? e.message : e);
			} finally {
				for (var j = 0; j < btns.length; j++) btns[j].disabled = false;
			}
		},

		// Limpieza: inputs visibles vs ocultos
		cleanHiddenInputs: function (container) {
			// seleccionar contenedores ocultos por estilo/clase/atributo
			var hiddenNodes = container.querySelectorAll('[style*="display:none"], [style*="display: none"], .hide, [hidden]');
			for (var i = 0; i < hiddenNodes.length; i++) {
				var n = hiddenNodes[i];

				// borrar únicamente controles de formulario NO-whitelist dentro
				var controls = n.querySelectorAll("input, textarea, select");
				for (var k = 0; k < controls.length; k++) {
					var c = controls[k];
					var isHiddenType = (c.tagName === "INPUT" && (c.getAttribute("type") || "").toLowerCase() === "hidden");
					var keep = c.hasAttribute("data-keep");
					if (!isHiddenType && !keep) {
						c.remove();
					}
				}
			}
		},

		// Live search
		liveSearch: async function () {
			var href = Utils.getData(this, "href");
			var sel = Utils.requireSelector(Utils.getData(this, "live"), "data-live");
			if (!href || !sel) return;

			var target = document.querySelector(sel);
			if (!target) return;

			try {
				var html = await Utils.fetch(href, { params: { keywords: this.value } });
				target.innerHTML = "";
				target.appendChild(Utils.parseHTML(html));
				target.style.display = "";
			} catch (e) {
				warn("live falló:", e && e.message ? e.message : e);
			}
		},

		// Select con AJAX (base + segmento seguro)
		selectAjax: async function () {
			var base = Utils.getData(this, "href");
			var rawSel = Utils.getData(this, "ajax");
			var sel = Utils.requireSelector(rawSel, "select data-ajax");
			if (!base || !sel) return;

			var target = document.querySelector(sel);
			if (!target) return;

			var href = Utils.buildUrlWithSegment(base, this.value);

			log("SELECT AJAX", Utils.selectorOf(target, sel), href);
			try {
				var html = await Utils.fetch(href);
				target.innerHTML = "";
				target.appendChild(Utils.parseHTML(html));
				target.style.display = "";
			} catch (e) {
				warn("select ajax falló:", e && e.message ? e.message : e);
			}
		},

		// Efectos genéricos
		effect: function (name) {
			return function (ev) {
				// Prioridad crítica: Confirmación
				var msg = Utils.getData(this, "confirm");
				if (msg) {
					log("Confirmación requerida para", name, ":", msg);
					if (!confirm(msg)) {
						log("Acción cancelada por el usuario");
						if (ev) {
							if (ev.preventDefault) ev.preventDefault();
							if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
						}
						return;
					}
				}

				var raw = Utils.getData(this, name) || "";
				var nodes = raw.trim() ? document.querySelectorAll(raw) : [this];

				for (var i = 0; i < nodes.length; i++) {
					var el = nodes[i];
					if (name === "click") el.click();
					else if (name === "show") FX.show(el);
					else if (name === "hide") FX.hide(el);
					else if (name === "toggle") FX.toggle(el);
					else if (name === "fadeOut") FX.fadeOut(el);
					else if (name === "slideDown") FX.slideDown(el);
				}
			};
		},

		active: function () {
			var sel = Utils.getData(this, "active");
			var targets = [];
			if (sel) {
				targets = document.querySelectorAll(sel);
			} else {
				var cont = this.closest ? this.closest("nav, ul, ol, section, div") : null;
				if (cont && cont.querySelectorAll) {
					targets = cont.querySelectorAll("button, a, li > a, li > button");
				}
			}
			for (var i = 0; i < targets.length; i++) {
				targets[i].removeAttribute("aria-current");
				targets[i].classList.remove("active");
			}
			this.setAttribute("aria-current", "true");
			this.classList.add("active");
		},

		alert: function () { alert(Utils.getData(this, "alert")); },

		confirm: function (ev) {
			// Si el elemento también tiene un efecto, éste ya maneja el confirm internamente
			if (this.hasAttribute("data-hide") || this.hasAttribute("data-show") ||
				this.hasAttribute("data-toggle") || this.hasAttribute("data-fadeOut") ||
				this.hasAttribute("data-slideDown") || this.hasAttribute("data-remove")) return;
			if (!confirm(Utils.getData(this, "confirm"))) {
				ev.preventDefault();
				ev.stopImmediatePropagation();
			}
		},

		remove: function () {
			// Si coexiste data-confirm, preguntar antes de eliminar
			var msg = Utils.getData(this, "confirm");
			if (msg && !confirm(msg)) return;

			var raw = Utils.getData(this, "remove") || "";
			var parts = raw ? raw.split(",").map(function (s) { return s.trim(); }).filter(Boolean) : [];

			function rm(node) {
				AutoScroll.disconnect(node);
				node.remove();
			}

			if (!parts.length) {
				rm(this);
			} else {
				for (var i = 0; i < parts.length; i++) {
					var p = parts[i];
					var targets = [];
					if (p === "parent") {
						targets = this.parentElement ? [this.parentElement] : [];
					} else if (p === "parent parent") {
						var pp = this.parentElement;
						targets = pp && pp.parentElement ? [pp.parentElement] : [];
					} else {
						targets = document.querySelectorAll(p);
					}
					for (var j = 0; j < targets.length; j++) rm(targets[j]);
				}
			}
			Overlay.unlockIfNone();
		},

		toggleClass: function () {
			var raw = Utils.getData(this, "toggleClass") || "";
			var i = raw.indexOf(", ");
			if (i === -1) {
				var cn = raw.trim();
				if (cn) this.classList.toggle(cn);
				return;
			}
			var cn2 = raw.slice(0, i).trim();
			var sel = raw.slice(i + 2).trim();
			var list = document.querySelectorAll(sel);
			for (var k = 0; k < list.length; k++) list[k].classList.toggle(cn2);
		},

		selectRedirect: function () {
			var base = Utils.getData(this, "redirect") || "";
			if (!base) return;
			var href = Utils.buildUrlWithSegment(base, this.value);
			location.href = href;
		},

		style: function () {
			var raw = Utils.getData(this, "style") || "";
			var i = raw.indexOf(", ");
			if (i === -1) { this.setAttribute("style", raw); return; }
			var sel = raw.slice(0, i).trim();
			var css = raw.slice(i + 2);
			var list = document.querySelectorAll(sel);
			for (var k = 0; k < list.length; k++) list[k].setAttribute("style", css);
		},

		clone_append: function () {
			var raw = Utils.getData(this, "cloneAppend") || "";
			var i = raw.indexOf(", ");
			if (i === -1) {
				warn("clone_append: falta 'origen, destino'");
				return;
			}
			var src = raw.slice(0, i).trim();
			var dst = raw.slice(i + 2).trim();

			var el = document.querySelector(src);
			var to = document.querySelector(dst);
			if (el && to) to.appendChild(el.cloneNode(true));
		},

		toggleDisplay: function () {
			var sel = Utils.getData(this, "toggleDisplay");
			if (!(sel && sel.trim())) {
				warn("toggleDisplay: falta selector");
				return;
			}
			var nodes = document.querySelectorAll(sel);
			for (var i = 0; i < nodes.length; i++) {
				FX.toggle(nodes[i]);
			}
		},

		selectToggle: function () {
			var scopeSel = Utils.getData(this, "changeToggle");
			if (!(scopeSel && scopeSel.trim())) {
				warn("selectToggle: falta data-change_toggle");
				return;
			}
			var val = this.value;
			var target = document.querySelector(scopeSel + '[data-grp="' + CSS.escape(val) + '"]');
			if (!val || !target) return;

			// mostrar solo el target del grupo
			var all = document.querySelectorAll(scopeSel);
			for (var i = 0; i < all.length; i++) {
				if (all[i] === target) FX.show(all[i]);
				else FX.hide(all[i]);
			}
			// limpiar valores del target
			var inputs = target.querySelectorAll("input, textarea, select");
			for (var k = 0; k < inputs.length; k++) inputs[k].value = "";
		}
	};

	/* ========================================
	 * EVENTOS
	 * ======================================== */
	const Events = {
		bind: function () {
			var on = Utils.on;

			on("click", "a[data-ajax]", Handlers.ajaxLink);
			on("click", 'form[data-ajax] [type="submit"], form[data-ajax_append] [type="submit"], form[data-ajax_prepend] [type="submit"]', Handlers.ajaxForm);

			on("change", "select[data-ajax]", Handlers.selectAjax);
			on("keyup", "[data-live]", Handlers.liveSearch);
			on("change", "select[data-redirect]", Handlers.selectRedirect);

			on("click", "[data-active]", Handlers.active);
			on("click", "[data-alert]", Handlers.alert);
			on("click", "[data-confirm]", Handlers.confirm);
			on("click", "[data-remove]", Handlers.remove);

			on("click", "[data-toggle_class]", Handlers.toggleClass);
			on("click", "[data-style]", Handlers.style);
			on("click", "[data-clone_append]", Handlers.clone_append);
			on("click", "[data-toggle_display]", Handlers.toggleDisplay);

			on("click", "[data-show]", Handlers.effect("show"));
			on("click", "[data-hide]", Handlers.effect("hide"));
			on("click", "[data-toggle]", Handlers.effect("toggle"));
			on("click", "[data-fadeOut]", Handlers.effect("fadeOut"));
			on("click", "[data-slideDown]", Handlers.effect("slideDown"));
			on("click", "[data-click]", Handlers.effect("click"));
		}
	};

	/* ========================================
	 * OBSERVER GLOBAL
	 * ======================================== */
	var observer = null;
	const Observer = {
		init: function () {
			if (observer) {
				try { observer.disconnect(); } catch (_) { }
			}
			observer = new MutationObserver(function (muts) {
				for (var i = 0; i < muts.length; i++) {
					var m = muts[i];
					if (m.type !== "childList" || !m.addedNodes) continue;

					m.addedNodes.forEach(function (n) {
						if (!n || n.nodeType !== 1) return;

						// enganchar cajas autoscroll
						if (n.matches && n.matches("[data-autoscroll]")) AutoScroll.setup(n);
						var inside = n.querySelectorAll ? n.querySelectorAll("[data-autoscroll]") : [];
						for (var k = 0; k < inside.length; k++) AutoScroll.setup(inside[k]);

						// removals diferidos si entran marcas
						var hasRem =
							(n.matches && n.matches("[data-remove_id]")) ||
							(n.querySelectorAll && n.querySelectorAll("[data-remove_id]").length > 0);
						if (hasRem) DeferredRemove.apply();
					});
				}
			});
			try {
				observer.observe(document.documentElement, { childList: true, subtree: true });
			} catch (e) {
				warn("observer fail:", e && e.message ? e.message : e);
			}
		}
	};

	/* ========================================
	 * API
	 * ======================================== */
	const Kumbia = {
		version: "2.5.0",
		config: function (opts) { Object.assign(CONFIG, opts || {}); return Kumbia; },
		use: function (plugin) { if (typeof plugin === "function") plugin(Kumbia, Utils, FX); return Kumbia; },
		rescan: function () { AutoScroll.scan(); DeferredRemove.apply(); Observer.init(); return Kumbia; },
		destroy: function () { if (observer) { try { observer.disconnect(); } catch (_) { } } },
		utils: Utils,
		fx: FX,
		scroll: AutoScroll,
		remove: DeferredRemove
	};

	/* ========================================
	 * BOOT
	 * ======================================== */
	function boot() {
		Events.bind();
		AutoScroll.scan();
		DeferredRemove.apply();
		Observer.init();
		log("Kumbia.js v" + Kumbia.version + " ready");
	}

	global.Kumbia = Kumbia;
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})(window);


/* --- Start of 4-autoheight_textarea.js --- */
function textarea_auto_height(el) {
    if (el) {
        var scrollAncestors = [];
        var parent = el.parentNode;
        while (parent && parent !== document) {
            scrollAncestors.push({
                element: parent,
                scrollTop: parent.scrollTop,
                scrollLeft: parent.scrollLeft
            });
            parent = parent.parentNode;
        }
        var windowScrollX = window.scrollX;
        var windowScrollY = window.scrollY;

        el.style.height = 'auto';
        var style = window.getComputedStyle(el);
        var height = el.scrollHeight;
        if (style.boxSizing === 'border-box') {
            var borderTop = parseFloat(style.borderTopWidth) || 0;
            var borderBottom = parseFloat(style.borderBottomWidth) || 0;
            height += borderTop + borderBottom;
        }
        height = (height < 50) ? 50 : height;
        el.style.height = height + 'px';

        for (var i = 0; i < scrollAncestors.length; i++) {
            scrollAncestors[i].element.scrollTop = scrollAncestors[i].scrollTop;
            scrollAncestors[i].element.scrollLeft = scrollAncestors[i].scrollLeft;
        }
        window.scrollTo(windowScrollX, windowScrollY);
        return;
    }
    var els = document.querySelectorAll('textarea');
    for(var i=0; i<els.length; i++){
        textarea_auto_height(els[i]);
    }
}
textarea_auto_height();

Kumbia.utils.on('keyup', 'textarea', function() {
    textarea_auto_height(this);
});

// Detectar textareas nuevos insertados por AJAX
var textareaObserver = new MutationObserver(function(mutations) {
    var hasTextarea = false;
    for (var i = 0; i < mutations.length; i++) {
        var addedNodes = mutations[i].addedNodes;
        for (var j = 0; j < addedNodes.length; j++) {
            var node = addedNodes[j];
            if (node.nodeType === 1) {
                if (node.tagName === 'TEXTAREA' || node.querySelector('textarea')) {
                    hasTextarea = true;
                    break;
                }
            }
        }
        if (hasTextarea) break;
    }
    if (hasTextarea) {
        requestAnimationFrame(function() {
            textarea_auto_height();
        });
    }
});
textareaObserver.observe(document.body, { childList: true, subtree: true });

/* TAB Y SHIFT+TAB EN UN TEXTAREA */
Kumbia.utils.on('keydown', 'textarea', function(event) {
    if (event.keyCode === 9) {
        var v = this.value,
            s = this.selectionStart,
            e = this.selectionEnd;
        var linesStart = v.lastIndexOf('\n', s - 1) + 1;

        if (event.shiftKey) {
            // Shift+Tab: desindentar
            var selectedText = v.substring(linesStart, e);
            var lines = selectedText.split('\n');
            var removedCount = 0;
            var newText = lines.map(function(line, idx) {
                if (line.charAt(0) === '\t') {
                    if (idx === 0) removedCount = 1;
                    return line.substring(1);
                }
                return line;
            }).join('\n');
            var removed = lines.map(function(l) { return l.charAt(0) === '\t' ? 1 : 0; });
            this.value = v.substring(0, linesStart) + newText + v.substring(e);
            this.selectionStart = Math.max(linesStart, s - removed[0]);
            this.selectionEnd = e - removed.reduce(function(a, b) { return a + b; }, 0);
        } else {
            // Tab: indentar
            if (s === e) {
                this.value = v.substring(0, s) + '\t' + v.substring(e);
                this.selectionStart = this.selectionEnd = s + 1;
            } else {
                var selectedText2 = v.substring(linesStart, e);
                var lines2 = selectedText2.split('\n');
                var newText2 = lines2.map(function(line) { return '\t' + line; }).join('\n');
                this.value = v.substring(0, linesStart) + newText2 + v.substring(e);
                this.selectionStart = s + 1;
                this.selectionEnd = e + lines2.length;
            }
        }
        event.preventDefault();
    }
});



/* --- Start of 4-color_picker_ps.js --- */
/**
 * Photoshop-like color picker.
 *
 * Usage:
 * 1. Explicit target: <button class="web-color-picker" data-target="#input_hex"></button>
 * 2. Relative target: <button class="web-color-picker"></button>
 * 3. Manual target: psColorPickerTarget = myElement;
 */
const PS_COLOR_HISTORY_LIMIT = 20;
const PS_COLOR_HISTORY_KEY = 'roleplus_color_history';
const PS_COLOR_MAP_SIZE = 256;

let psColorPickerTarget = null;

document.addEventListener('DOMContentLoaded', initPSColorPicker);

function initPSColorPicker() {
    const picker = document.getElementById('ps-color-picker');
    if (!picker) return;

    const elements = {
        map: picker.querySelector('.map'),
        mapCanvas: picker.querySelector('.map canvas'),
        mapMarker: picker.querySelector('.map .marker'),
        hue: picker.querySelector('.hue'),
        hueMarker: picker.querySelector('.hue .marker'),
        currentPreview: picker.querySelector('.preview .curr'),
        nextPreview: picker.querySelector('.preview .next'),
        hexInput: picker.querySelector('.hex input'),
        webSafeInput: picker.querySelector('.web-safe input'),
        history: picker.querySelector('.history'),
        okButton: picker.querySelector('.actions .ok'),
        cancelButton: picker.querySelector('.actions .cancel'),
        valueInputs: picker.querySelectorAll('input[data-type]')
    };

    if (!elements.mapCanvas || !elements.map || !elements.hue) return;

    const valueInputs = Array.from(elements.valueInputs).reduce((inputs, input) => {
        inputs[input.dataset.type] = input;
        return inputs;
    }, {});
    const mapContext = elements.mapCanvas.getContext('2d', { alpha: false });
    let color = { h: 0, s: 100, b: 100 };
    let activeTrigger = null;
    let dragging = null;
    let history = readHistory();
    let lastRenderedHue = null;
    let lastRenderedWebSafe = null;

    function clamp(value, min, max) {
        const number = Number(value);
        if (!Number.isFinite(number)) return min;
        return Math.min(max, Math.max(min, number));
    }

    function clampChannel(value) {
        return Math.round(clamp(value, 0, 255));
    }

    function normalizeHex(value) {
        const hex = String(value || '').trim().replace(/^#/, '').toLowerCase();
        if (/^[0-9a-f]{3}$/.test(hex)) {
            return `#${hex.split('').map((char) => char + char).join('')}`;
        }
        if (/^[0-9a-f]{6}$/.test(hex)) {
            return `#${hex}`;
        }
        return null;
    }

    function readHistory() {
        try {
            const savedHistory = JSON.parse(localStorage.getItem(PS_COLOR_HISTORY_KEY) || '[]');
            if (!Array.isArray(savedHistory)) return [];

            return savedHistory
                .map(normalizeHex)
                .filter(Boolean)
                .slice(0, PS_COLOR_HISTORY_LIMIT);
        } catch (error) {
            return [];
        }
    }

    function saveHistory(hex) {
        history = [
            hex,
            ...history.filter((savedHex) => savedHex !== hex)
        ].slice(0, PS_COLOR_HISTORY_LIMIT);

        localStorage.setItem(PS_COLOR_HISTORY_KEY, JSON.stringify(history));
    }

    function hsbToRgb(h, s, brightness) {
        const safeHue = clamp(h, 0, 360);
        const hue = safeHue === 360 ? 0 : safeHue;
        const saturation = clamp(s, 0, 100) / 100;
        const value = clamp(brightness, 0, 100) / 100;
        const c = value * saturation;
        const x = c * (1 - Math.abs((hue / 60) % 2 - 1));
        const m = value - c;
        let r = 0;
        let g = 0;
        let b = 0;

        if (hue < 60) {
            r = c;
            g = x;
        } else if (hue < 120) {
            r = x;
            g = c;
        } else if (hue < 180) {
            g = c;
            b = x;
        } else if (hue < 240) {
            g = x;
            b = c;
        } else if (hue < 300) {
            r = x;
            b = c;
        } else {
            r = c;
            b = x;
        }

        return {
            r: clampChannel((r + m) * 255),
            g: clampChannel((g + m) * 255),
            b: clampChannel((b + m) * 255)
        };
    }

    function rgbToHsb(r, g, b) {
        const red = clampChannel(r) / 255;
        const green = clampChannel(g) / 255;
        const blue = clampChannel(b) / 255;
        const max = Math.max(red, green, blue);
        const min = Math.min(red, green, blue);
        const delta = max - min;
        let h = 0;

        if (delta !== 0) {
            if (max === red) {
                h = ((green - blue) / delta) % 6;
            } else if (max === green) {
                h = (blue - red) / delta + 2;
            } else {
                h = (red - green) / delta + 4;
            }
        }

        return {
            h: (h * 60 + 360) % 360,
            s: max === 0 ? 0 : (delta / max) * 100,
            b: max * 100
        };
    }

    function rgbToHex(r, g, b) {
        return `#${[r, g, b].map((channel) => clampChannel(channel).toString(16).padStart(2, '0')).join('')}`;
    }

    function hexToRgb(hex) {
        const normalizedHex = normalizeHex(hex);
        if (!normalizedHex) return null;

        return {
            r: parseInt(normalizedHex.slice(1, 3), 16),
            g: parseInt(normalizedHex.slice(3, 5), 16),
            b: parseInt(normalizedHex.slice(5, 7), 16)
        };
    }

    function snapToWeb(value) {
        return Math.round(clampChannel(value) / 51) * 51;
    }

    function webSafeEnabled() {
        return Boolean(elements.webSafeInput?.checked);
    }

    function getSelectedRgb() {
        const rgb = hsbToRgb(color.h, color.s, color.b);
        if (!webSafeEnabled()) return rgb;

        return {
            r: snapToWeb(rgb.r),
            g: snapToWeb(rgb.g),
            b: snapToWeb(rgb.b)
        };
    }

    function getSelectedHex() {
        const rgb = getSelectedRgb();
        return rgbToHex(rgb.r, rgb.g, rgb.b);
    }

    function setColorFromRgb(rgb) {
        const hsb = rgbToHsb(rgb.r, rgb.g, rgb.b);
        color = {
            h: hsb.h,
            s: hsb.s,
            b: hsb.b
        };
    }

    function setColorFromHex(hex) {
        const rgb = hexToRgb(hex);
        if (!rgb) return false;

        setColorFromRgb(rgb);
        return true;
    }

    function drawMap() {
        const isWebSafe = webSafeEnabled();
        if (color.h === lastRenderedHue && isWebSafe === lastRenderedWebSafe) return;

        lastRenderedHue = color.h;
        lastRenderedWebSafe = isWebSafe;

        const imageData = mapContext.createImageData(PS_COLOR_MAP_SIZE, PS_COLOR_MAP_SIZE);
        const data = imageData.data;

        for (let y = 0; y < PS_COLOR_MAP_SIZE; y++) {
            const brightness = 100 - (y / (PS_COLOR_MAP_SIZE - 1)) * 100;

            for (let x = 0; x < PS_COLOR_MAP_SIZE; x++) {
                const saturation = (x / (PS_COLOR_MAP_SIZE - 1)) * 100;
                const rgb = hsbToRgb(color.h, saturation, brightness);
                const index = (y * PS_COLOR_MAP_SIZE + x) * 4;

                data[index] = isWebSafe ? snapToWeb(rgb.r) : rgb.r;
                data[index + 1] = isWebSafe ? snapToWeb(rgb.g) : rgb.g;
                data[index + 2] = isWebSafe ? snapToWeb(rgb.b) : rgb.b;
                data[index + 3] = 255;
            }
        }

        mapContext.putImageData(imageData, 0, 0);
    }

    function renderHistory() {
        elements.history.replaceChildren();

        history.forEach((hex) => {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.title = hex;
            swatch.setAttribute('aria-label', hex);
            swatch.style.backgroundColor = hex;
            swatch.addEventListener('click', () => {
                setColorFromHex(hex);
                updateUI();
            });
            elements.history.appendChild(swatch);
        });
    }

    function updateValueInputs() {
        const rgb = getSelectedRgb();

        valueInputs.h.value = Math.round(color.h);
        valueInputs.s.value = Math.round(color.s);
        valueInputs.b.value = Math.round(color.b);
        valueInputs.r.value = rgb.r;
        valueInputs.g.value = rgb.g;
        valueInputs.b_rgb.value = rgb.b;
        elements.hexInput.value = getSelectedHex().replace('#', '');
    }

    function updateUI(options = {}) {
        const preserveInputs = Boolean(options.preserveInputs);
        const hex = getSelectedHex();

        drawMap();
        elements.mapMarker.style.left = `${color.s}%`;
        elements.mapMarker.style.top = `${100 - color.b}%`;
        elements.hueMarker.style.top = `${(color.h / 360) * 100}%`;
        elements.nextPreview.style.backgroundColor = hex;

        if (!preserveInputs) {
            updateValueInputs();
        }

        renderHistory();
    }

    function handleMap(event) {
        const rect = elements.map.getBoundingClientRect();
        color.s = clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100);
        color.b = clamp(100 - ((event.clientY - rect.top) / rect.height) * 100, 0, 100);
        updateUI();
    }

    function handleHue(event) {
        const rect = elements.hue.getBoundingClientRect();
        color.h = clamp(((event.clientY - rect.top) / rect.height) * 360, 0, 360);
        updateUI();
    }

    function resolveTarget(trigger) {
        if (trigger.dataset.target) {
            try {
                return document.querySelector(trigger.dataset.target);
            } catch (error) {
                return null;
            }
        }

        return trigger.closest('section')?.querySelector('input[type="text"]') || trigger;
    }

    function cssColorToHex(cssColor) {
        if (!cssColor || cssColor === 'transparent') return null;

        const normalizedHex = normalizeHex(cssColor);
        if (normalizedHex) return normalizedHex;

        const probe = document.createElement('div');
        probe.style.color = cssColor;
        document.body.appendChild(probe);
        const computedColor = window.getComputedStyle(probe).color;
        document.body.removeChild(probe);

        const channels = computedColor.match(/[\d.]+/g);
        if (!channels || channels.length < 3 || Number(channels[3]) === 0) return null;

        return rgbToHex(channels[0], channels[1], channels[2]);
    }

    function getInitialHex(trigger, target) {
        if (target?.tagName === 'INPUT') {
            const targetHex = normalizeHex(target.value);
            if (targetHex) return targetHex;
        }

        return cssColorToHex(trigger.style.backgroundColor)
            || cssColorToHex(window.getComputedStyle(trigger).backgroundColor)
            || '#ffffff';
    }

    function openPicker(trigger) {
        activeTrigger = trigger;
        psColorPickerTarget = resolveTarget(trigger);

        const initialHex = getInitialHex(trigger, psColorPickerTarget);
        elements.currentPreview.style.backgroundColor = initialHex;
        setColorFromHex(initialHex);
        picker.style.display = 'block';
        updateUI();
    }

    function saveSelectedColor() {
        const hex = getSelectedHex();
        const target = psColorPickerTarget || activeTrigger;

        if (target?.tagName === 'INPUT') {
            target.value = hex;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (activeTrigger?.classList.contains('web-color-picker')) {
            activeTrigger.style.backgroundColor = hex;
        }

        saveHistory(hex);

        if (target) {
            target.dispatchEvent(new CustomEvent('ps-color-saved', {
                detail: { hex, target },
                bubbles: true
            }));
        }

        picker.style.display = 'none';
    }

    picker.addEventListener('mousedown', (event) => {
        if (event.target.closest('.map')) {
            dragging = 'map';
            handleMap(event);
        } else if (event.target.closest('.hue')) {
            dragging = 'hue';
            handleHue(event);
        }
    });

    document.addEventListener('mousemove', (event) => {
        if (dragging === 'map') {
            handleMap(event);
        } else if (dragging === 'hue') {
            handleHue(event);
        }
    });

    document.addEventListener('mouseup', () => {
        dragging = null;
    });

    elements.valueInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const type = input.dataset.type;

            if (type === 'h') {
                color.h = clamp(input.value, 0, 360);
            } else if (type === 's') {
                color.s = clamp(input.value, 0, 100);
            } else if (type === 'b') {
                color.b = clamp(input.value, 0, 100);
            } else {
                setColorFromRgb({
                    r: valueInputs.r.value,
                    g: valueInputs.g.value,
                    b: valueInputs.b_rgb.value
                });
            }

            updateUI({ preserveInputs: true });
        });

        input.addEventListener('change', () => updateUI());
    });

    elements.hexInput.addEventListener('input', () => {
        if (setColorFromHex(elements.hexInput.value)) {
            updateUI({ preserveInputs: true });
        }
    });

    elements.hexInput.addEventListener('change', () => updateUI());
    elements.webSafeInput.addEventListener('change', () => updateUI());

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.web-color-picker');
        if (!trigger) return;

        openPicker(trigger);
        event.stopPropagation();
    });

    elements.okButton.addEventListener('click', saveSelectedColor);
    elements.cancelButton.addEventListener('click', () => {
        picker.style.display = 'none';
    });

    updateUI();
}


/* --- Start of 4-drop-or-paste-image.js --- */
(function () {
    const renderPreview = (img_box, result) => {
        const imgs = img_box.querySelectorAll(':scope > img');
        for (let i = 0; i < imgs.length; i++) imgs[i].remove();
        img_box.style.backgroundImage = 'url(' + result + ')';
        img_box.classList.add('dropnocontent');
    };

    const cloneIfNeeded = (container) => {
        if (!container || !container.classList.contains('multiple')) return;
        const box_container = container.parentElement;
        const images = document.querySelectorAll('.modal .dropimage').length;
        if (images < 4) {
            const other = container.cloneNode(true);
            const otherDrop = other.querySelector('.dropimage') || (other.classList.contains('dropimage') ? other : null);
            if (otherDrop) {
                otherDrop.classList.remove('dropimagehover', 'dropnocontent');
                otherDrop.removeAttribute('style');
                const otherInput = otherDrop.querySelector('[type="file"]');
                if (otherInput) otherInput.value = '';
            }
            if (box_container) box_container.appendChild(other);
        }
    };

    Kumbia.utils.on('paste', 'textarea', function (eve) {
        const clipboard = (eve.clipboardData || window.clipboardData);
        if (!clipboard || !clipboard.files || !clipboard.files.length) return;

        const form = this.closest('form');
        if (!form) return;
        const img_boxes = form.querySelectorAll('.dropimage');
        if (!img_boxes.length) return;

        const img_box = img_boxes[img_boxes.length - 1];
        const input = img_box.querySelector('[type="file"]');
        if (input) {
            input.files = clipboard.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(img_box, reader.result);
        reader.readAsDataURL(clipboard.files[0]);
        cloneIfNeeded(img_box.parentElement);
    });

    Kumbia.utils.on('change', '.dropimage [type="file"]', function () {
        const img_box = this.closest('.dropimage');
        if (!img_box || !this.files || !this.files[0]) return;

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(img_box, reader.result);
        reader.readAsDataURL(this.files[0]);
        cloneIfNeeded(img_box.parentElement);
    });

    Kumbia.utils.on('click', '.dropimage button, .dropimage .quitar', function (eve) {
        eve.preventDefault();
        eve.stopPropagation();

        const img_box = this.closest('.dropimage');
        const container = img_box ? img_box.parentElement : null;
        let clean = 0;

        if (container && container.classList.contains('multiple')) {
            const images = document.querySelectorAll('.modal .dropimage').length;
            if (images > 1) {
                container.remove();
            } else {
                clean = 1;
            }

            const empty_box = document.querySelectorAll('.modal .dropimage:not(.dropnocontent)').length;
            if (empty_box < 1 && images < 5) {
                const new_box = container.cloneNode(true);
                if (container.parentElement) container.parentElement.appendChild(new_box);
                const new_img_box = new_box.querySelector('.dropimage') || (new_box.classList.contains('dropimage') ? new_box : null);
                if (new_img_box) {
                    new_img_box.removeAttribute('style');
                    new_img_box.classList.remove('dropimagehover', 'dropnocontent');
                    const inp = new_img_box.querySelector('input');
                    if (inp) inp.value = '';
                }
            }
        } else {
            clean = 1;
        }

        if (clean === 1 && img_box) {
            img_box.removeAttribute('style');
            img_box.classList.remove('dropimagehover', 'dropnocontent');
            const imgs = img_box.querySelectorAll(':scope > img');
            for (let i = 0; i < imgs.length; i++) imgs[i].remove();
            const inp = img_box.querySelector('input');
            if (inp) inp.value = '';
        }
    });

    Kumbia.utils.on('dragenter dragover', '.dropimage', function (eve) {
        eve.preventDefault();
        this.classList.add('dropimagehover');
    });

    Kumbia.utils.on('dragleave', '.dropimage', function () {
        this.classList.remove('dropimagehover');
    });

    Kumbia.utils.on('drop', '.dropimage', function (eve) {
        eve.preventDefault();
        eve.stopPropagation();
        this.classList.remove('dropimagehover');

        const files = eve.dataTransfer && eve.dataTransfer.files;
        if (!files || !files.length) return;

        const input = this.querySelector('[type="file"]');
        if (input) {
            try {
                input.files = files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                console.warn('[dropimage] Could not set input.files:', e);
            }
        }

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(this, reader.result);
        reader.readAsDataURL(files[0]);
        cloneIfNeeded(this.parentElement);
    });
})();


/* --- Start of 4-filter.js --- */
/* INPUT LIVE FILTER ACCENTS */
window.replaceAccents = function (q) {
    q = q.replace(/[eéèêëEÉÈÊË]/gi, '[E]');
    q = q.replace(/[aàâäAÀÁÂÃÄÅÆ]/gi, '[A]');
    q = q.replace(/[cçC]/gi, '[C]');
    q = q.replace(/[iïîIÌÍÎÏ]/gi, '[I]');
    q = q.replace(/[oôöÒÓÔÕÖ]/gi, '[O]');
    q = q.replace(/[uüûUÜÛÙÚ]/gi, '[U]');
    q = q.replace(/[yYÿÝ]/gi, '[Y]');
    return q;
};

/* INPUT LIVE FILTER */
Kumbia.utils.on('keyup', '[data-filter]', function () {
    var itemSel = Kumbia.utils.getData(this, 'filter');
    var search = window.replaceAccents(this.value).toUpperCase();
    var items = document.querySelectorAll(itemSel);

    for (var i = 0; i < items.length; i++) {
        var q = window.replaceAccents(items[i].textContent || items[i].innerText).toUpperCase();
        if (q.indexOf(search) >= 0) Kumbia.fx.show(items[i]);
        else Kumbia.fx.hide(items[i]);
    }
});


/* --- Start of 4-scroll.js --- */
var currentScrollPos, prevScrollpos = window.pageYOffset,
    stop = 0;
window.onscroll = function () {
    if (window.pageYOffset == 0) {
        showElements('nav, .scroll-down-hide', true);
    } else if (stop == 0) {
        stop = 1;
        currentScrollPos = window.pageYOffset;
        if (prevScrollpos > currentScrollPos) {
            showElements('nav, .scroll-down-hide', true);
            stop = 0;
        } else {
            showElements('nav, .scroll-down-hide', false);
            stop = 0;
        }
        prevScrollpos = currentScrollPos;
    }
}
function showElements(selector, show) {
    var els = document.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
        if (show) Kumbia.fx.show(els[i]);
        else Kumbia.fx.hide(els[i]);
    }
}

Kumbia.utils.on('click', '.scroll-top', function () {
    window.scrollTo(0, 0)
});

/* Scroll to body: On */
Kumbia.utils.on('click', '[data-ajax]:not([data-style]), [data-hide*="overlay"], .overlay', function (eve) {
    document.body.style.overflow = 'auto';
});

/* Scroll to body: Off 
    In button or link set the attr: data-style="body, overflow:hidden"
*/


/* --- Start of 4-sortable.js --- */
/**!
 * Sortable 1.15.7
 * @author	RubaXa   <trash@rubaxa.org>
 * @author	owenm    <owen23355@gmail.com>
 * @license MIT
 */
(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
        typeof define === 'function' && define.amd ? define(factory) :
            (global = global || self, global.Sortable = factory());
}(this, (function () {
    'use strict';

    function _arrayLikeToArray(r, a) {
        (null == a || a > r.length) && (a = r.length);
        for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e];
        return n;
    }
    function _arrayWithoutHoles(r) {
        if (Array.isArray(r)) return _arrayLikeToArray(r);
    }
    function _defineProperty(e, r, t) {
        return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, {
            value: t,
            enumerable: !0,
            configurable: !0,
            writable: !0
        }) : e[r] = t, e;
    }
    function _extends() {
        return _extends = Object.assign ? Object.assign.bind() : function (n) {
            for (var e = 1; e < arguments.length; e++) {
                var t = arguments[e];
                for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]);
            }
            return n;
        }, _extends.apply(null, arguments);
    }
    function _iterableToArray(r) {
        if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r);
    }
    function _nonIterableSpread() {
        throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
    }
    function ownKeys(e, r) {
        var t = Object.keys(e);
        if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(e);
            r && (o = o.filter(function (r) {
                return Object.getOwnPropertyDescriptor(e, r).enumerable;
            })), t.push.apply(t, o);
        }
        return t;
    }
    function _objectSpread2(e) {
        for (var r = 1; r < arguments.length; r++) {
            var t = null != arguments[r] ? arguments[r] : {};
            r % 2 ? ownKeys(Object(t), !0).forEach(function (r) {
                _defineProperty(e, r, t[r]);
            }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) {
                Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r));
            });
        }
        return e;
    }
    function _objectWithoutProperties(e, t) {
        if (null == e) return {};
        var o,
            r,
            i = _objectWithoutPropertiesLoose(e, t);
        if (Object.getOwnPropertySymbols) {
            var n = Object.getOwnPropertySymbols(e);
            for (r = 0; r < n.length; r++) o = n[r], -1 === t.indexOf(o) && {}.propertyIsEnumerable.call(e, o) && (i[o] = e[o]);
        }
        return i;
    }
    function _objectWithoutPropertiesLoose(r, e) {
        if (null == r) return {};
        var t = {};
        for (var n in r) if ({}.hasOwnProperty.call(r, n)) {
            if (-1 !== e.indexOf(n)) continue;
            t[n] = r[n];
        }
        return t;
    }
    function _toConsumableArray(r) {
        return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread();
    }
    function _toPrimitive(t, r) {
        if ("object" != typeof t || !t) return t;
        var e = t[Symbol.toPrimitive];
        if (void 0 !== e) {
            var i = e.call(t, r || "default");
            if ("object" != typeof i) return i;
            throw new TypeError("@@toPrimitive must return a primitive value.");
        }
        return ("string" === r ? String : Number)(t);
    }
    function _toPropertyKey(t) {
        var i = _toPrimitive(t, "string");
        return "symbol" == typeof i ? i : i + "";
    }
    function _typeof(o) {
        "@babel/helpers - typeof";

        return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
            return typeof o;
        } : function (o) {
            return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
        }, _typeof(o);
    }
    function _unsupportedIterableToArray(r, a) {
        if (r) {
            if ("string" == typeof r) return _arrayLikeToArray(r, a);
            var t = {}.toString.call(r).slice(8, -1);
            return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0;
        }
    }

    var version = "1.15.7";

    function userAgent(pattern) {
        if (typeof window !== 'undefined' && window.navigator) {
            return !! /*@__PURE__*/navigator.userAgent.match(pattern);
        }
    }
    var IE11OrLess = userAgent(/(?:Trident.*rv[ :]?11\.|msie|iemobile|Windows Phone)/i);
    var Edge = userAgent(/Edge/i);
    var FireFox = userAgent(/firefox/i);
    var Safari = userAgent(/safari/i) && !userAgent(/chrome/i) && !userAgent(/android/i);
    var IOS = userAgent(/iP(ad|od|hone)/i);
    var ChromeForAndroid = userAgent(/chrome/i) && userAgent(/android/i);

    var captureMode = {
        capture: false,
        passive: false
    };
    function on(el, event, fn) {
        el.addEventListener(event, fn, !IE11OrLess && captureMode);
    }
    function off(el, event, fn) {
        el.removeEventListener(event, fn, !IE11OrLess && captureMode);
    }
    function matches( /**HTMLElement*/el, /**String*/selector) {
        if (!selector) return;
        selector[0] === '>' && (selector = selector.substring(1));
        if (el) {
            try {
                if (el.matches) {
                    return el.matches(selector);
                } else if (el.msMatchesSelector) {
                    return el.msMatchesSelector(selector);
                } else if (el.webkitMatchesSelector) {
                    return el.webkitMatchesSelector(selector);
                }
            } catch (_) {
                return false;
            }
        }
        return false;
    }
    function getParentOrHost(el) {
        return el.host && el !== document && el.host.nodeType && el.host !== el ? el.host : el.parentNode;
    }
    function closest( /**HTMLElement*/el, /**String*/selector, /**HTMLElement*/ctx, includeCTX) {
        if (el) {
            ctx = ctx || document;
            do {
                if (selector != null && (selector[0] === '>' ? el.parentNode === ctx && matches(el, selector) : matches(el, selector)) || includeCTX && el === ctx) {
                    return el;
                }
                if (el === ctx) break;
                /* jshint boss:true */
            } while (el = getParentOrHost(el));
        }
        return null;
    }
    var R_SPACE = /\s+/g;
    function toggleClass(el, name, state) {
        if (el && name) {
            if (el.classList) {
                el.classList[state ? 'add' : 'remove'](name);
            } else {
                var className = (' ' + el.className + ' ').replace(R_SPACE, ' ').replace(' ' + name + ' ', ' ');
                el.className = (className + (state ? ' ' + name : '')).replace(R_SPACE, ' ');
            }
        }
    }
    function css(el, prop, val) {
        var style = el && el.style;
        if (style) {
            if (val === void 0) {
                if (document.defaultView && document.defaultView.getComputedStyle) {
                    val = document.defaultView.getComputedStyle(el, '');
                } else if (el.currentStyle) {
                    val = el.currentStyle;
                }
                return prop === void 0 ? val : val[prop];
            } else {
                if (!(prop in style) && prop.indexOf('webkit') === -1) {
                    prop = '-webkit-' + prop;
                }
                style[prop] = val + (typeof val === 'string' ? '' : 'px');
            }
        }
    }
    function matrix(el, selfOnly) {
        var appliedTransforms = '';
        if (typeof el === 'string') {
            appliedTransforms = el;
        } else {
            do {
                var transform = css(el, 'transform');
                if (transform && transform !== 'none') {
                    appliedTransforms = transform + ' ' + appliedTransforms;
                }
                /* jshint boss:true */
            } while (!selfOnly && (el = el.parentNode));
        }
        var matrixFn = window.DOMMatrix || window.WebKitCSSMatrix || window.CSSMatrix || window.MSCSSMatrix;
        /*jshint -W056 */
        return matrixFn && new matrixFn(appliedTransforms);
    }
    function find(ctx, tagName, iterator) {
        if (ctx) {
            var list = ctx.getElementsByTagName(tagName),
                i = 0,
                n = list.length;
            if (iterator) {
                for (; i < n; i++) {
                    iterator(list[i], i);
                }
            }
            return list;
        }
        return [];
    }
    function getWindowScrollingElement() {
        var scrollingElement = document.scrollingElement;
        if (scrollingElement) {
            return scrollingElement;
        } else {
            return document.documentElement;
        }
    }

    /**
     * Returns the "bounding client rect" of given element
     * @param  {HTMLElement} el                       The element whose boundingClientRect is wanted
     * @param  {[Boolean]} relativeToContainingBlock  Whether the rect should be relative to the containing block of (including) the container
     * @param  {[Boolean]} relativeToNonStaticParent  Whether the rect should be relative to the relative parent of (including) the contaienr
     * @param  {[Boolean]} undoScale                  Whether the container's scale() should be undone
     * @param  {[HTMLElement]} container              The parent the element will be placed in
     * @return {Object}                               The boundingClientRect of el, with specified adjustments
     */
    function getRect(el, relativeToContainingBlock, relativeToNonStaticParent, undoScale, container) {
        if (!el.getBoundingClientRect && el !== window) return;
        var elRect, top, left, bottom, right, height, width;
        if (el !== window && el.parentNode && el !== getWindowScrollingElement()) {
            elRect = el.getBoundingClientRect();
            top = elRect.top;
            left = elRect.left;
            bottom = elRect.bottom;
            right = elRect.right;
            height = elRect.height;
            width = elRect.width;
        } else {
            top = 0;
            left = 0;
            bottom = window.innerHeight;
            right = window.innerWidth;
            height = window.innerHeight;
            width = window.innerWidth;
        }
        if ((relativeToContainingBlock || relativeToNonStaticParent) && el !== window) {
            // Adjust for translate()
            container = container || el.parentNode;

            // solves #1123 (see: https://stackoverflow.com/a/37953806/6088312)
            // Not needed on <= IE11
            if (!IE11OrLess) {
                do {
                    if (container && container.getBoundingClientRect && (css(container, 'transform') !== 'none' || relativeToNonStaticParent && css(container, 'position') !== 'static')) {
                        var containerRect = container.getBoundingClientRect();

                        // Set relative to edges of padding box of container
                        top -= containerRect.top + parseInt(css(container, 'border-top-width'));
                        left -= containerRect.left + parseInt(css(container, 'border-left-width'));
                        bottom = top + elRect.height;
                        right = left + elRect.width;
                        break;
                    }
                    /* jshint boss:true */
                } while (container = container.parentNode);
            }
        }
        if (undoScale && el !== window) {
            // Adjust for scale()
            var elMatrix = matrix(container || el),
                scaleX = elMatrix && elMatrix.a,
                scaleY = elMatrix && elMatrix.d;
            if (elMatrix) {
                top /= scaleY;
                left /= scaleX;
                width /= scaleX;
                height /= scaleY;
                bottom = top + height;
                right = left + width;
            }
        }
        return {
            top: top,
            left: left,
            bottom: bottom,
            right: right,
            width: width,
            height: height
        };
    }

    /**
     * Checks if a side of an element is scrolled past a side of its parents
     * @param  {HTMLElement}  el           The element who's side being scrolled out of view is in question
     * @param  {String}       elSide       Side of the element in question ('top', 'left', 'right', 'bottom')
     * @param  {String}       parentSide   Side of the parent in question ('top', 'left', 'right', 'bottom')
     * @return {HTMLElement}               The parent scroll element that the el's side is scrolled past, or null if there is no such element
     */
    function isScrolledPast(el, elSide, parentSide) {
        var parent = getParentAutoScrollElement(el, true),
            elSideVal = getRect(el)[elSide];

        /* jshint boss:true */
        while (parent) {
            var parentSideVal = getRect(parent)[parentSide],
                visible = void 0;
            if (parentSide === 'top' || parentSide === 'left') {
                visible = elSideVal >= parentSideVal;
            } else {
                visible = elSideVal <= parentSideVal;
            }
            if (!visible) return parent;
            if (parent === getWindowScrollingElement()) break;
            parent = getParentAutoScrollElement(parent, false);
        }
        return false;
    }

    /**
     * Gets nth child of el, ignoring hidden children, sortable's elements (does not ignore clone if it's visible)
     * and non-draggable elements
     * @param  {HTMLElement} el       The parent element
     * @param  {Number} childNum      The index of the child
     * @param  {Object} options       Parent Sortable's options
     * @return {HTMLElement}          The child at index childNum, or null if not found
     */
    function getChild(el, childNum, options, includeDragEl) {
        var currentChild = 0,
            i = 0,
            children = el.children;
        while (i < children.length) {
            if (children[i].style.display !== 'none' && children[i] !== Sortable.ghost && (includeDragEl || children[i] !== Sortable.dragged) && closest(children[i], options.draggable, el, false)) {
                if (currentChild === childNum) {
                    return children[i];
                }
                currentChild++;
            }
            i++;
        }
        return null;
    }

    /**
     * Gets the last child in the el, ignoring ghostEl or invisible elements (clones)
     * @param  {HTMLElement} el       Parent element
     * @param  {selector} selector    Any other elements that should be ignored
     * @return {HTMLElement}          The last child, ignoring ghostEl
     */
    function lastChild(el, selector) {
        var last = el.lastElementChild;
        while (last && (last === Sortable.ghost || css(last, 'display') === 'none' || selector && !matches(last, selector))) {
            last = last.previousElementSibling;
        }
        return last || null;
    }

    /**
     * Returns the index of an element within its parent for a selected set of
     * elements
     * @param  {HTMLElement} el
     * @param  {selector} selector
     * @return {number}
     */
    function index(el, selector) {
        var index = 0;
        if (!el || !el.parentNode) {
            return -1;
        }

        /* jshint boss:true */
        while (el = el.previousElementSibling) {
            if (el.nodeName.toUpperCase() !== 'TEMPLATE' && el !== Sortable.clone && (!selector || matches(el, selector))) {
                index++;
            }
        }
        return index;
    }

    /**
     * Returns the scroll offset of the given element, added with all the scroll offsets of parent elements.
     * The value is returned in real pixels.
     * @param  {HTMLElement} el
     * @return {Array}             Offsets in the format of [left, top]
     */
    function getRelativeScrollOffset(el) {
        var offsetLeft = 0,
            offsetTop = 0,
            winScroller = getWindowScrollingElement();
        if (el) {
            do {
                var elMatrix = matrix(el),
                    scaleX = elMatrix.a,
                    scaleY = elMatrix.d;
                offsetLeft += el.scrollLeft * scaleX;
                offsetTop += el.scrollTop * scaleY;
            } while (el !== winScroller && (el = el.parentNode));
        }
        return [offsetLeft, offsetTop];
    }

    /**
     * Returns the index of the object within the given array
     * @param  {Array} arr   Array that may or may not hold the object
     * @param  {Object} obj  An object that has a key-value pair unique to and identical to a key-value pair in the object you want to find
     * @return {Number}      The index of the object in the array, or -1
     */
    function indexOfObject(arr, obj) {
        for (var i in arr) {
            if (!arr.hasOwnProperty(i)) continue;
            for (var key in obj) {
                if (obj.hasOwnProperty(key) && obj[key] === arr[i][key]) return Number(i);
            }
        }
        return -1;
    }
    function getParentAutoScrollElement(el, includeSelf) {
        // skip to window
        if (!el || !el.getBoundingClientRect) return getWindowScrollingElement();
        var elem = el;
        var gotSelf = false;
        do {
            // we don't need to get elem css if it isn't even overflowing in the first place (performance)
            if (elem.clientWidth < elem.scrollWidth || elem.clientHeight < elem.scrollHeight) {
                var elemCSS = css(elem);
                if (elem.clientWidth < elem.scrollWidth && (elemCSS.overflowX == 'auto' || elemCSS.overflowX == 'scroll') || elem.clientHeight < elem.scrollHeight && (elemCSS.overflowY == 'auto' || elemCSS.overflowY == 'scroll')) {
                    if (!elem.getBoundingClientRect || elem === document.body) return getWindowScrollingElement();
                    if (gotSelf || includeSelf) return elem;
                    gotSelf = true;
                }
            }
            /* jshint boss:true */
        } while (elem = elem.parentNode);
        return getWindowScrollingElement();
    }
    function extend(dst, src) {
        if (dst && src) {
            for (var key in src) {
                if (src.hasOwnProperty(key)) {
                    dst[key] = src[key];
                }
            }
        }
        return dst;
    }
    function isRectEqual(rect1, rect2) {
        return Math.round(rect1.top) === Math.round(rect2.top) && Math.round(rect1.left) === Math.round(rect2.left) && Math.round(rect1.height) === Math.round(rect2.height) && Math.round(rect1.width) === Math.round(rect2.width);
    }
    var _throttleTimeout;
    function throttle(callback, ms) {
        return function () {
            if (!_throttleTimeout) {
                var args = arguments,
                    _this = this;
                if (args.length === 1) {
                    callback.call(_this, args[0]);
                } else {
                    callback.apply(_this, args);
                }
                _throttleTimeout = setTimeout(function () {
                    _throttleTimeout = void 0;
                }, ms);
            }
        };
    }
    function cancelThrottle() {
        clearTimeout(_throttleTimeout);
        _throttleTimeout = void 0;
    }
    function scrollBy(el, x, y) {
        el.scrollLeft += x;
        el.scrollTop += y;
    }
    function clone(el) {
        var Polymer = window.Polymer;
        var $ = window.jQuery || window.Zepto;
        if (Polymer && Polymer.dom) {
            return Polymer.dom(el).cloneNode(true);
        } else if ($) {
            return $(el).clone(true)[0];
        } else {
            return el.cloneNode(true);
        }
    }
    function setRect(el, rect) {
        css(el, 'position', 'absolute');
        css(el, 'top', rect.top);
        css(el, 'left', rect.left);
        css(el, 'width', rect.width);
        css(el, 'height', rect.height);
    }
    function unsetRect(el) {
        css(el, 'position', '');
        css(el, 'top', '');
        css(el, 'left', '');
        css(el, 'width', '');
        css(el, 'height', '');
    }
    function getChildContainingRectFromElement(container, options, ghostEl) {
        var rect = {};
        Array.from(container.children).forEach(function (child) {
            var _rect$left, _rect$top, _rect$right, _rect$bottom;
            if (!closest(child, options.draggable, container, false) || child.animated || child === ghostEl) return;
            var childRect = getRect(child);
            rect.left = Math.min((_rect$left = rect.left) !== null && _rect$left !== void 0 ? _rect$left : Infinity, childRect.left);
            rect.top = Math.min((_rect$top = rect.top) !== null && _rect$top !== void 0 ? _rect$top : Infinity, childRect.top);
            rect.right = Math.max((_rect$right = rect.right) !== null && _rect$right !== void 0 ? _rect$right : -Infinity, childRect.right);
            rect.bottom = Math.max((_rect$bottom = rect.bottom) !== null && _rect$bottom !== void 0 ? _rect$bottom : -Infinity, childRect.bottom);
        });
        rect.width = rect.right - rect.left;
        rect.height = rect.bottom - rect.top;
        rect.x = rect.left;
        rect.y = rect.top;
        return rect;
    }
    var expando = 'Sortable' + new Date().getTime();

    function AnimationStateManager() {
        var animationStates = [],
            animationCallbackId;
        return {
            captureAnimationState: function captureAnimationState() {
                animationStates = [];
                if (!this.options.animation) return;
                var children = [].slice.call(this.el.children);
                children.forEach(function (child) {
                    if (css(child, 'display') === 'none' || child === Sortable.ghost) return;
                    animationStates.push({
                        target: child,
                        rect: getRect(child)
                    });
                    var fromRect = _objectSpread2({}, animationStates[animationStates.length - 1].rect);

                    // If animating: compensate for current animation
                    if (child.thisAnimationDuration) {
                        var childMatrix = matrix(child, true);
                        if (childMatrix) {
                            fromRect.top -= childMatrix.f;
                            fromRect.left -= childMatrix.e;
                        }
                    }
                    child.fromRect = fromRect;
                });
            },
            addAnimationState: function addAnimationState(state) {
                animationStates.push(state);
            },
            removeAnimationState: function removeAnimationState(target) {
                animationStates.splice(indexOfObject(animationStates, {
                    target: target
                }), 1);
            },
            animateAll: function animateAll(callback) {
                var _this = this;
                if (!this.options.animation) {
                    clearTimeout(animationCallbackId);
                    if (typeof callback === 'function') callback();
                    return;
                }
                var animating = false,
                    animationTime = 0;
                animationStates.forEach(function (state) {
                    var time = 0,
                        target = state.target,
                        fromRect = target.fromRect,
                        toRect = getRect(target),
                        prevFromRect = target.prevFromRect,
                        prevToRect = target.prevToRect,
                        animatingRect = state.rect,
                        targetMatrix = matrix(target, true);
                    if (targetMatrix) {
                        // Compensate for current animation
                        toRect.top -= targetMatrix.f;
                        toRect.left -= targetMatrix.e;
                    }
                    target.toRect = toRect;
                    if (target.thisAnimationDuration) {
                        // Could also check if animatingRect is between fromRect and toRect
                        if (isRectEqual(prevFromRect, toRect) && !isRectEqual(fromRect, toRect) &&
                            // Make sure animatingRect is on line between toRect & fromRect
                            (animatingRect.top - toRect.top) / (animatingRect.left - toRect.left) === (fromRect.top - toRect.top) / (fromRect.left - toRect.left)) {
                            // If returning to same place as started from animation and on same axis
                            time = calculateRealTime(animatingRect, prevFromRect, prevToRect, _this.options);
                        }
                    }

                    // if fromRect != toRect: animate
                    if (!isRectEqual(toRect, fromRect)) {
                        target.prevFromRect = fromRect;
                        target.prevToRect = toRect;
                        if (!time) {
                            time = _this.options.animation;
                        }
                        _this.animate(target, animatingRect, toRect, time);
                    }
                    if (time) {
                        animating = true;
                        animationTime = Math.max(animationTime, time);
                        clearTimeout(target.animationResetTimer);
                        target.animationResetTimer = setTimeout(function () {
                            target.animationTime = 0;
                            target.prevFromRect = null;
                            target.fromRect = null;
                            target.prevToRect = null;
                            target.thisAnimationDuration = null;
                        }, time);
                        target.thisAnimationDuration = time;
                    }
                });
                clearTimeout(animationCallbackId);
                if (!animating) {
                    if (typeof callback === 'function') callback();
                } else {
                    animationCallbackId = setTimeout(function () {
                        if (typeof callback === 'function') callback();
                    }, animationTime);
                }
                animationStates = [];
            },
            animate: function animate(target, currentRect, toRect, duration) {
                if (duration) {
                    css(target, 'transition', '');
                    css(target, 'transform', '');
                    var elMatrix = matrix(this.el),
                        scaleX = elMatrix && elMatrix.a,
                        scaleY = elMatrix && elMatrix.d,
                        translateX = (currentRect.left - toRect.left) / (scaleX || 1),
                        translateY = (currentRect.top - toRect.top) / (scaleY || 1);
                    target.animatingX = !!translateX;
                    target.animatingY = !!translateY;
                    css(target, 'transform', 'translate3d(' + translateX + 'px,' + translateY + 'px,0)');
                    this.forRepaintDummy = repaint(target); // repaint

                    css(target, 'transition', 'transform ' + duration + 'ms' + (this.options.easing ? ' ' + this.options.easing : ''));
                    css(target, 'transform', 'translate3d(0,0,0)');
                    typeof target.animated === 'number' && clearTimeout(target.animated);
                    target.animated = setTimeout(function () {
                        css(target, 'transition', '');
                        css(target, 'transform', '');
                        target.animated = false;
                        target.animatingX = false;
                        target.animatingY = false;
                    }, duration);
                }
            }
        };
    }
    function repaint(target) {
        return target.offsetWidth;
    }
    function calculateRealTime(animatingRect, fromRect, toRect, options) {
        return Math.sqrt(Math.pow(fromRect.top - animatingRect.top, 2) + Math.pow(fromRect.left - animatingRect.left, 2)) / Math.sqrt(Math.pow(fromRect.top - toRect.top, 2) + Math.pow(fromRect.left - toRect.left, 2)) * options.animation;
    }

    var plugins = [];
    var defaults = {
        initializeByDefault: true
    };
    var PluginManager = {
        mount: function mount(plugin) {
            // Set default static properties
            for (var option in defaults) {
                if (defaults.hasOwnProperty(option) && !(option in plugin)) {
                    plugin[option] = defaults[option];
                }
            }
            plugins.forEach(function (p) {
                if (p.pluginName === plugin.pluginName) {
                    throw "Sortable: Cannot mount plugin ".concat(plugin.pluginName, " more than once");
                }
            });
            plugins.push(plugin);
        },
        pluginEvent: function pluginEvent(eventName, sortable, evt) {
            var _this = this;
            this.eventCanceled = false;
            evt.cancel = function () {
                _this.eventCanceled = true;
            };
            var eventNameGlobal = eventName + 'Global';
            plugins.forEach(function (plugin) {
                if (!sortable[plugin.pluginName]) return;
                // Fire global events if it exists in this sortable
                if (sortable[plugin.pluginName][eventNameGlobal]) {
                    sortable[plugin.pluginName][eventNameGlobal](_objectSpread2({
                        sortable: sortable
                    }, evt));
                }

                // Only fire plugin event if plugin is enabled in this sortable,
                // and plugin has event defined
                if (sortable.options[plugin.pluginName] && sortable[plugin.pluginName][eventName]) {
                    sortable[plugin.pluginName][eventName](_objectSpread2({
                        sortable: sortable
                    }, evt));
                }
            });
        },
        initializePlugins: function initializePlugins(sortable, el, defaults, options) {
            plugins.forEach(function (plugin) {
                var pluginName = plugin.pluginName;
                if (!sortable.options[pluginName] && !plugin.initializeByDefault) return;
                var initialized = new plugin(sortable, el, sortable.options);
                initialized.sortable = sortable;
                initialized.options = sortable.options;
                sortable[pluginName] = initialized;

                // Add default options from plugin
                _extends(defaults, initialized.defaults);
            });
            for (var option in sortable.options) {
                if (!sortable.options.hasOwnProperty(option)) continue;
                var modified = this.modifyOption(sortable, option, sortable.options[option]);
                if (typeof modified !== 'undefined') {
                    sortable.options[option] = modified;
                }
            }
        },
        getEventProperties: function getEventProperties(name, sortable) {
            var eventProperties = {};
            plugins.forEach(function (plugin) {
                if (typeof plugin.eventProperties !== 'function') return;
                _extends(eventProperties, plugin.eventProperties.call(sortable[plugin.pluginName], name));
            });
            return eventProperties;
        },
        modifyOption: function modifyOption(sortable, name, value) {
            var modifiedValue;
            plugins.forEach(function (plugin) {
                // Plugin must exist on the Sortable
                if (!sortable[plugin.pluginName]) return;

                // If static option listener exists for this option, call in the context of the Sortable's instance of this plugin
                if (plugin.optionListeners && typeof plugin.optionListeners[name] === 'function') {
                    modifiedValue = plugin.optionListeners[name].call(sortable[plugin.pluginName], value);
                }
            });
            return modifiedValue;
        }
    };

    function dispatchEvent(_ref) {
        var sortable = _ref.sortable,
            rootEl = _ref.rootEl,
            name = _ref.name,
            targetEl = _ref.targetEl,
            cloneEl = _ref.cloneEl,
            toEl = _ref.toEl,
            fromEl = _ref.fromEl,
            oldIndex = _ref.oldIndex,
            newIndex = _ref.newIndex,
            oldDraggableIndex = _ref.oldDraggableIndex,
            newDraggableIndex = _ref.newDraggableIndex,
            originalEvent = _ref.originalEvent,
            putSortable = _ref.putSortable,
            extraEventProperties = _ref.extraEventProperties;
        sortable = sortable || rootEl && rootEl[expando];
        if (!sortable) return;
        var evt,
            options = sortable.options,
            onName = 'on' + name.charAt(0).toUpperCase() + name.substr(1);
        // Support for new CustomEvent feature
        if (window.CustomEvent && !IE11OrLess && !Edge) {
            evt = new CustomEvent(name, {
                bubbles: true,
                cancelable: true
            });
        } else {
            evt = document.createEvent('Event');
            evt.initEvent(name, true, true);
        }
        evt.to = toEl || rootEl;
        evt.from = fromEl || rootEl;
        evt.item = targetEl || rootEl;
        evt.clone = cloneEl;
        evt.oldIndex = oldIndex;
        evt.newIndex = newIndex;
        evt.oldDraggableIndex = oldDraggableIndex;
        evt.newDraggableIndex = newDraggableIndex;
        evt.originalEvent = originalEvent;
        evt.pullMode = putSortable ? putSortable.lastPutMode : undefined;
        var allEventProperties = _objectSpread2(_objectSpread2({}, extraEventProperties), PluginManager.getEventProperties(name, sortable));
        for (var option in allEventProperties) {
            evt[option] = allEventProperties[option];
        }
        if (rootEl) {
            rootEl.dispatchEvent(evt);
        }
        if (options[onName]) {
            options[onName].call(sortable, evt);
        }
    }

    var _excluded = ["evt"];
    var pluginEvent = function pluginEvent(eventName, sortable) {
        var _ref = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {},
            originalEvent = _ref.evt,
            data = _objectWithoutProperties(_ref, _excluded);
        PluginManager.pluginEvent.bind(Sortable)(eventName, sortable, _objectSpread2({
            dragEl: dragEl,
            parentEl: parentEl,
            ghostEl: ghostEl,
            rootEl: rootEl,
            nextEl: nextEl,
            lastDownEl: lastDownEl,
            cloneEl: cloneEl,
            cloneHidden: cloneHidden,
            dragStarted: moved,
            putSortable: putSortable,
            activeSortable: Sortable.active,
            originalEvent: originalEvent,
            oldIndex: oldIndex,
            oldDraggableIndex: oldDraggableIndex,
            newIndex: newIndex,
            newDraggableIndex: newDraggableIndex,
            hideGhostForTarget: _hideGhostForTarget,
            unhideGhostForTarget: _unhideGhostForTarget,
            cloneNowHidden: function cloneNowHidden() {
                cloneHidden = true;
            },
            cloneNowShown: function cloneNowShown() {
                cloneHidden = false;
            },
            dispatchSortableEvent: function dispatchSortableEvent(name) {
                _dispatchEvent({
                    sortable: sortable,
                    name: name,
                    originalEvent: originalEvent
                });
            }
        }, data));
    };
    function _dispatchEvent(info) {
        dispatchEvent(_objectSpread2({
            putSortable: putSortable,
            cloneEl: cloneEl,
            targetEl: dragEl,
            rootEl: rootEl,
            oldIndex: oldIndex,
            oldDraggableIndex: oldDraggableIndex,
            newIndex: newIndex,
            newDraggableIndex: newDraggableIndex
        }, info));
    }
    var dragEl,
        parentEl,
        ghostEl,
        rootEl,
        nextEl,
        lastDownEl,
        cloneEl,
        cloneHidden,
        oldIndex,
        newIndex,
        oldDraggableIndex,
        newDraggableIndex,
        activeGroup,
        putSortable,
        awaitingDragStarted = false,
        ignoreNextClick = false,
        sortables = [],
        tapEvt,
        touchEvt,
        lastDx,
        lastDy,
        tapDistanceLeft,
        tapDistanceTop,
        moved,
        lastTarget,
        lastDirection,
        pastFirstInvertThresh = false,
        isCircumstantialInvert = false,
        targetMoveDistance,
        // For positioning ghost absolutely
        ghostRelativeParent,
        ghostRelativeParentInitialScroll = [],
        // (left, top)

        _silent = false,
        savedInputChecked = [];

    /** @const */
    var documentExists = typeof document !== 'undefined',
        PositionGhostAbsolutely = IOS,
        CSSFloatProperty = Edge || IE11OrLess ? 'cssFloat' : 'float',
        // This will not pass for IE9, because IE9 DnD only works on anchors
        supportDraggable = documentExists && !ChromeForAndroid && !IOS && 'draggable' in document.createElement('div'),
        supportCssPointerEvents = function () {
            if (!documentExists) return;
            // false when <= IE11
            if (IE11OrLess) {
                return false;
            }
            var el = document.createElement('x');
            el.style.cssText = 'pointer-events:auto';
            return el.style.pointerEvents === 'auto';
        }(),
        _detectDirection = function _detectDirection(el, options) {
            var elCSS = css(el),
                elWidth = parseInt(elCSS.width) - parseInt(elCSS.paddingLeft) - parseInt(elCSS.paddingRight) - parseInt(elCSS.borderLeftWidth) - parseInt(elCSS.borderRightWidth),
                child1 = getChild(el, 0, options),
                child2 = getChild(el, 1, options),
                firstChildCSS = child1 && css(child1),
                secondChildCSS = child2 && css(child2),
                firstChildWidth = firstChildCSS && parseInt(firstChildCSS.marginLeft) + parseInt(firstChildCSS.marginRight) + getRect(child1).width,
                secondChildWidth = secondChildCSS && parseInt(secondChildCSS.marginLeft) + parseInt(secondChildCSS.marginRight) + getRect(child2).width;
            if (elCSS.display === 'flex') {
                return elCSS.flexDirection === 'column' || elCSS.flexDirection === 'column-reverse' ? 'vertical' : 'horizontal';
            }
            if (elCSS.display === 'grid') {
                return elCSS.gridTemplateColumns.split(' ').length <= 1 ? 'vertical' : 'horizontal';
            }
            if (child1 && firstChildCSS["float"] && firstChildCSS["float"] !== 'none') {
                var touchingSideChild2 = firstChildCSS["float"] === 'left' ? 'left' : 'right';
                return child2 && (secondChildCSS.clear === 'both' || secondChildCSS.clear === touchingSideChild2) ? 'vertical' : 'horizontal';
            }
            return child1 && (firstChildCSS.display === 'block' || firstChildCSS.display === 'flex' || firstChildCSS.display === 'table' || firstChildCSS.display === 'grid' || firstChildWidth >= elWidth && elCSS[CSSFloatProperty] === 'none' || child2 && elCSS[CSSFloatProperty] === 'none' && firstChildWidth + secondChildWidth > elWidth) ? 'vertical' : 'horizontal';
        },
        _dragElInRowColumn = function _dragElInRowColumn(dragRect, targetRect, vertical) {
            var dragElS1Opp = vertical ? dragRect.left : dragRect.top,
                dragElS2Opp = vertical ? dragRect.right : dragRect.bottom,
                dragElOppLength = vertical ? dragRect.width : dragRect.height,
                targetS1Opp = vertical ? targetRect.left : targetRect.top,
                targetS2Opp = vertical ? targetRect.right : targetRect.bottom,
                targetOppLength = vertical ? targetRect.width : targetRect.height;
            return dragElS1Opp === targetS1Opp || dragElS2Opp === targetS2Opp || dragElS1Opp + dragElOppLength / 2 === targetS1Opp + targetOppLength / 2;
        },
        /**
         * Detects first nearest empty sortable to X and Y position using emptyInsertThreshold.
         * @param  {Number} x      X position
         * @param  {Number} y      Y position
         * @return {HTMLElement}   Element of the first found nearest Sortable
         */
        _detectNearestEmptySortable = function _detectNearestEmptySortable(x, y) {
            var ret;
            sortables.some(function (sortable) {
                var threshold = sortable[expando].options.emptyInsertThreshold;
                if (!threshold || lastChild(sortable)) return;
                var rect = getRect(sortable),
                    insideHorizontally = x >= rect.left - threshold && x <= rect.right + threshold,
                    insideVertically = y >= rect.top - threshold && y <= rect.bottom + threshold;
                if (insideHorizontally && insideVertically) {
                    return ret = sortable;
                }
            });
            return ret;
        },
        _prepareGroup = function _prepareGroup(options) {
            function toFn(value, pull) {
                return function (to, from, dragEl, evt) {
                    var sameGroup = to.options.group.name && from.options.group.name && to.options.group.name === from.options.group.name;
                    if (value == null && (pull || sameGroup)) {
                        // Default pull value
                        // Default pull and put value if same group
                        return true;
                    } else if (value == null || value === false) {
                        return false;
                    } else if (pull && value === 'clone') {
                        return value;
                    } else if (typeof value === 'function') {
                        return toFn(value(to, from, dragEl, evt), pull)(to, from, dragEl, evt);
                    } else {
                        var otherGroup = (pull ? to : from).options.group.name;
                        return value === true || typeof value === 'string' && value === otherGroup || value.join && value.indexOf(otherGroup) > -1;
                    }
                };
            }
            var group = {};
            var originalGroup = options.group;
            if (!originalGroup || _typeof(originalGroup) != 'object') {
                originalGroup = {
                    name: originalGroup
                };
            }
            group.name = originalGroup.name;
            group.checkPull = toFn(originalGroup.pull, true);
            group.checkPut = toFn(originalGroup.put);
            group.revertClone = originalGroup.revertClone;
            options.group = group;
        },
        _hideGhostForTarget = function _hideGhostForTarget() {
            if (!supportCssPointerEvents && ghostEl) {
                css(ghostEl, 'display', 'none');
            }
        },
        _unhideGhostForTarget = function _unhideGhostForTarget() {
            if (!supportCssPointerEvents && ghostEl) {
                css(ghostEl, 'display', '');
            }
        };

    // #1184 fix - Prevent click event on fallback if dragged but item not changed position
    if (documentExists && !ChromeForAndroid) {
        document.addEventListener('click', function (evt) {
            if (ignoreNextClick) {
                evt.preventDefault();
                evt.stopPropagation && evt.stopPropagation();
                evt.stopImmediatePropagation && evt.stopImmediatePropagation();
                ignoreNextClick = false;
                return false;
            }
        }, true);
    }
    var nearestEmptyInsertDetectEvent = function nearestEmptyInsertDetectEvent(evt) {
        if (dragEl) {
            evt = evt.touches ? evt.touches[0] : evt;
            var nearest = _detectNearestEmptySortable(evt.clientX, evt.clientY);
            if (nearest) {
                // Create imitation event
                var event = {};
                for (var i in evt) {
                    if (evt.hasOwnProperty(i)) {
                        event[i] = evt[i];
                    }
                }
                event.target = event.rootEl = nearest;
                event.preventDefault = void 0;
                event.stopPropagation = void 0;
                nearest[expando]._onDragOver(event);
            }
        }
    };
    var _checkOutsideTargetEl = function _checkOutsideTargetEl(evt) {
        if (dragEl) {
            dragEl.parentNode[expando]._isOutsideThisEl(evt.target);
        }
    };

    /**
     * @class  Sortable
     * @param  {HTMLElement}  el
     * @param  {Object}       [options]
     */
    function Sortable(el, options) {
        if (!(el && el.nodeType && el.nodeType === 1)) {
            throw "Sortable: `el` must be an HTMLElement, not ".concat({}.toString.call(el));
        }
        this.el = el; // root element
        this.options = options = _extends({}, options);

        // Export instance
        el[expando] = this;
        var defaults = {
            group: null,
            sort: true,
            disabled: false,
            store: null,
            handle: null,
            draggable: /^[uo]l$/i.test(el.nodeName) ? '>li' : '>*',
            swapThreshold: 1,
            // percentage; 0 <= x <= 1
            invertSwap: false,
            // invert always
            invertedSwapThreshold: null,
            // will be set to same as swapThreshold if default
            removeCloneOnHide: true,
            direction: function direction() {
                return _detectDirection(el, this.options);
            },
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            ignore: 'a, img',
            filter: null,
            preventOnFilter: true,
            animation: 0,
            easing: null,
            setData: function setData(dataTransfer, dragEl) {
                dataTransfer.setData('Text', dragEl.textContent);
            },
            dropBubble: false,
            dragoverBubble: false,
            dataIdAttr: 'data-id',
            delay: 0,
            delayOnTouchOnly: false,
            touchStartThreshold: (Number.parseInt ? Number : window).parseInt(window.devicePixelRatio, 10) || 1,
            forceFallback: false,
            fallbackClass: 'sortable-fallback',
            fallbackOnBody: false,
            fallbackTolerance: 0,
            fallbackOffset: {
                x: 0,
                y: 0
            },
            // Disabled on Safari: #1571; Enabled on Safari IOS: #2244
            supportPointer: Sortable.supportPointer !== false && 'PointerEvent' in window && (!Safari || IOS),
            emptyInsertThreshold: 5
        };
        PluginManager.initializePlugins(this, el, defaults);

        // Set default options
        for (var name in defaults) {
            !(name in options) && (options[name] = defaults[name]);
        }
        _prepareGroup(options);

        // Bind all private methods
        for (var fn in this) {
            if (fn.charAt(0) === '_' && typeof this[fn] === 'function') {
                this[fn] = this[fn].bind(this);
            }
        }

        // Setup drag mode
        this.nativeDraggable = options.forceFallback ? false : supportDraggable;
        if (this.nativeDraggable) {
            // Touch start threshold cannot be greater than the native dragstart threshold
            this.options.touchStartThreshold = 1;
        }

        // Bind events
        if (options.supportPointer) {
            on(el, 'pointerdown', this._onTapStart);
        } else {
            on(el, 'mousedown', this._onTapStart);
            on(el, 'touchstart', this._onTapStart);
        }
        if (this.nativeDraggable) {
            on(el, 'dragover', this);
            on(el, 'dragenter', this);
        }
        sortables.push(this.el);

        // Restore sorting
        options.store && options.store.get && this.sort(options.store.get(this) || []);

        // Add animation state manager
        _extends(this, AnimationStateManager());
    }
    Sortable.prototype = /** @lends Sortable.prototype */{
        constructor: Sortable,
        _isOutsideThisEl: function _isOutsideThisEl(target) {
            if (!this.el.contains(target) && target !== this.el) {
                lastTarget = null;
            }
        },
        _getDirection: function _getDirection(evt, target) {
            return typeof this.options.direction === 'function' ? this.options.direction.call(this, evt, target, dragEl) : this.options.direction;
        },
        _onTapStart: function _onTapStart( /** Event|TouchEvent */evt) {
            if (!evt.cancelable) return;
            var _this = this,
                el = this.el,
                options = this.options,
                preventOnFilter = options.preventOnFilter,
                type = evt.type,
                touch = evt.touches && evt.touches[0] || evt.pointerType && evt.pointerType === 'touch' && evt,
                target = (touch || evt).target,
                originalTarget = evt.target.shadowRoot && (evt.path && evt.path[0] || evt.composedPath && evt.composedPath()[0]) || target,
                filter = options.filter;
            _saveInputCheckedState(el);

            // Don't trigger start event when an element is been dragged, otherwise the evt.oldindex always wrong when set option.group.
            if (dragEl) {
                return;
            }
            if (/mousedown|pointerdown/.test(type) && evt.button !== 0 || options.disabled) {
                return; // only left button and enabled
            }

            // cancel dnd if original target is content editable
            if (originalTarget.isContentEditable) {
                return;
            }

            // Safari ignores further event handling after mousedown
            if (!this.nativeDraggable && Safari && target && target.tagName.toUpperCase() === 'SELECT') {
                return;
            }
            target = closest(target, options.draggable, el, false);
            if (target && target.animated) {
                return;
            }
            if (lastDownEl === target) {
                // Ignoring duplicate `down`
                return;
            }

            // Get the index of the dragged element within its parent
            oldIndex = index(target);
            oldDraggableIndex = index(target, options.draggable);

            // Check filter
            if (typeof filter === 'function') {
                if (filter.call(this, evt, target, this)) {
                    _dispatchEvent({
                        sortable: _this,
                        rootEl: originalTarget,
                        name: 'filter',
                        targetEl: target,
                        toEl: el,
                        fromEl: el
                    });
                    pluginEvent('filter', _this, {
                        evt: evt
                    });
                    preventOnFilter && evt.preventDefault();
                    return; // cancel dnd
                }
            } else if (filter) {
                filter = filter.split(',').some(function (criteria) {
                    criteria = closest(originalTarget, criteria.trim(), el, false);
                    if (criteria) {
                        _dispatchEvent({
                            sortable: _this,
                            rootEl: criteria,
                            name: 'filter',
                            targetEl: target,
                            fromEl: el,
                            toEl: el
                        });
                        pluginEvent('filter', _this, {
                            evt: evt
                        });
                        return true;
                    }
                });
                if (filter) {
                    preventOnFilter && evt.preventDefault();
                    return; // cancel dnd
                }
            }
            if (options.handle && !closest(originalTarget, options.handle, el, false)) {
                return;
            }

            // Prepare `dragstart`
            this._prepareDragStart(evt, touch, target);
        },
        _prepareDragStart: function _prepareDragStart( /** Event */evt, /** Touch */touch, /** HTMLElement */target) {
            var _this = this,
                el = _this.el,
                options = _this.options,
                ownerDocument = el.ownerDocument,
                dragStartFn;
            if (target && !dragEl && target.parentNode === el) {
                var dragRect = getRect(target);
                rootEl = el;
                dragEl = target;
                parentEl = dragEl.parentNode;
                nextEl = dragEl.nextSibling;
                lastDownEl = target;
                activeGroup = options.group;
                Sortable.dragged = dragEl;
                tapEvt = {
                    target: dragEl,
                    clientX: (touch || evt).clientX,
                    clientY: (touch || evt).clientY
                };
                tapDistanceLeft = tapEvt.clientX - dragRect.left;
                tapDistanceTop = tapEvt.clientY - dragRect.top;
                this._lastX = (touch || evt).clientX;
                this._lastY = (touch || evt).clientY;
                dragEl.style['will-change'] = 'all';
                dragStartFn = function dragStartFn() {
                    pluginEvent('delayEnded', _this, {
                        evt: evt
                    });
                    if (Sortable.eventCanceled) {
                        _this._onDrop();
                        return;
                    }
                    // Delayed drag has been triggered
                    // we can re-enable the events: touchmove/mousemove
                    _this._disableDelayedDragEvents();
                    if (!FireFox && _this.nativeDraggable) {
                        dragEl.draggable = true;
                    }

                    // Bind the events: dragstart/dragend
                    _this._triggerDragStart(evt, touch);

                    // Drag start event
                    _dispatchEvent({
                        sortable: _this,
                        name: 'choose',
                        originalEvent: evt
                    });

                    // Chosen item
                    toggleClass(dragEl, options.chosenClass, true);
                };

                // Disable "draggable"
                options.ignore.split(',').forEach(function (criteria) {
                    find(dragEl, criteria.trim(), _disableDraggable);
                });
                on(ownerDocument, 'dragover', nearestEmptyInsertDetectEvent);
                on(ownerDocument, 'mousemove', nearestEmptyInsertDetectEvent);
                on(ownerDocument, 'touchmove', nearestEmptyInsertDetectEvent);
                if (options.supportPointer) {
                    on(ownerDocument, 'pointerup', _this._onDrop);
                    // Native D&D triggers pointercancel
                    !this.nativeDraggable && on(ownerDocument, 'pointercancel', _this._onDrop);
                } else {
                    on(ownerDocument, 'mouseup', _this._onDrop);
                    on(ownerDocument, 'touchend', _this._onDrop);
                    on(ownerDocument, 'touchcancel', _this._onDrop);
                }

                // Make dragEl draggable (must be before delay for FireFox)
                if (FireFox && this.nativeDraggable) {
                    this.options.touchStartThreshold = 4;
                    dragEl.draggable = true;
                }
                pluginEvent('delayStart', this, {
                    evt: evt
                });

                // Delay is impossible for native DnD in Edge or IE
                if (options.delay && (!options.delayOnTouchOnly || touch) && (!this.nativeDraggable || !(Edge || IE11OrLess))) {
                    if (Sortable.eventCanceled) {
                        this._onDrop();
                        return;
                    }
                    // If the user moves the pointer or let go the click or touch
                    // before the delay has been reached:
                    // disable the delayed drag
                    if (options.supportPointer) {
                        on(ownerDocument, 'pointerup', _this._disableDelayedDrag);
                        on(ownerDocument, 'pointercancel', _this._disableDelayedDrag);
                    } else {
                        on(ownerDocument, 'mouseup', _this._disableDelayedDrag);
                        on(ownerDocument, 'touchend', _this._disableDelayedDrag);
                        on(ownerDocument, 'touchcancel', _this._disableDelayedDrag);
                    }
                    on(ownerDocument, 'mousemove', _this._delayedDragTouchMoveHandler);
                    on(ownerDocument, 'touchmove', _this._delayedDragTouchMoveHandler);
                    options.supportPointer && on(ownerDocument, 'pointermove', _this._delayedDragTouchMoveHandler);
                    _this._dragStartTimer = setTimeout(dragStartFn, options.delay);
                } else {
                    dragStartFn();
                }
            }
        },
        _delayedDragTouchMoveHandler: function _delayedDragTouchMoveHandler( /** TouchEvent|PointerEvent **/e) {
            var touch = e.touches ? e.touches[0] : e;
            if (Math.max(Math.abs(touch.clientX - this._lastX), Math.abs(touch.clientY - this._lastY)) >= Math.floor(this.options.touchStartThreshold / (this.nativeDraggable && window.devicePixelRatio || 1))) {
                this._disableDelayedDrag();
            }
        },
        _disableDelayedDrag: function _disableDelayedDrag() {
            dragEl && _disableDraggable(dragEl);
            clearTimeout(this._dragStartTimer);
            this._disableDelayedDragEvents();
        },
        _disableDelayedDragEvents: function _disableDelayedDragEvents() {
            var ownerDocument = this.el.ownerDocument;
            off(ownerDocument, 'mouseup', this._disableDelayedDrag);
            off(ownerDocument, 'touchend', this._disableDelayedDrag);
            off(ownerDocument, 'touchcancel', this._disableDelayedDrag);
            off(ownerDocument, 'pointerup', this._disableDelayedDrag);
            off(ownerDocument, 'pointercancel', this._disableDelayedDrag);
            off(ownerDocument, 'mousemove', this._delayedDragTouchMoveHandler);
            off(ownerDocument, 'touchmove', this._delayedDragTouchMoveHandler);
            off(ownerDocument, 'pointermove', this._delayedDragTouchMoveHandler);
        },
        _triggerDragStart: function _triggerDragStart( /** Event */evt, /** Touch */touch) {
            touch = touch || evt.pointerType == 'touch' && evt;
            if (!this.nativeDraggable || touch) {
                if (this.options.supportPointer) {
                    on(document, 'pointermove', this._onTouchMove);
                } else if (touch) {
                    on(document, 'touchmove', this._onTouchMove);
                } else {
                    on(document, 'mousemove', this._onTouchMove);
                }
            } else {
                on(dragEl, 'dragend', this);
                on(rootEl, 'dragstart', this._onDragStart);
            }
            try {
                if (document.selection) {
                    _nextTick(function () {
                        document.selection.empty();
                    });
                } else {
                    window.getSelection().removeAllRanges();
                }
            } catch (err) { }
        },
        _dragStarted: function _dragStarted(fallback, evt) {
            awaitingDragStarted = false;
            if (rootEl && dragEl) {
                pluginEvent('dragStarted', this, {
                    evt: evt
                });
                if (this.nativeDraggable) {
                    on(document, 'dragover', _checkOutsideTargetEl);
                }
                var options = this.options;

                // Apply effect
                !fallback && toggleClass(dragEl, options.dragClass, false);
                toggleClass(dragEl, options.ghostClass, true);
                Sortable.active = this;
                fallback && this._appendGhost();

                // Drag start event
                _dispatchEvent({
                    sortable: this,
                    name: 'start',
                    originalEvent: evt
                });
            } else {
                this._nulling();
            }
        },
        _emulateDragOver: function _emulateDragOver() {
            if (touchEvt) {
                this._lastX = touchEvt.clientX;
                this._lastY = touchEvt.clientY;
                _hideGhostForTarget();
                var target = document.elementFromPoint(touchEvt.clientX, touchEvt.clientY);
                var parent = target;
                while (target && target.shadowRoot) {
                    target = target.shadowRoot.elementFromPoint(touchEvt.clientX, touchEvt.clientY);
                    if (target === parent) break;
                    parent = target;
                }
                dragEl.parentNode[expando]._isOutsideThisEl(target);
                if (parent) {
                    do {
                        if (parent[expando]) {
                            var inserted = void 0;
                            inserted = parent[expando]._onDragOver({
                                clientX: touchEvt.clientX,
                                clientY: touchEvt.clientY,
                                target: target,
                                rootEl: parent
                            });
                            if (inserted && !this.options.dragoverBubble) {
                                break;
                            }
                        }
                        target = parent; // store last element
                    }
          /* jshint boss:true */ while (parent = getParentOrHost(parent));
                }
                _unhideGhostForTarget();
            }
        },
        _onTouchMove: function _onTouchMove( /**TouchEvent*/evt) {
            if (tapEvt) {
                var options = this.options,
                    fallbackTolerance = options.fallbackTolerance,
                    fallbackOffset = options.fallbackOffset,
                    touch = evt.touches ? evt.touches[0] : evt,
                    ghostMatrix = ghostEl && matrix(ghostEl, true),
                    scaleX = ghostEl && ghostMatrix && ghostMatrix.a,
                    scaleY = ghostEl && ghostMatrix && ghostMatrix.d,
                    relativeScrollOffset = PositionGhostAbsolutely && ghostRelativeParent && getRelativeScrollOffset(ghostRelativeParent),
                    dx = (touch.clientX - tapEvt.clientX + fallbackOffset.x) / (scaleX || 1) + (relativeScrollOffset ? relativeScrollOffset[0] - ghostRelativeParentInitialScroll[0] : 0) / (scaleX || 1),
                    dy = (touch.clientY - tapEvt.clientY + fallbackOffset.y) / (scaleY || 1) + (relativeScrollOffset ? relativeScrollOffset[1] - ghostRelativeParentInitialScroll[1] : 0) / (scaleY || 1);

                // only set the status to dragging, when we are actually dragging
                if (!Sortable.active && !awaitingDragStarted) {
                    if (fallbackTolerance && Math.max(Math.abs(touch.clientX - this._lastX), Math.abs(touch.clientY - this._lastY)) < fallbackTolerance) {
                        return;
                    }
                    this._onDragStart(evt, true);
                }
                if (ghostEl) {
                    if (ghostMatrix) {
                        ghostMatrix.e += dx - (lastDx || 0);
                        ghostMatrix.f += dy - (lastDy || 0);
                    } else {
                        ghostMatrix = {
                            a: 1,
                            b: 0,
                            c: 0,
                            d: 1,
                            e: dx,
                            f: dy
                        };
                    }
                    var cssMatrix = "matrix(".concat(ghostMatrix.a, ",").concat(ghostMatrix.b, ",").concat(ghostMatrix.c, ",").concat(ghostMatrix.d, ",").concat(ghostMatrix.e, ",").concat(ghostMatrix.f, ")");
                    css(ghostEl, 'webkitTransform', cssMatrix);
                    css(ghostEl, 'mozTransform', cssMatrix);
                    css(ghostEl, 'msTransform', cssMatrix);
                    css(ghostEl, 'transform', cssMatrix);
                    lastDx = dx;
                    lastDy = dy;
                    touchEvt = touch;
                }
                evt.cancelable && evt.preventDefault();
            }
        },
        _appendGhost: function _appendGhost() {
            // Bug if using scale(): https://stackoverflow.com/questions/2637058
            // Not being adjusted for
            if (!ghostEl) {
                var container = this.options.fallbackOnBody ? document.body : rootEl,
                    rect = getRect(dragEl, true, PositionGhostAbsolutely, true, container),
                    options = this.options;

                // Position absolutely
                if (PositionGhostAbsolutely) {
                    // Get relatively positioned parent
                    ghostRelativeParent = container;
                    while (css(ghostRelativeParent, 'position') === 'static' && css(ghostRelativeParent, 'transform') === 'none' && ghostRelativeParent !== document) {
                        ghostRelativeParent = ghostRelativeParent.parentNode;
                    }
                    if (ghostRelativeParent !== document.body && ghostRelativeParent !== document.documentElement) {
                        if (ghostRelativeParent === document) ghostRelativeParent = getWindowScrollingElement();
                        rect.top += ghostRelativeParent.scrollTop;
                        rect.left += ghostRelativeParent.scrollLeft;
                    } else {
                        ghostRelativeParent = getWindowScrollingElement();
                    }
                    ghostRelativeParentInitialScroll = getRelativeScrollOffset(ghostRelativeParent);
                }
                ghostEl = dragEl.cloneNode(true);
                toggleClass(ghostEl, options.ghostClass, false);
                toggleClass(ghostEl, options.fallbackClass, true);
                toggleClass(ghostEl, options.dragClass, true);
                css(ghostEl, 'transition', '');
                css(ghostEl, 'transform', '');
                css(ghostEl, 'box-sizing', 'border-box');
                css(ghostEl, 'margin', 0);
                css(ghostEl, 'top', rect.top);
                css(ghostEl, 'left', rect.left);
                css(ghostEl, 'width', rect.width);
                css(ghostEl, 'height', rect.height);
                css(ghostEl, 'opacity', '0.8');
                css(ghostEl, 'position', PositionGhostAbsolutely ? 'absolute' : 'fixed');
                css(ghostEl, 'zIndex', '100000');
                css(ghostEl, 'pointerEvents', 'none');
                Sortable.ghost = ghostEl;
                container.appendChild(ghostEl);

                // Set transform-origin
                css(ghostEl, 'transform-origin', tapDistanceLeft / parseInt(ghostEl.style.width) * 100 + '% ' + tapDistanceTop / parseInt(ghostEl.style.height) * 100 + '%');
            }
        },
        _onDragStart: function _onDragStart( /**Event*/evt, /**boolean*/fallback) {
            var _this = this;
            var dataTransfer = evt.dataTransfer;
            var options = _this.options;
            pluginEvent('dragStart', this, {
                evt: evt
            });
            if (Sortable.eventCanceled) {
                this._onDrop();
                return;
            }
            pluginEvent('setupClone', this);
            if (!Sortable.eventCanceled) {
                cloneEl = clone(dragEl);
                cloneEl.removeAttribute("id");
                cloneEl.draggable = false;
                cloneEl.style['will-change'] = '';
                this._hideClone();
                toggleClass(cloneEl, this.options.chosenClass, false);
                Sortable.clone = cloneEl;
            }

            // #1143: IFrame support workaround
            _this.cloneId = _nextTick(function () {
                pluginEvent('clone', _this);
                if (Sortable.eventCanceled) return;
                if (!_this.options.removeCloneOnHide) {
                    rootEl.insertBefore(cloneEl, dragEl);
                }
                _this._hideClone();
                _dispatchEvent({
                    sortable: _this,
                    name: 'clone'
                });
            });
            !fallback && toggleClass(dragEl, options.dragClass, true);

            // Set proper drop events
            if (fallback) {
                ignoreNextClick = true;
                _this._loopId = setInterval(_this._emulateDragOver, 50);
            } else {
                // Undo what was set in _prepareDragStart before drag started
                off(document, 'mouseup', _this._onDrop);
                off(document, 'touchend', _this._onDrop);
                off(document, 'touchcancel', _this._onDrop);
                if (dataTransfer) {
                    dataTransfer.effectAllowed = 'move';
                    options.setData && options.setData.call(_this, dataTransfer, dragEl);
                }
                on(document, 'drop', _this);

                // #1276 fix:
                css(dragEl, 'transform', 'translateZ(0)');
            }
            awaitingDragStarted = true;
            _this._dragStartId = _nextTick(_this._dragStarted.bind(_this, fallback, evt));
            on(document, 'selectstart', _this);
            moved = true;
            window.getSelection().removeAllRanges();
            if (Safari) {
                css(document.body, 'user-select', 'none');
            }
        },
        // Returns true - if no further action is needed (either inserted or another condition)
        _onDragOver: function _onDragOver( /**Event*/evt) {
            var el = this.el,
                target = evt.target,
                dragRect,
                targetRect,
                revert,
                options = this.options,
                group = options.group,
                activeSortable = Sortable.active,
                isOwner = activeGroup === group,
                canSort = options.sort,
                fromSortable = putSortable || activeSortable,
                vertical,
                _this = this,
                completedFired = false;
            if (_silent) return;
            function dragOverEvent(name, extra) {
                pluginEvent(name, _this, _objectSpread2({
                    evt: evt,
                    isOwner: isOwner,
                    axis: vertical ? 'vertical' : 'horizontal',
                    revert: revert,
                    dragRect: dragRect,
                    targetRect: targetRect,
                    canSort: canSort,
                    fromSortable: fromSortable,
                    target: target,
                    completed: completed,
                    onMove: function onMove(target, after) {
                        return _onMove(rootEl, el, dragEl, dragRect, target, getRect(target), evt, after);
                    },
                    changed: changed
                }, extra));
            }

            // Capture animation state
            function capture() {
                dragOverEvent('dragOverAnimationCapture');
                _this.captureAnimationState();
                if (_this !== fromSortable) {
                    fromSortable.captureAnimationState();
                }
            }

            // Return invocation when dragEl is inserted (or completed)
            function completed(insertion) {
                dragOverEvent('dragOverCompleted', {
                    insertion: insertion
                });
                if (insertion) {
                    // Clones must be hidden before folding animation to capture dragRectAbsolute properly
                    if (isOwner) {
                        activeSortable._hideClone();
                    } else {
                        activeSortable._showClone(_this);
                    }
                    if (_this !== fromSortable) {
                        // Set ghost class to new sortable's ghost class
                        toggleClass(dragEl, putSortable ? putSortable.options.ghostClass : activeSortable.options.ghostClass, false);
                        toggleClass(dragEl, options.ghostClass, true);
                    }
                    if (putSortable !== _this && _this !== Sortable.active) {
                        putSortable = _this;
                    } else if (_this === Sortable.active && putSortable) {
                        putSortable = null;
                    }

                    // Animation
                    if (fromSortable === _this) {
                        _this._ignoreWhileAnimating = target;
                    }
                    _this.animateAll(function () {
                        dragOverEvent('dragOverAnimationComplete');
                        _this._ignoreWhileAnimating = null;
                    });
                    if (_this !== fromSortable) {
                        fromSortable.animateAll();
                        fromSortable._ignoreWhileAnimating = null;
                    }
                }

                // Null lastTarget if it is not inside a previously swapped element
                if (target === dragEl && !dragEl.animated || target === el && !target.animated) {
                    lastTarget = null;
                }

                // no bubbling and not fallback
                if (!options.dragoverBubble && !evt.rootEl && target !== document) {
                    dragEl.parentNode[expando]._isOutsideThisEl(evt.target);

                    // Do not detect for empty insert if already inserted
                    !insertion && nearestEmptyInsertDetectEvent(evt);
                }
                !options.dragoverBubble && evt.stopPropagation && evt.stopPropagation();
                return completedFired = true;
            }

            // Call when dragEl has been inserted
            function changed() {
                newIndex = index(dragEl);
                newDraggableIndex = index(dragEl, options.draggable);
                _dispatchEvent({
                    sortable: _this,
                    name: 'change',
                    toEl: el,
                    newIndex: newIndex,
                    newDraggableIndex: newDraggableIndex,
                    originalEvent: evt
                });
            }
            if (evt.preventDefault !== void 0) {
                evt.cancelable && evt.preventDefault();
            }
            target = closest(target, options.draggable, el, true);
            dragOverEvent('dragOver');
            if (Sortable.eventCanceled) return completedFired;
            if (dragEl.contains(evt.target) || target.animated && target.animatingX && target.animatingY || _this._ignoreWhileAnimating === target) {
                return completed(false);
            }
            ignoreNextClick = false;
            if (activeSortable && !options.disabled && (isOwner ? canSort || (revert = parentEl !== rootEl) // Reverting item into the original list
                : putSortable === this || (this.lastPutMode = activeGroup.checkPull(this, activeSortable, dragEl, evt)) && group.checkPut(this, activeSortable, dragEl, evt))) {
                vertical = this._getDirection(evt, target) === 'vertical';
                dragRect = getRect(dragEl);
                dragOverEvent('dragOverValid');
                if (Sortable.eventCanceled) return completedFired;
                if (revert) {
                    parentEl = rootEl; // actualization
                    capture();
                    this._hideClone();
                    dragOverEvent('revert');
                    if (!Sortable.eventCanceled) {
                        if (nextEl) {
                            rootEl.insertBefore(dragEl, nextEl);
                        } else {
                            rootEl.appendChild(dragEl);
                        }
                    }
                    return completed(true);
                }
                var elLastChild = lastChild(el, options.draggable);
                if (!elLastChild || _ghostIsLast(evt, vertical, this) && !elLastChild.animated) {
                    // Insert to end of list

                    // If already at end of list: Do not insert
                    if (elLastChild === dragEl) {
                        return completed(false);
                    }

                    // if there is a last element, it is the target
                    if (elLastChild && el === evt.target) {
                        target = elLastChild;
                    }
                    if (target) {
                        targetRect = getRect(target);
                    }
                    if (_onMove(rootEl, el, dragEl, dragRect, target, targetRect, evt, !!target) !== false) {
                        capture();
                        if (elLastChild && elLastChild.nextSibling) {
                            // the last draggable element is not the last node
                            el.insertBefore(dragEl, elLastChild.nextSibling);
                        } else {
                            el.appendChild(dragEl);
                        }
                        parentEl = el; // actualization

                        changed();
                        return completed(true);
                    }
                } else if (elLastChild && _ghostIsFirst(evt, vertical, this)) {
                    // Insert to start of list
                    var firstChild = getChild(el, 0, options, true);
                    if (firstChild === dragEl) {
                        return completed(false);
                    }
                    target = firstChild;
                    targetRect = getRect(target);
                    if (_onMove(rootEl, el, dragEl, dragRect, target, targetRect, evt, false) !== false) {
                        capture();
                        el.insertBefore(dragEl, firstChild);
                        parentEl = el; // actualization

                        changed();
                        return completed(true);
                    }
                } else if (target.parentNode === el) {
                    targetRect = getRect(target);
                    var direction = 0,
                        targetBeforeFirstSwap,
                        differentLevel = dragEl.parentNode !== el,
                        differentRowCol = !_dragElInRowColumn(dragEl.animated && dragEl.toRect || dragRect, target.animated && target.toRect || targetRect, vertical),
                        side1 = vertical ? 'top' : 'left',
                        scrolledPastTop = isScrolledPast(target, 'top', 'top') || isScrolledPast(dragEl, 'top', 'top'),
                        scrollBefore = scrolledPastTop ? scrolledPastTop.scrollTop : void 0;
                    if (lastTarget !== target) {
                        targetBeforeFirstSwap = targetRect[side1];
                        pastFirstInvertThresh = false;
                        isCircumstantialInvert = !differentRowCol && options.invertSwap || differentLevel;
                    }
                    direction = _getSwapDirection(evt, target, targetRect, vertical, differentRowCol ? 1 : options.swapThreshold, options.invertedSwapThreshold == null ? options.swapThreshold : options.invertedSwapThreshold, isCircumstantialInvert, lastTarget === target);
                    var sibling;
                    if (direction !== 0) {
                        // Check if target is beside dragEl in respective direction (ignoring hidden elements)
                        var dragIndex = index(dragEl);
                        do {
                            dragIndex -= direction;
                            sibling = parentEl.children[dragIndex];
                        } while (sibling && (css(sibling, 'display') === 'none' || sibling === ghostEl));
                    }
                    // If dragEl is already beside target: Do not insert
                    if (direction === 0 || sibling === target) {
                        return completed(false);
                    }
                    lastTarget = target;
                    lastDirection = direction;
                    var nextSibling = target.nextElementSibling,
                        after = false;
                    after = direction === 1;
                    var moveVector = _onMove(rootEl, el, dragEl, dragRect, target, targetRect, evt, after);
                    if (moveVector !== false) {
                        if (moveVector === 1 || moveVector === -1) {
                            after = moveVector === 1;
                        }
                        _silent = true;
                        setTimeout(_unsilent, 30);
                        capture();
                        if (after && !nextSibling) {
                            el.appendChild(dragEl);
                        } else {
                            target.parentNode.insertBefore(dragEl, after ? nextSibling : target);
                        }

                        // Undo chrome's scroll adjustment (has no effect on other browsers)
                        if (scrolledPastTop) {
                            scrollBy(scrolledPastTop, 0, scrollBefore - scrolledPastTop.scrollTop);
                        }
                        parentEl = dragEl.parentNode; // actualization

                        // must be done before animation
                        if (targetBeforeFirstSwap !== undefined && !isCircumstantialInvert) {
                            targetMoveDistance = Math.abs(targetBeforeFirstSwap - getRect(target)[side1]);
                        }
                        changed();
                        return completed(true);
                    }
                }
                if (el.contains(dragEl)) {
                    return completed(false);
                }
            }
            return false;
        },
        _ignoreWhileAnimating: null,
        _offMoveEvents: function _offMoveEvents() {
            off(document, 'mousemove', this._onTouchMove);
            off(document, 'touchmove', this._onTouchMove);
            off(document, 'pointermove', this._onTouchMove);
            off(document, 'dragover', nearestEmptyInsertDetectEvent);
            off(document, 'mousemove', nearestEmptyInsertDetectEvent);
            off(document, 'touchmove', nearestEmptyInsertDetectEvent);
        },
        _offUpEvents: function _offUpEvents() {
            var ownerDocument = this.el.ownerDocument;
            off(ownerDocument, 'mouseup', this._onDrop);
            off(ownerDocument, 'touchend', this._onDrop);
            off(ownerDocument, 'pointerup', this._onDrop);
            off(ownerDocument, 'pointercancel', this._onDrop);
            off(ownerDocument, 'touchcancel', this._onDrop);
            off(document, 'selectstart', this);
        },
        _onDrop: function _onDrop( /**Event*/evt) {
            var el = this.el,
                options = this.options;

            // Get the index of the dragged element within its parent
            newIndex = index(dragEl);
            newDraggableIndex = index(dragEl, options.draggable);
            pluginEvent('drop', this, {
                evt: evt
            });
            parentEl = dragEl && dragEl.parentNode;

            // Get again after plugin event
            newIndex = index(dragEl);
            newDraggableIndex = index(dragEl, options.draggable);
            if (Sortable.eventCanceled) {
                this._nulling();
                return;
            }
            awaitingDragStarted = false;
            isCircumstantialInvert = false;
            pastFirstInvertThresh = false;
            clearInterval(this._loopId);
            clearTimeout(this._dragStartTimer);
            _cancelNextTick(this.cloneId);
            _cancelNextTick(this._dragStartId);

            // Unbind events
            if (this.nativeDraggable) {
                off(document, 'drop', this);
                off(el, 'dragstart', this._onDragStart);
            }
            this._offMoveEvents();
            this._offUpEvents();
            if (Safari) {
                css(document.body, 'user-select', '');
            }
            css(dragEl, 'transform', '');
            if (evt) {
                if (moved) {
                    evt.cancelable && evt.preventDefault();
                    !options.dropBubble && evt.stopPropagation();
                }
                ghostEl && ghostEl.parentNode && ghostEl.parentNode.removeChild(ghostEl);
                if (rootEl === parentEl || putSortable && putSortable.lastPutMode !== 'clone') {
                    // Remove clone(s)
                    cloneEl && cloneEl.parentNode && cloneEl.parentNode.removeChild(cloneEl);
                }
                if (dragEl) {
                    if (this.nativeDraggable) {
                        off(dragEl, 'dragend', this);
                    }
                    _disableDraggable(dragEl);
                    dragEl.style['will-change'] = '';

                    // Remove classes
                    // ghostClass is added in dragStarted
                    if (moved && !awaitingDragStarted) {
                        toggleClass(dragEl, putSortable ? putSortable.options.ghostClass : this.options.ghostClass, false);
                    }
                    toggleClass(dragEl, this.options.chosenClass, false);

                    // Drag stop event
                    _dispatchEvent({
                        sortable: this,
                        name: 'unchoose',
                        toEl: parentEl,
                        newIndex: null,
                        newDraggableIndex: null,
                        originalEvent: evt
                    });
                    if (rootEl !== parentEl) {
                        if (newIndex >= 0) {
                            // Add event
                            _dispatchEvent({
                                rootEl: parentEl,
                                name: 'add',
                                toEl: parentEl,
                                fromEl: rootEl,
                                originalEvent: evt
                            });

                            // Remove event
                            _dispatchEvent({
                                sortable: this,
                                name: 'remove',
                                toEl: parentEl,
                                originalEvent: evt
                            });

                            // drag from one list and drop into another
                            _dispatchEvent({
                                rootEl: parentEl,
                                name: 'sort',
                                toEl: parentEl,
                                fromEl: rootEl,
                                originalEvent: evt
                            });
                            _dispatchEvent({
                                sortable: this,
                                name: 'sort',
                                toEl: parentEl,
                                originalEvent: evt
                            });
                        }
                        putSortable && putSortable.save();
                    } else {
                        if (newIndex !== oldIndex) {
                            if (newIndex >= 0) {
                                // drag & drop within the same list
                                _dispatchEvent({
                                    sortable: this,
                                    name: 'update',
                                    toEl: parentEl,
                                    originalEvent: evt
                                });
                                _dispatchEvent({
                                    sortable: this,
                                    name: 'sort',
                                    toEl: parentEl,
                                    originalEvent: evt
                                });
                            }
                        }
                    }
                    if (Sortable.active) {
                        /* jshint eqnull:true */
                        if (newIndex == null || newIndex === -1) {
                            newIndex = oldIndex;
                            newDraggableIndex = oldDraggableIndex;
                        }
                        _dispatchEvent({
                            sortable: this,
                            name: 'end',
                            toEl: parentEl,
                            originalEvent: evt
                        });

                        // Save sorting
                        this.save();
                    }
                }
            }
            this._nulling();
        },
        _nulling: function _nulling() {
            pluginEvent('nulling', this);
            rootEl = dragEl = parentEl = ghostEl = nextEl = cloneEl = lastDownEl = cloneHidden = tapEvt = touchEvt = moved = newIndex = newDraggableIndex = oldIndex = oldDraggableIndex = lastTarget = lastDirection = putSortable = activeGroup = Sortable.dragged = Sortable.ghost = Sortable.clone = Sortable.active = null;
            var el = this.el;
            savedInputChecked.forEach(function (checkEl) {
                if (el.contains(checkEl)) {
                    checkEl.checked = true;
                }
            });
            savedInputChecked.length = lastDx = lastDy = 0;
        },
        handleEvent: function handleEvent( /**Event*/evt) {
            switch (evt.type) {
                case 'drop':
                case 'dragend':
                    this._onDrop(evt);
                    break;
                case 'dragenter':
                case 'dragover':
                    if (dragEl) {
                        this._onDragOver(evt);
                        _globalDragOver(evt);
                    }
                    break;
                case 'selectstart':
                    evt.preventDefault();
                    break;
            }
        },
        /**
         * Serializes the item into an array of string.
         * @returns {String[]}
         */
        toArray: function toArray() {
            var order = [],
                el,
                children = this.el.children,
                i = 0,
                n = children.length,
                options = this.options;
            for (; i < n; i++) {
                el = children[i];
                if (closest(el, options.draggable, this.el, false)) {
                    order.push(el.getAttribute(options.dataIdAttr) || _generateId(el));
                }
            }
            return order;
        },
        /**
         * Sorts the elements according to the array.
         * @param  {String[]}  order  order of the items
         */
        sort: function sort(order, useAnimation) {
            var items = {},
                rootEl = this.el;
            this.toArray().forEach(function (id, i) {
                var el = rootEl.children[i];
                if (closest(el, this.options.draggable, rootEl, false)) {
                    items[id] = el;
                }
            }, this);
            useAnimation && this.captureAnimationState();
            order.forEach(function (id) {
                if (items[id]) {
                    rootEl.removeChild(items[id]);
                    rootEl.appendChild(items[id]);
                }
            });
            useAnimation && this.animateAll();
        },
        /**
         * Save the current sorting
         */
        save: function save() {
            var store = this.options.store;
            store && store.set && store.set(this);
        },
        /**
         * For each element in the set, get the first element that matches the selector by testing the element itself and traversing up through its ancestors in the DOM tree.
         * @param   {HTMLElement}  el
         * @param   {String}       [selector]  default: `options.draggable`
         * @returns {HTMLElement|null}
         */
        closest: function closest$1(el, selector) {
            return closest(el, selector || this.options.draggable, this.el, false);
        },
        /**
         * Set/get option
         * @param   {string} name
         * @param   {*}      [value]
         * @returns {*}
         */
        option: function option(name, value) {
            var options = this.options;
            if (value === void 0) {
                return options[name];
            } else {
                var modifiedValue = PluginManager.modifyOption(this, name, value);
                if (typeof modifiedValue !== 'undefined') {
                    options[name] = modifiedValue;
                } else {
                    options[name] = value;
                }
                if (name === 'group') {
                    _prepareGroup(options);
                }
            }
        },
        /**
         * Destroy
         */
        destroy: function destroy() {
            pluginEvent('destroy', this);
            var el = this.el;
            el[expando] = null;
            off(el, 'mousedown', this._onTapStart);
            off(el, 'touchstart', this._onTapStart);
            off(el, 'pointerdown', this._onTapStart);
            if (this.nativeDraggable) {
                off(el, 'dragover', this);
                off(el, 'dragenter', this);
            }
            // Remove draggable attributes
            Array.prototype.forEach.call(el.querySelectorAll('[draggable]'), function (el) {
                el.removeAttribute('draggable');
            });
            this._onDrop();
            this._disableDelayedDragEvents();
            sortables.splice(sortables.indexOf(this.el), 1);
            this.el = el = null;
        },
        _hideClone: function _hideClone() {
            if (!cloneHidden) {
                pluginEvent('hideClone', this);
                if (Sortable.eventCanceled) return;
                css(cloneEl, 'display', 'none');
                if (this.options.removeCloneOnHide && cloneEl.parentNode) {
                    cloneEl.parentNode.removeChild(cloneEl);
                }
                cloneHidden = true;
            }
        },
        _showClone: function _showClone(putSortable) {
            if (putSortable.lastPutMode !== 'clone') {
                this._hideClone();
                return;
            }
            if (cloneHidden) {
                pluginEvent('showClone', this);
                if (Sortable.eventCanceled) return;

                // show clone at dragEl or original position
                if (dragEl.parentNode == rootEl && !this.options.group.revertClone) {
                    rootEl.insertBefore(cloneEl, dragEl);
                } else if (nextEl) {
                    rootEl.insertBefore(cloneEl, nextEl);
                } else {
                    rootEl.appendChild(cloneEl);
                }
                if (this.options.group.revertClone) {
                    this.animate(dragEl, cloneEl);
                }
                css(cloneEl, 'display', '');
                cloneHidden = false;
            }
        }
    };
    function _globalDragOver( /**Event*/evt) {
        if (evt.dataTransfer) {
            evt.dataTransfer.dropEffect = 'move';
        }
        evt.cancelable && evt.preventDefault();
    }
    function _onMove(fromEl, toEl, dragEl, dragRect, targetEl, targetRect, originalEvent, willInsertAfter) {
        var evt,
            sortable = fromEl[expando],
            onMoveFn = sortable.options.onMove,
            retVal;
        // Support for new CustomEvent feature
        if (window.CustomEvent && !IE11OrLess && !Edge) {
            evt = new CustomEvent('move', {
                bubbles: true,
                cancelable: true
            });
        } else {
            evt = document.createEvent('Event');
            evt.initEvent('move', true, true);
        }
        evt.to = toEl;
        evt.from = fromEl;
        evt.dragged = dragEl;
        evt.draggedRect = dragRect;
        evt.related = targetEl || toEl;
        evt.relatedRect = targetRect || getRect(toEl);
        evt.willInsertAfter = willInsertAfter;
        evt.originalEvent = originalEvent;
        fromEl.dispatchEvent(evt);
        if (onMoveFn) {
            retVal = onMoveFn.call(sortable, evt, originalEvent);
        }
        return retVal;
    }
    function _disableDraggable(el) {
        el.draggable = false;
    }
    function _unsilent() {
        _silent = false;
    }
    function _ghostIsFirst(evt, vertical, sortable) {
        var firstElRect = getRect(getChild(sortable.el, 0, sortable.options, true));
        var childContainingRect = getChildContainingRectFromElement(sortable.el, sortable.options, ghostEl);
        var spacer = 10;
        return vertical ? evt.clientX < childContainingRect.left - spacer || evt.clientY < firstElRect.top && evt.clientX < firstElRect.right : evt.clientY < childContainingRect.top - spacer || evt.clientY < firstElRect.bottom && evt.clientX < firstElRect.left;
    }
    function _ghostIsLast(evt, vertical, sortable) {
        var lastElRect = getRect(lastChild(sortable.el, sortable.options.draggable));
        var childContainingRect = getChildContainingRectFromElement(sortable.el, sortable.options, ghostEl);
        var spacer = 10;
        return vertical ? evt.clientX > childContainingRect.right + spacer || evt.clientY > lastElRect.bottom && evt.clientX > lastElRect.left : evt.clientY > childContainingRect.bottom + spacer || evt.clientX > lastElRect.right && evt.clientY > lastElRect.top;
    }
    function _getSwapDirection(evt, target, targetRect, vertical, swapThreshold, invertedSwapThreshold, invertSwap, isLastTarget) {
        var mouseOnAxis = vertical ? evt.clientY : evt.clientX,
            targetLength = vertical ? targetRect.height : targetRect.width,
            targetS1 = vertical ? targetRect.top : targetRect.left,
            targetS2 = vertical ? targetRect.bottom : targetRect.right,
            invert = false;
        if (!invertSwap) {
            // Never invert or create dragEl shadow when target movemenet causes mouse to move past the end of regular swapThreshold
            if (isLastTarget && targetMoveDistance < targetLength * swapThreshold) {
                // multiplied only by swapThreshold because mouse will already be inside target by (1 - threshold) * targetLength / 2
                // check if past first invert threshold on side opposite of lastDirection
                if (!pastFirstInvertThresh && (lastDirection === 1 ? mouseOnAxis > targetS1 + targetLength * invertedSwapThreshold / 2 : mouseOnAxis < targetS2 - targetLength * invertedSwapThreshold / 2)) {
                    // past first invert threshold, do not restrict inverted threshold to dragEl shadow
                    pastFirstInvertThresh = true;
                }
                if (!pastFirstInvertThresh) {
                    // dragEl shadow (target move distance shadow)
                    if (lastDirection === 1 ? mouseOnAxis < targetS1 + targetMoveDistance // over dragEl shadow
                        : mouseOnAxis > targetS2 - targetMoveDistance) {
                        return -lastDirection;
                    }
                } else {
                    invert = true;
                }
            } else {
                // Regular
                if (mouseOnAxis > targetS1 + targetLength * (1 - swapThreshold) / 2 && mouseOnAxis < targetS2 - targetLength * (1 - swapThreshold) / 2) {
                    return _getInsertDirection(target);
                }
            }
        }
        invert = invert || invertSwap;
        if (invert) {
            // Invert of regular
            if (mouseOnAxis < targetS1 + targetLength * invertedSwapThreshold / 2 || mouseOnAxis > targetS2 - targetLength * invertedSwapThreshold / 2) {
                return mouseOnAxis > targetS1 + targetLength / 2 ? 1 : -1;
            }
        }
        return 0;
    }

    /**
     * Gets the direction dragEl must be swapped relative to target in order to make it
     * seem that dragEl has been "inserted" into that element's position
     * @param  {HTMLElement} target       The target whose position dragEl is being inserted at
     * @return {Number}                   Direction dragEl must be swapped
     */
    function _getInsertDirection(target) {
        if (index(dragEl) < index(target)) {
            return 1;
        } else {
            return -1;
        }
    }

    /**
     * Generate id
     * @param   {HTMLElement} el
     * @returns {String}
     * @private
     */
    function _generateId(el) {
        var str = el.tagName + el.className + el.src + el.href + el.textContent,
            i = str.length,
            sum = 0;
        while (i--) {
            sum += str.charCodeAt(i);
        }
        return sum.toString(36);
    }
    function _saveInputCheckedState(root) {
        savedInputChecked.length = 0;
        var inputs = root.getElementsByTagName('input');
        var idx = inputs.length;
        while (idx--) {
            var el = inputs[idx];
            el.checked && savedInputChecked.push(el);
        }
    }
    function _nextTick(fn) {
        return setTimeout(fn, 0);
    }
    function _cancelNextTick(id) {
        return clearTimeout(id);
    }

    // Fixed #973:
    if (documentExists) {
        on(document, 'touchmove', function (evt) {
            if ((Sortable.active || awaitingDragStarted) && evt.cancelable) {
                evt.preventDefault();
            }
        });
    }

    // Export utils
    Sortable.utils = {
        on: on,
        off: off,
        css: css,
        find: find,
        is: function is(el, selector) {
            return !!closest(el, selector, el, false);
        },
        extend: extend,
        throttle: throttle,
        closest: closest,
        toggleClass: toggleClass,
        clone: clone,
        index: index,
        nextTick: _nextTick,
        cancelNextTick: _cancelNextTick,
        detectDirection: _detectDirection,
        getChild: getChild,
        expando: expando
    };

    /**
     * Get the Sortable instance of an element
     * @param  {HTMLElement} element The element
     * @return {Sortable|undefined}         The instance of Sortable
     */
    Sortable.get = function (element) {
        return element[expando];
    };

    /**
     * Mount a plugin to Sortable
     * @param  {...SortablePlugin|SortablePlugin[]} plugins       Plugins being mounted
     */
    Sortable.mount = function () {
        for (var _len = arguments.length, plugins = new Array(_len), _key = 0; _key < _len; _key++) {
            plugins[_key] = arguments[_key];
        }
        if (plugins[0].constructor === Array) plugins = plugins[0];
        plugins.forEach(function (plugin) {
            if (!plugin.prototype || !plugin.prototype.constructor) {
                throw "Sortable: Mounted plugin must be a constructor function, not ".concat({}.toString.call(plugin));
            }
            if (plugin.utils) Sortable.utils = _objectSpread2(_objectSpread2({}, Sortable.utils), plugin.utils);
            PluginManager.mount(plugin);
        });
    };

    /**
     * Create sortable instance
     * @param {HTMLElement}  el
     * @param {Object}      [options]
     */
    Sortable.create = function (el, options) {
        return new Sortable(el, options);
    };

    // Export
    Sortable.version = version;

    var autoScrolls = [],
        scrollEl,
        scrollRootEl,
        scrolling = false,
        lastAutoScrollX,
        lastAutoScrollY,
        touchEvt$1,
        pointerElemChangedInterval;
    function AutoScrollPlugin() {
        function AutoScroll() {
            this.defaults = {
                scroll: true,
                forceAutoScrollFallback: false,
                scrollSensitivity: 30,
                scrollSpeed: 10,
                bubbleScroll: true
            };

            // Bind all private methods
            for (var fn in this) {
                if (fn.charAt(0) === '_' && typeof this[fn] === 'function') {
                    this[fn] = this[fn].bind(this);
                }
            }
        }
        AutoScroll.prototype = {
            dragStarted: function dragStarted(_ref) {
                var originalEvent = _ref.originalEvent;
                if (this.sortable.nativeDraggable) {
                    on(document, 'dragover', this._handleAutoScroll);
                } else {
                    if (this.options.supportPointer) {
                        on(document, 'pointermove', this._handleFallbackAutoScroll);
                    } else if (originalEvent.touches) {
                        on(document, 'touchmove', this._handleFallbackAutoScroll);
                    } else {
                        on(document, 'mousemove', this._handleFallbackAutoScroll);
                    }
                }
            },
            dragOverCompleted: function dragOverCompleted(_ref2) {
                var originalEvent = _ref2.originalEvent;
                // For when bubbling is canceled and using fallback (fallback 'touchmove' always reached)
                if (!this.options.dragOverBubble && !originalEvent.rootEl) {
                    this._handleAutoScroll(originalEvent);
                }
            },
            drop: function drop() {
                if (this.sortable.nativeDraggable) {
                    off(document, 'dragover', this._handleAutoScroll);
                } else {
                    off(document, 'pointermove', this._handleFallbackAutoScroll);
                    off(document, 'touchmove', this._handleFallbackAutoScroll);
                    off(document, 'mousemove', this._handleFallbackAutoScroll);
                }
                clearPointerElemChangedInterval();
                clearAutoScrolls();
                cancelThrottle();
            },
            nulling: function nulling() {
                touchEvt$1 = scrollRootEl = scrollEl = scrolling = pointerElemChangedInterval = lastAutoScrollX = lastAutoScrollY = null;
                autoScrolls.length = 0;
            },
            _handleFallbackAutoScroll: function _handleFallbackAutoScroll(evt) {
                this._handleAutoScroll(evt, true);
            },
            _handleAutoScroll: function _handleAutoScroll(evt, fallback) {
                var _this = this;
                var x = (evt.touches ? evt.touches[0] : evt).clientX,
                    y = (evt.touches ? evt.touches[0] : evt).clientY,
                    elem = document.elementFromPoint(x, y);
                touchEvt$1 = evt;

                // IE does not seem to have native autoscroll,
                // Edge's autoscroll seems too conditional,
                // MACOS Safari does not have autoscroll,
                // Firefox and Chrome are good
                if (fallback || this.options.forceAutoScrollFallback || Edge || IE11OrLess || Safari) {
                    autoScroll(evt, this.options, elem, fallback);

                    // Listener for pointer element change
                    var ogElemScroller = getParentAutoScrollElement(elem, true);
                    if (scrolling && (!pointerElemChangedInterval || x !== lastAutoScrollX || y !== lastAutoScrollY)) {
                        pointerElemChangedInterval && clearPointerElemChangedInterval();
                        // Detect for pointer elem change, emulating native DnD behaviour
                        pointerElemChangedInterval = setInterval(function () {
                            var newElem = getParentAutoScrollElement(document.elementFromPoint(x, y), true);
                            if (newElem !== ogElemScroller) {
                                ogElemScroller = newElem;
                                clearAutoScrolls();
                            }
                            autoScroll(evt, _this.options, newElem, fallback);
                        }, 10);
                        lastAutoScrollX = x;
                        lastAutoScrollY = y;
                    }
                } else {
                    // if DnD is enabled (and browser has good autoscrolling), first autoscroll will already scroll, so get parent autoscroll of first autoscroll
                    if (!this.options.bubbleScroll || getParentAutoScrollElement(elem, true) === getWindowScrollingElement()) {
                        clearAutoScrolls();
                        return;
                    }
                    autoScroll(evt, this.options, getParentAutoScrollElement(elem, false), false);
                }
            }
        };
        return _extends(AutoScroll, {
            pluginName: 'scroll',
            initializeByDefault: true
        });
    }
    function clearAutoScrolls() {
        autoScrolls.forEach(function (autoScroll) {
            clearInterval(autoScroll.pid);
        });
        autoScrolls = [];
    }
    function clearPointerElemChangedInterval() {
        clearInterval(pointerElemChangedInterval);
    }
    var autoScroll = throttle(function (evt, options, rootEl, isFallback) {
        // Bug: https://bugzilla.mozilla.org/show_bug.cgi?id=505521
        if (!options.scroll) return;
        var x = (evt.touches ? evt.touches[0] : evt).clientX,
            y = (evt.touches ? evt.touches[0] : evt).clientY,
            sens = options.scrollSensitivity,
            speed = options.scrollSpeed,
            winScroller = getWindowScrollingElement();
        var scrollThisInstance = false,
            scrollCustomFn;

        // New scroll root, set scrollEl
        if (scrollRootEl !== rootEl) {
            scrollRootEl = rootEl;
            clearAutoScrolls();
            scrollEl = options.scroll;
            scrollCustomFn = options.scrollFn;
            if (scrollEl === true) {
                scrollEl = getParentAutoScrollElement(rootEl, true);
            }
        }
        var layersOut = 0;
        var currentParent = scrollEl;
        do {
            var el = currentParent,
                rect = getRect(el),
                top = rect.top,
                bottom = rect.bottom,
                left = rect.left,
                right = rect.right,
                width = rect.width,
                height = rect.height,
                canScrollX = void 0,
                canScrollY = void 0,
                scrollWidth = el.scrollWidth,
                scrollHeight = el.scrollHeight,
                elCSS = css(el),
                scrollPosX = el.scrollLeft,
                scrollPosY = el.scrollTop;
            if (el === winScroller) {
                canScrollX = width < scrollWidth && (elCSS.overflowX === 'auto' || elCSS.overflowX === 'scroll' || elCSS.overflowX === 'visible');
                canScrollY = height < scrollHeight && (elCSS.overflowY === 'auto' || elCSS.overflowY === 'scroll' || elCSS.overflowY === 'visible');
            } else {
                canScrollX = width < scrollWidth && (elCSS.overflowX === 'auto' || elCSS.overflowX === 'scroll');
                canScrollY = height < scrollHeight && (elCSS.overflowY === 'auto' || elCSS.overflowY === 'scroll');
            }
            var vx = canScrollX && (Math.abs(right - x) <= sens && scrollPosX + width < scrollWidth) - (Math.abs(left - x) <= sens && !!scrollPosX);
            var vy = canScrollY && (Math.abs(bottom - y) <= sens && scrollPosY + height < scrollHeight) - (Math.abs(top - y) <= sens && !!scrollPosY);
            if (!autoScrolls[layersOut]) {
                for (var i = 0; i <= layersOut; i++) {
                    if (!autoScrolls[i]) {
                        autoScrolls[i] = {};
                    }
                }
            }
            if (autoScrolls[layersOut].vx != vx || autoScrolls[layersOut].vy != vy || autoScrolls[layersOut].el !== el) {
                autoScrolls[layersOut].el = el;
                autoScrolls[layersOut].vx = vx;
                autoScrolls[layersOut].vy = vy;
                clearInterval(autoScrolls[layersOut].pid);
                if (vx != 0 || vy != 0) {
                    scrollThisInstance = true;
                    /* jshint loopfunc:true */
                    autoScrolls[layersOut].pid = setInterval(function () {
                        // emulate drag over during autoscroll (fallback), emulating native DnD behaviour
                        if (isFallback && this.layer === 0) {
                            Sortable.active._onTouchMove(touchEvt$1); // To move ghost if it is positioned absolutely
                        }
                        var scrollOffsetY = autoScrolls[this.layer].vy ? autoScrolls[this.layer].vy * speed : 0;
                        var scrollOffsetX = autoScrolls[this.layer].vx ? autoScrolls[this.layer].vx * speed : 0;
                        if (typeof scrollCustomFn === 'function') {
                            if (scrollCustomFn.call(Sortable.dragged.parentNode[expando], scrollOffsetX, scrollOffsetY, evt, touchEvt$1, autoScrolls[this.layer].el) !== 'continue') {
                                return;
                            }
                        }
                        scrollBy(autoScrolls[this.layer].el, scrollOffsetX, scrollOffsetY);
                    }.bind({
                        layer: layersOut
                    }), 24);
                }
            }
            layersOut++;
        } while (options.bubbleScroll && currentParent !== winScroller && (currentParent = getParentAutoScrollElement(currentParent, false)));
        scrolling = scrollThisInstance; // in case another function catches scrolling as false in between when it is not
    }, 30);

    var drop = function drop(_ref) {
        var originalEvent = _ref.originalEvent,
            putSortable = _ref.putSortable,
            dragEl = _ref.dragEl,
            activeSortable = _ref.activeSortable,
            dispatchSortableEvent = _ref.dispatchSortableEvent,
            hideGhostForTarget = _ref.hideGhostForTarget,
            unhideGhostForTarget = _ref.unhideGhostForTarget;
        if (!originalEvent) return;
        var toSortable = putSortable || activeSortable;
        hideGhostForTarget();
        var touch = originalEvent.changedTouches && originalEvent.changedTouches.length ? originalEvent.changedTouches[0] : originalEvent;
        var target = document.elementFromPoint(touch.clientX, touch.clientY);
        unhideGhostForTarget();
        if (toSortable && !toSortable.el.contains(target)) {
            dispatchSortableEvent('spill');
            this.onSpill({
                dragEl: dragEl,
                putSortable: putSortable
            });
        }
    };
    function Revert() { }
    Revert.prototype = {
        startIndex: null,
        dragStart: function dragStart(_ref2) {
            var oldDraggableIndex = _ref2.oldDraggableIndex;
            this.startIndex = oldDraggableIndex;
        },
        onSpill: function onSpill(_ref3) {
            var dragEl = _ref3.dragEl,
                putSortable = _ref3.putSortable;
            this.sortable.captureAnimationState();
            if (putSortable) {
                putSortable.captureAnimationState();
            }
            var nextSibling = getChild(this.sortable.el, this.startIndex, this.options);
            if (nextSibling) {
                this.sortable.el.insertBefore(dragEl, nextSibling);
            } else {
                this.sortable.el.appendChild(dragEl);
            }
            this.sortable.animateAll();
            if (putSortable) {
                putSortable.animateAll();
            }
        },
        drop: drop
    };
    _extends(Revert, {
        pluginName: 'revertOnSpill'
    });
    function Remove() { }
    Remove.prototype = {
        onSpill: function onSpill(_ref4) {
            var dragEl = _ref4.dragEl,
                putSortable = _ref4.putSortable;
            var parentSortable = putSortable || this.sortable;
            parentSortable.captureAnimationState();
            dragEl.parentNode && dragEl.parentNode.removeChild(dragEl);
            parentSortable.animateAll();
        },
        drop: drop
    };
    _extends(Remove, {
        pluginName: 'removeOnSpill'
    });

    var lastSwapEl;
    function SwapPlugin() {
        function Swap() {
            this.defaults = {
                swapClass: 'sortable-swap-highlight'
            };
        }
        Swap.prototype = {
            dragStart: function dragStart(_ref) {
                var dragEl = _ref.dragEl;
                lastSwapEl = dragEl;
            },
            dragOverValid: function dragOverValid(_ref2) {
                var completed = _ref2.completed,
                    target = _ref2.target,
                    onMove = _ref2.onMove,
                    activeSortable = _ref2.activeSortable,
                    changed = _ref2.changed,
                    cancel = _ref2.cancel;
                if (!activeSortable.options.swap) return;
                var el = this.sortable.el,
                    options = this.options;
                if (target && target !== el) {
                    var prevSwapEl = lastSwapEl;
                    if (onMove(target) !== false) {
                        toggleClass(target, options.swapClass, true);
                        lastSwapEl = target;
                    } else {
                        lastSwapEl = null;
                    }
                    if (prevSwapEl && prevSwapEl !== lastSwapEl) {
                        toggleClass(prevSwapEl, options.swapClass, false);
                    }
                }
                changed();
                completed(true);
                cancel();
            },
            drop: function drop(_ref3) {
                var activeSortable = _ref3.activeSortable,
                    putSortable = _ref3.putSortable,
                    dragEl = _ref3.dragEl;
                var toSortable = putSortable || this.sortable;
                var options = this.options;
                lastSwapEl && toggleClass(lastSwapEl, options.swapClass, false);
                if (lastSwapEl && (options.swap || putSortable && putSortable.options.swap)) {
                    if (dragEl !== lastSwapEl) {
                        toSortable.captureAnimationState();
                        if (toSortable !== activeSortable) activeSortable.captureAnimationState();
                        swapNodes(dragEl, lastSwapEl);
                        toSortable.animateAll();
                        if (toSortable !== activeSortable) activeSortable.animateAll();
                    }
                }
            },
            nulling: function nulling() {
                lastSwapEl = null;
            }
        };
        return _extends(Swap, {
            pluginName: 'swap',
            eventProperties: function eventProperties() {
                return {
                    swapItem: lastSwapEl
                };
            }
        });
    }
    function swapNodes(n1, n2) {
        var p1 = n1.parentNode,
            p2 = n2.parentNode,
            i1,
            i2;
        if (!p1 || !p2 || p1.isEqualNode(n2) || p2.isEqualNode(n1)) return;
        i1 = index(n1);
        i2 = index(n2);
        if (p1.isEqualNode(p2) && i1 < i2) {
            i2++;
        }
        p1.insertBefore(n2, p1.children[i1]);
        p2.insertBefore(n1, p2.children[i2]);
    }

    var multiDragElements = [],
        multiDragClones = [],
        lastMultiDragSelect,
        // for selection with modifier key down (SHIFT)
        multiDragSortable,
        initialFolding = false,
        // Initial multi-drag fold when drag started
        folding = false,
        // Folding any other time
        dragStarted = false,
        dragEl$1,
        clonesFromRect,
        clonesHidden;
    function MultiDragPlugin() {
        function MultiDrag(sortable) {
            // Bind all private methods
            for (var fn in this) {
                if (fn.charAt(0) === '_' && typeof this[fn] === 'function') {
                    this[fn] = this[fn].bind(this);
                }
            }
            if (!sortable.options.avoidImplicitDeselect) {
                if (sortable.options.supportPointer) {
                    on(document, 'pointerup', this._deselectMultiDrag);
                } else {
                    on(document, 'mouseup', this._deselectMultiDrag);
                    on(document, 'touchend', this._deselectMultiDrag);
                }
            }
            on(document, 'keydown', this._checkKeyDown);
            on(document, 'keyup', this._checkKeyUp);
            this.defaults = {
                selectedClass: 'sortable-selected',
                multiDragKey: null,
                avoidImplicitDeselect: false,
                setData: function setData(dataTransfer, dragEl) {
                    var data = '';
                    if (multiDragElements.length && multiDragSortable === sortable) {
                        multiDragElements.forEach(function (multiDragElement, i) {
                            data += (!i ? '' : ', ') + multiDragElement.textContent;
                        });
                    } else {
                        data = dragEl.textContent;
                    }
                    dataTransfer.setData('Text', data);
                }
            };
        }
        MultiDrag.prototype = {
            multiDragKeyDown: false,
            isMultiDrag: false,
            delayStartGlobal: function delayStartGlobal(_ref) {
                var dragged = _ref.dragEl;
                dragEl$1 = dragged;
            },
            delayEnded: function delayEnded() {
                this.isMultiDrag = ~multiDragElements.indexOf(dragEl$1);
            },
            setupClone: function setupClone(_ref2) {
                var sortable = _ref2.sortable,
                    cancel = _ref2.cancel;
                if (!this.isMultiDrag) return;
                for (var i = 0; i < multiDragElements.length; i++) {
                    multiDragClones.push(clone(multiDragElements[i]));
                    multiDragClones[i].sortableIndex = multiDragElements[i].sortableIndex;
                    multiDragClones[i].draggable = false;
                    multiDragClones[i].style['will-change'] = '';
                    toggleClass(multiDragClones[i], this.options.selectedClass, false);
                    multiDragElements[i] === dragEl$1 && toggleClass(multiDragClones[i], this.options.chosenClass, false);
                }
                sortable._hideClone();
                cancel();
            },
            clone: function clone(_ref3) {
                var sortable = _ref3.sortable,
                    rootEl = _ref3.rootEl,
                    dispatchSortableEvent = _ref3.dispatchSortableEvent,
                    cancel = _ref3.cancel;
                if (!this.isMultiDrag) return;
                if (!this.options.removeCloneOnHide) {
                    if (multiDragElements.length && multiDragSortable === sortable) {
                        insertMultiDragClones(true, rootEl);
                        dispatchSortableEvent('clone');
                        cancel();
                    }
                }
            },
            showClone: function showClone(_ref4) {
                var cloneNowShown = _ref4.cloneNowShown,
                    rootEl = _ref4.rootEl,
                    cancel = _ref4.cancel;
                if (!this.isMultiDrag) return;
                insertMultiDragClones(false, rootEl);
                multiDragClones.forEach(function (clone) {
                    css(clone, 'display', '');
                });
                cloneNowShown();
                clonesHidden = false;
                cancel();
            },
            hideClone: function hideClone(_ref5) {
                var _this = this;
                var sortable = _ref5.sortable,
                    cloneNowHidden = _ref5.cloneNowHidden,
                    cancel = _ref5.cancel;
                if (!this.isMultiDrag) return;
                multiDragClones.forEach(function (clone) {
                    css(clone, 'display', 'none');
                    if (_this.options.removeCloneOnHide && clone.parentNode) {
                        clone.parentNode.removeChild(clone);
                    }
                });
                cloneNowHidden();
                clonesHidden = true;
                cancel();
            },
            dragStartGlobal: function dragStartGlobal(_ref6) {
                var sortable = _ref6.sortable;
                if (!this.isMultiDrag && multiDragSortable) {
                    multiDragSortable.multiDrag._deselectMultiDrag();
                }
                multiDragElements.forEach(function (multiDragElement) {
                    multiDragElement.sortableIndex = index(multiDragElement);
                });

                // Sort multi-drag elements
                multiDragElements = multiDragElements.sort(function (a, b) {
                    return a.sortableIndex - b.sortableIndex;
                });
                dragStarted = true;
            },
            dragStarted: function dragStarted(_ref7) {
                var _this2 = this;
                var sortable = _ref7.sortable;
                if (!this.isMultiDrag) return;
                if (this.options.sort) {
                    // Capture rects,
                    // hide multi drag elements (by positioning them absolute),
                    // set multi drag elements rects to dragRect,
                    // show multi drag elements,
                    // animate to rects,
                    // unset rects & remove from DOM

                    sortable.captureAnimationState();
                    if (this.options.animation) {
                        multiDragElements.forEach(function (multiDragElement) {
                            if (multiDragElement === dragEl$1) return;
                            css(multiDragElement, 'position', 'absolute');
                        });
                        var dragRect = getRect(dragEl$1, false, true, true);
                        multiDragElements.forEach(function (multiDragElement) {
                            if (multiDragElement === dragEl$1) return;
                            setRect(multiDragElement, dragRect);
                        });
                        folding = true;
                        initialFolding = true;
                    }
                }
                sortable.animateAll(function () {
                    folding = false;
                    initialFolding = false;
                    if (_this2.options.animation) {
                        multiDragElements.forEach(function (multiDragElement) {
                            unsetRect(multiDragElement);
                        });
                    }

                    // Remove all auxiliary multidrag items from el, if sorting enabled
                    if (_this2.options.sort) {
                        removeMultiDragElements();
                    }
                });
            },
            dragOver: function dragOver(_ref8) {
                var target = _ref8.target,
                    completed = _ref8.completed,
                    cancel = _ref8.cancel;
                if (folding && ~multiDragElements.indexOf(target)) {
                    completed(false);
                    cancel();
                }
            },
            revert: function revert(_ref9) {
                var fromSortable = _ref9.fromSortable,
                    rootEl = _ref9.rootEl,
                    sortable = _ref9.sortable,
                    dragRect = _ref9.dragRect;
                if (multiDragElements.length > 1) {
                    // Setup unfold animation
                    multiDragElements.forEach(function (multiDragElement) {
                        sortable.addAnimationState({
                            target: multiDragElement,
                            rect: folding ? getRect(multiDragElement) : dragRect
                        });
                        unsetRect(multiDragElement);
                        multiDragElement.fromRect = dragRect;
                        fromSortable.removeAnimationState(multiDragElement);
                    });
                    folding = false;
                    insertMultiDragElements(!this.options.removeCloneOnHide, rootEl);
                }
            },
            dragOverCompleted: function dragOverCompleted(_ref10) {
                var sortable = _ref10.sortable,
                    isOwner = _ref10.isOwner,
                    insertion = _ref10.insertion,
                    activeSortable = _ref10.activeSortable,
                    parentEl = _ref10.parentEl,
                    putSortable = _ref10.putSortable;
                var options = this.options;
                if (insertion) {
                    // Clones must be hidden before folding animation to capture dragRectAbsolute properly
                    if (isOwner) {
                        activeSortable._hideClone();
                    }
                    initialFolding = false;
                    // If leaving sort:false root, or already folding - Fold to new location
                    if (options.animation && multiDragElements.length > 1 && (folding || !isOwner && !activeSortable.options.sort && !putSortable)) {
                        // Fold: Set all multi drag elements's rects to dragEl's rect when multi-drag elements are invisible
                        var dragRectAbsolute = getRect(dragEl$1, false, true, true);
                        multiDragElements.forEach(function (multiDragElement) {
                            if (multiDragElement === dragEl$1) return;
                            setRect(multiDragElement, dragRectAbsolute);

                            // Move element(s) to end of parentEl so that it does not interfere with multi-drag clones insertion if they are inserted
                            // while folding, and so that we can capture them again because old sortable will no longer be fromSortable
                            parentEl.appendChild(multiDragElement);
                        });
                        folding = true;
                    }

                    // Clones must be shown (and check to remove multi drags) after folding when interfering multiDragElements are moved out
                    if (!isOwner) {
                        // Only remove if not folding (folding will remove them anyways)
                        if (!folding) {
                            removeMultiDragElements();
                        }
                        if (multiDragElements.length > 1) {
                            var clonesHiddenBefore = clonesHidden;
                            activeSortable._showClone(sortable);

                            // Unfold animation for clones if showing from hidden
                            if (activeSortable.options.animation && !clonesHidden && clonesHiddenBefore) {
                                multiDragClones.forEach(function (clone) {
                                    activeSortable.addAnimationState({
                                        target: clone,
                                        rect: clonesFromRect
                                    });
                                    clone.fromRect = clonesFromRect;
                                    clone.thisAnimationDuration = null;
                                });
                            }
                        } else {
                            activeSortable._showClone(sortable);
                        }
                    }
                }
            },
            dragOverAnimationCapture: function dragOverAnimationCapture(_ref11) {
                var dragRect = _ref11.dragRect,
                    isOwner = _ref11.isOwner,
                    activeSortable = _ref11.activeSortable;
                multiDragElements.forEach(function (multiDragElement) {
                    multiDragElement.thisAnimationDuration = null;
                });
                if (activeSortable.options.animation && !isOwner && activeSortable.multiDrag.isMultiDrag) {
                    clonesFromRect = _extends({}, dragRect);
                    var dragMatrix = matrix(dragEl$1, true);
                    clonesFromRect.top -= dragMatrix.f;
                    clonesFromRect.left -= dragMatrix.e;
                }
            },
            dragOverAnimationComplete: function dragOverAnimationComplete() {
                if (folding) {
                    folding = false;
                    removeMultiDragElements();
                }
            },
            drop: function drop(_ref12) {
                var evt = _ref12.originalEvent,
                    rootEl = _ref12.rootEl,
                    parentEl = _ref12.parentEl,
                    sortable = _ref12.sortable,
                    dispatchSortableEvent = _ref12.dispatchSortableEvent,
                    oldIndex = _ref12.oldIndex,
                    putSortable = _ref12.putSortable;
                var toSortable = putSortable || this.sortable;
                if (!evt) return;
                var options = this.options,
                    children = parentEl.children;

                // Multi-drag selection
                if (!dragStarted) {
                    if (options.multiDragKey && !this.multiDragKeyDown) {
                        this._deselectMultiDrag();
                    }
                    toggleClass(dragEl$1, options.selectedClass, !~multiDragElements.indexOf(dragEl$1));
                    if (!~multiDragElements.indexOf(dragEl$1)) {
                        multiDragElements.push(dragEl$1);
                        dispatchEvent({
                            sortable: sortable,
                            rootEl: rootEl,
                            name: 'select',
                            targetEl: dragEl$1,
                            originalEvent: evt
                        });

                        // Modifier activated, select from last to dragEl
                        if (evt.shiftKey && lastMultiDragSelect && sortable.el.contains(lastMultiDragSelect)) {
                            var lastIndex = index(lastMultiDragSelect),
                                currentIndex = index(dragEl$1);
                            if (~lastIndex && ~currentIndex && lastIndex !== currentIndex) {
                                (function () {
                                    // Must include lastMultiDragSelect (select it), in case modified selection from no selection
                                    // (but previous selection existed)
                                    var n, i;
                                    if (currentIndex > lastIndex) {
                                        i = lastIndex;
                                        n = currentIndex;
                                    } else {
                                        i = currentIndex;
                                        n = lastIndex + 1;
                                    }
                                    var filter = options.filter;
                                    for (; i < n; i++) {
                                        if (~multiDragElements.indexOf(children[i])) continue;
                                        // Check if element is draggable
                                        if (!closest(children[i], options.draggable, parentEl, false)) continue;
                                        // Check if element is filtered
                                        var filtered = filter && (typeof filter === 'function' ? filter.call(sortable, evt, children[i], sortable) : filter.split(',').some(function (criteria) {
                                            return closest(children[i], criteria.trim(), parentEl, false);
                                        }));
                                        if (filtered) continue;
                                        toggleClass(children[i], options.selectedClass, true);
                                        multiDragElements.push(children[i]);
                                        dispatchEvent({
                                            sortable: sortable,
                                            rootEl: rootEl,
                                            name: 'select',
                                            targetEl: children[i],
                                            originalEvent: evt
                                        });
                                    }
                                })();
                            }
                        } else {
                            lastMultiDragSelect = dragEl$1;
                        }
                        multiDragSortable = toSortable;
                    } else {
                        multiDragElements.splice(multiDragElements.indexOf(dragEl$1), 1);
                        lastMultiDragSelect = null;
                        dispatchEvent({
                            sortable: sortable,
                            rootEl: rootEl,
                            name: 'deselect',
                            targetEl: dragEl$1,
                            originalEvent: evt
                        });
                    }
                }

                // Multi-drag drop
                if (dragStarted && this.isMultiDrag) {
                    folding = false;
                    // Do not "unfold" after around dragEl if reverted
                    if ((parentEl[expando].options.sort || parentEl !== rootEl) && multiDragElements.length > 1) {
                        var dragRect = getRect(dragEl$1),
                            multiDragIndex = index(dragEl$1, ':not(.' + this.options.selectedClass + ')');
                        if (!initialFolding && options.animation) dragEl$1.thisAnimationDuration = null;
                        toSortable.captureAnimationState();
                        if (!initialFolding) {
                            if (options.animation) {
                                dragEl$1.fromRect = dragRect;
                                multiDragElements.forEach(function (multiDragElement) {
                                    multiDragElement.thisAnimationDuration = null;
                                    if (multiDragElement !== dragEl$1) {
                                        var rect = folding ? getRect(multiDragElement) : dragRect;
                                        multiDragElement.fromRect = rect;

                                        // Prepare unfold animation
                                        toSortable.addAnimationState({
                                            target: multiDragElement,
                                            rect: rect
                                        });
                                    }
                                });
                            }

                            // Multi drag elements are not necessarily removed from the DOM on drop, so to reinsert
                            // properly they must all be removed
                            removeMultiDragElements();
                            multiDragElements.forEach(function (multiDragElement) {
                                if (children[multiDragIndex]) {
                                    parentEl.insertBefore(multiDragElement, children[multiDragIndex]);
                                } else {
                                    parentEl.appendChild(multiDragElement);
                                }
                                multiDragIndex++;
                            });

                            // If initial folding is done, the elements may have changed position because they are now
                            // unfolding around dragEl, even though dragEl may not have his index changed, so update event
                            // must be fired here as Sortable will not.
                            if (oldIndex === index(dragEl$1)) {
                                var update = false;
                                multiDragElements.forEach(function (multiDragElement) {
                                    if (multiDragElement.sortableIndex !== index(multiDragElement)) {
                                        update = true;
                                        return;
                                    }
                                });
                                if (update) {
                                    dispatchSortableEvent('update');
                                    dispatchSortableEvent('sort');
                                }
                            }
                        }

                        // Must be done after capturing individual rects (scroll bar)
                        multiDragElements.forEach(function (multiDragElement) {
                            unsetRect(multiDragElement);
                        });
                        toSortable.animateAll();
                    }
                    multiDragSortable = toSortable;
                }

                // Remove clones if necessary
                if (rootEl === parentEl || putSortable && putSortable.lastPutMode !== 'clone') {
                    multiDragClones.forEach(function (clone) {
                        clone.parentNode && clone.parentNode.removeChild(clone);
                    });
                }
            },
            nullingGlobal: function nullingGlobal() {
                this.isMultiDrag = dragStarted = false;
                multiDragClones.length = 0;
            },
            destroyGlobal: function destroyGlobal() {
                this._deselectMultiDrag();
                off(document, 'pointerup', this._deselectMultiDrag);
                off(document, 'mouseup', this._deselectMultiDrag);
                off(document, 'touchend', this._deselectMultiDrag);
                off(document, 'keydown', this._checkKeyDown);
                off(document, 'keyup', this._checkKeyUp);
            },
            _deselectMultiDrag: function _deselectMultiDrag(evt) {
                if (typeof dragStarted !== "undefined" && dragStarted) return;

                // Only deselect if selection is in this sortable
                if (multiDragSortable !== this.sortable) return;

                // Only deselect if target is not item in this sortable
                if (evt && closest(evt.target, this.options.draggable, this.sortable.el, false)) return;

                // Only deselect if left click
                if (evt && evt.button !== 0) return;
                while (multiDragElements.length) {
                    var el = multiDragElements[0];
                    toggleClass(el, this.options.selectedClass, false);
                    multiDragElements.shift();
                    dispatchEvent({
                        sortable: this.sortable,
                        rootEl: this.sortable.el,
                        name: 'deselect',
                        targetEl: el,
                        originalEvent: evt
                    });
                }
            },
            _checkKeyDown: function _checkKeyDown(evt) {
                if (evt.key === this.options.multiDragKey) {
                    this.multiDragKeyDown = true;
                }
            },
            _checkKeyUp: function _checkKeyUp(evt) {
                if (evt.key === this.options.multiDragKey) {
                    this.multiDragKeyDown = false;
                }
            }
        };
        return _extends(MultiDrag, {
            // Static methods & properties
            pluginName: 'multiDrag',
            utils: {
                /**
                 * Selects the provided multi-drag item
                 * @param  {HTMLElement} el    The element to be selected
                 */
                select: function select(el) {
                    var sortable = el.parentNode[expando];
                    if (!sortable || !sortable.options.multiDrag || ~multiDragElements.indexOf(el)) return;
                    if (multiDragSortable && multiDragSortable !== sortable) {
                        multiDragSortable.multiDrag._deselectMultiDrag();
                        multiDragSortable = sortable;
                    }
                    toggleClass(el, sortable.options.selectedClass, true);
                    multiDragElements.push(el);
                },
                /**
                 * Deselects the provided multi-drag item
                 * @param  {HTMLElement} el    The element to be deselected
                 */
                deselect: function deselect(el) {
                    var sortable = el.parentNode[expando],
                        index = multiDragElements.indexOf(el);
                    if (!sortable || !sortable.options.multiDrag || !~index) return;
                    toggleClass(el, sortable.options.selectedClass, false);
                    multiDragElements.splice(index, 1);
                }
            },
            eventProperties: function eventProperties() {
                var _this3 = this;
                var oldIndicies = [],
                    newIndicies = [];
                multiDragElements.forEach(function (multiDragElement) {
                    oldIndicies.push({
                        multiDragElement: multiDragElement,
                        index: multiDragElement.sortableIndex
                    });

                    // multiDragElements will already be sorted if folding
                    var newIndex;
                    if (folding && multiDragElement !== dragEl$1) {
                        newIndex = -1;
                    } else if (folding) {
                        newIndex = index(multiDragElement, ':not(.' + _this3.options.selectedClass + ')');
                    } else {
                        newIndex = index(multiDragElement);
                    }
                    newIndicies.push({
                        multiDragElement: multiDragElement,
                        index: newIndex
                    });
                });
                return {
                    items: _toConsumableArray(multiDragElements),
                    clones: [].concat(multiDragClones),
                    oldIndicies: oldIndicies,
                    newIndicies: newIndicies
                };
            },
            optionListeners: {
                multiDragKey: function multiDragKey(key) {
                    key = key.toLowerCase();
                    if (key === 'ctrl') {
                        key = 'Control';
                    } else if (key.length > 1) {
                        key = key.charAt(0).toUpperCase() + key.substr(1);
                    }
                    return key;
                }
            }
        });
    }
    function insertMultiDragElements(clonesInserted, rootEl) {
        multiDragElements.forEach(function (multiDragElement, i) {
            var target = rootEl.children[multiDragElement.sortableIndex + (clonesInserted ? Number(i) : 0)];
            if (target) {
                rootEl.insertBefore(multiDragElement, target);
            } else {
                rootEl.appendChild(multiDragElement);
            }
        });
    }

    /**
     * Insert multi-drag clones
     * @param  {[Boolean]} elementsInserted  Whether the multi-drag elements are inserted
     * @param  {HTMLElement} rootEl
     */
    function insertMultiDragClones(elementsInserted, rootEl) {
        multiDragClones.forEach(function (clone, i) {
            var target = rootEl.children[clone.sortableIndex + (elementsInserted ? Number(i) : 0)];
            if (target) {
                rootEl.insertBefore(clone, target);
            } else {
                rootEl.appendChild(clone);
            }
        });
    }
    function removeMultiDragElements() {
        multiDragElements.forEach(function (multiDragElement) {
            if (multiDragElement === dragEl$1) return;
            multiDragElement.parentNode && multiDragElement.parentNode.removeChild(multiDragElement);
        });
    }

    Sortable.mount(new AutoScrollPlugin());
    Sortable.mount(Remove, Revert);

    Sortable.mount(new SwapPlugin());
    Sortable.mount(new MultiDragPlugin());

    return Sortable;

})));


/* --- Start of 4-toast.js --- */
/**
 * Sistema de Toasts para el Editor
 * Basado en el partial shared/toast.phtml
 */

(function () {
    "use strict";

    /**
     * Elimina un toast de forma suave
     */
    function removeToast(el) {
        if (!el || el.classList.contains('removing')) return;

        // Obtenemos la altura actual para que la transición de max-height funcione
        el.style.maxHeight = el.offsetHeight + 'px';

        // Forzamos un reflow
        el.offsetHeight;

        el.classList.add('removing');

        setTimeout(() => {
            el.remove();
        }, 600); // Un poco más que la transición CSS
    }

    /**
     * Configura el temporizador de auto-remoción para un elemento
     */
    function setupTimeout(el) {
        const seconds = parseInt(el.dataset.timeout);
        if (seconds > 0) {
            setTimeout(() => {
                removeToast(el);
            }, seconds * 1000);
        }
    }

    // Delegación para cerrar toasts manualmente
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.toast-container [data-remove="parent"]');
        if (btn) {
            const toast = btn.closest('.toast-container > div');
            if (toast) removeToast(toast);
        }
    });

    /**
     * Inyecta HTML de toasts en la página
     */
    window.spawnToasts = function (html) {
        if (!html) return;

        let container = document.querySelector('.toast-container');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const newToasts = Array.from(wrapper.querySelectorAll('.toast-container > div, [data-timeout]'));

        if (!container && newToasts.length > 0) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        newToasts.forEach(toast => {
            container.appendChild(toast);
            setupTimeout(toast);
        });
    };

    /**
     * Helper para lanzar toasts con el mismo formato que el partial PHP
     */
    window.editorToast = function (message, type = 'info') {
        const title = (type === 'error') ? 'ERROR' : 'INFO';
        const html = '<div class="toast-container"><div data-timeout="8"> ' +
            '<button type="button" class="transparent" data-remove="parent"><img src="/img/icons/x.svg"></button> ' +
            '<h3>' + title + '</h3><hr><p>' + message + '</p></div></div>';
        window.spawnToasts(html);
    };

    // Al cargar, inicializamos los toasts que ya existan en el DOM (si los hay)
    document.querySelectorAll('.toast-container [data-timeout]').forEach(setupTimeout);

})();


/* --- Start of 4-uploader.js --- */
// w = window (Referencia al objeto global del navegador)
// d = document (Referencia al árbol DOM de la página actual)
(function (w, d) {
	'use strict';

	// ==========================================
	// Endpoint genérico temporal para la subida
	var UPLOAD_URL = '/uploader/upload';
	// Nivel de compresión (0-100). 100 = sin pérdida
	var COMPRESSION_LOSS = 100;
	// Límite de tamaño por archivo en Megabytes
	var MAX_FILE_SIZE_MB = 20;
	// Extensiones válidas
	var ALLOWED_EXTENSIONS = ['avif', 'bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'webp'];
	// Ancho máximo al que se debería redimensionar
	var MAX_IMAGE_WIDTH = 1920;
	// Formato de auto-conversión para el backend (ej: 'webp', false)
	var AUTO_CONVERT_FORMAT = 'webp';
	// Activa o desactiva los logs del Uploader en la consola
	var DEBUG_MODE = false;
	// Tiempo máximo de espera para la subida HTTP
	var UPLOAD_TIMEOUT_MS = 120000;
	// Textos de Notificaciones UI (Traducción al español por defecto para Roleplus)
	var STR_LOADING = 'Cargando {name}...';
	var STR_ERROR_SIZE = 'Error: La imagen "{name}" excede el límite de {mb}MB.';
	var STR_ERROR_NETWORK = 'Error de red al subir la imagen.';
	var STR_ERROR_TIMEOUT = 'Error: Tiempo de espera agotado ({s}s).';
	var STR_SUCCESS_COPIED = 'Imagen guardada: {url} (ruta copiada).';
	var STR_SUCCESS_NO_COPY = 'Imagen guardada: {url} (no se pudo copiar).';
	// ==========================================

	// CONTROL GLOBAL DE ARRASTRES INTERNOS (Prevenir clonados)
	var IS_INTERNAL_DRAG = false;
	d.addEventListener('dragstart', function () { IS_INTERNAL_DRAG = true; }, true);
	d.addEventListener('dragend', function () { IS_INTERNAL_DRAG = false; }, true);

	/**
	 * Clase Universal para Subida Rápida de Imágenes (Drag & Paste)
	 */
	function UniversalImageUploader(options) {
		options = options || {};

		this.cfg = {
			endpoint: options.endpoint || UPLOAD_URL,
			compressionLoss: options.compressionLoss !== undefined ? options.compressionLoss : COMPRESSION_LOSS,
			maxFileSizeMb: options.maxFileSizeMb || MAX_FILE_SIZE_MB,
			allowedExtensions: options.allowedExtensions || ALLOWED_EXTENSIONS,
			maxImageWidth: options.maxImageWidth || MAX_IMAGE_WIDTH,
			autoConvertFormat: options.autoConvertFormat !== undefined ? options.autoConvertFormat : AUTO_CONVERT_FORMAT,
			debugMode: options.debugMode !== undefined ? options.debugMode : DEBUG_MODE,
			uploadTimeoutMs: options.uploadTimeoutMs || UPLOAD_TIMEOUT_MS,
			dropZone: options.dropZone || d.body,

			// Traducciones por opciones o fallback global
			strLoading: options.strLoading || STR_LOADING,
			strErrorSize: options.strErrorSize || STR_ERROR_SIZE,
			strErrorNetwork: options.strErrorNetwork || STR_ERROR_NETWORK,
			strErrorTimeout: options.strErrorTimeout || STR_ERROR_TIMEOUT,
			strSuccessCopied: options.strSuccessCopied || STR_SUCCESS_COPIED,
			strSuccessNoCopy: options.strSuccessNoCopy || STR_SUCCESS_NO_COPY
		};

		this.IMG_EXT = new RegExp('\\.(' + this.cfg.allowedExtensions.join('|') + ')$', 'i');
		this.panel = null;
		this.panelMsg = null;
		this.lastOver = 0;

		this.init();
	}

	UniversalImageUploader.prototype = {
		constructor: UniversalImageUploader,

		L: function () { if (this.cfg.debugMode) { try { console.log.apply(console, ['[Uploader]'].concat(Array.prototype.slice.call(arguments))); } catch (_) { } } },
		E: function () { if (this.cfg.debugMode) { try { console.error.apply(console, ['[Uploader]'].concat(Array.prototype.slice.call(arguments))); } catch (_) { } } },

		isImgFile: function (f) {
			return f && (/^image\//i.test(f.type || '') || this.IMG_EXT.test(f.name || ''));
		},

		isFileDrag: function (e) {
			var dt = e.dataTransfer;
			if (!dt) return false;
			if (dt.types && Array.prototype.indexOf.call(dt.types, 'Files') !== -1) return true;
			if (dt.items) {
				for (var i = 0; i < dt.items.length; i++) {
					if (dt.items[i].kind === 'file') return true;
				}
			}
			return false;
		},

		filesFrom: function (dt) {
			var out = [], i, items = dt.items || [];
			if (dt.files && dt.files.length) {
				for (i = 0; i < dt.files.length; i++) out.push(dt.files[i]);
			} else if (items.length) {
				for (i = 0; i < items.length; i++) {
					if (items[i].kind === 'file') {
						var f = items[i].getAsFile && items[i].getAsFile();
						if (f) out.push(f);
					}
				}
			}
			return out;
		},

		ensurePanel: function () {
			if (this.panel) return this.panel;
			var p = d.createElement('div');
			p.style.cssText = 'display:none;position:fixed;z-index:2147483647;top:12px;left:12px;width:380px;max-height:42vh;overflow:auto;background:rgba(0,0,0,.85);color:#fff;padding:18px 12px 12px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.4);font:12px/1.4 ui-monospace,Menlo,Consolas,monospace';

			var b = d.createElement('button');
			b.textContent = '×';
			b.style.cssText = 'position:absolute;top:6px;right:6px;width:22px;height:22px;border:0;border-radius:50%;background:#444;color:#fff;cursor:pointer;line-height:22px;font:14px/22px ui-sans-serif,system-ui;padding:0';
			var self = this;
			b.onclick = function () { self.panel.style.display = 'none'; };

			var m = d.createElement('div');
			p.appendChild(b); p.appendChild(m);
			d.body.appendChild(p);
		upload: function (file) {
			var self = this;
			if (file.size && file.size > this.cfg.maxFileSizeMb * 1024 * 1024) {
				this.showPanel(this.cfg.strErrorSize.replace('{name}', file.name || 'adjunto').replace('{mb}', this.cfg.maxFileSizeMb));
				return;
			}

			this.showPanel(this.cfg.strLoading.replace('{name}', file.name || 'imagen'));

			this.L('upload →', this.cfg.endpoint, { name: file.name, type: file.type, size: file.size, loss: this.cfg.compressionLoss });

			var fd = new FormData();
			fd.append('file', file, file.name || 'image');
			fd.append('loss', this.cfg.compressionLoss);
			fd.append('max_width', this.cfg.maxImageWidth);
			if (this.cfg.autoConvertFormat) fd.append('convert_to', this.cfg.autoConvertFormat);

			var x = new XMLHttpRequest();
			x.timeout = this.cfg.uploadTimeoutMs;
			x.open('POST', this.cfg.endpoint, true);
			x.withCredentials = true;

			x.upload.onprogress = function (e) {
				if (e && e.lengthComputable) self.L('progress', Math.round(e.loaded * 100 / e.total) + '%', e.loaded + '/' + e.total);
			};

			x.onreadystatechange = function () {
				if (x.readyState === 4) {
					var body; try { body = JSON.parse(x.responseText); } catch (_) { body = { raw: x.responseText }; }
					var ok = x.status >= 200 && x.status < 300 && !body.error;
					self.L('done', { status: x.status, ok: ok, body: body });

					if (ok) {
						var url = body.url || '';
						self.copyText(url, function (copied) {
							var msg = copied ? self.cfg.strSuccessCopied : self.cfg.strSuccessNoCopy;
							self.showPanel(msg.replace('{url}', url));
						});
						d.dispatchEvent(new CustomEvent('image-uploaded', { detail: { status: x.status, ok: true, body: body, url: url, file: file } }));
					} else {
						self.showPanel('Error: ' + (body.error || body.raw || ('HTTP ' + x.status)));
						d.dispatchEvent(new CustomEvent('image-upload-error', { detail: { status: x.status, ok: false, body: body, file: file } }));
					}
				}
			};

			x.onerror = function () { self.E('network error'); self.showPanel(self.cfg.strErrorNetwork); };
			x.ontimeout = function () { self.E('timeout'); self.showPanel(self.cfg.strErrorTimeout.replace('{s}', self.cfg.uploadTimeoutMs / 1000)); };
			x.send(fd);
		},

		handleDrag: function (e) {
			if (!this.isFileDrag(e)) return;
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			if (e.type === 'dragover') {
				var now = Date.now();
				if (now - this.lastOver > 150) { this.lastOver = now; this.L('dragover'); }
			} else {
				this.L(e.type);
			}
		},

		handleDrop: function (e) {
			if (!this.isFileDrag(e)) return;

			// Ignorar drops en cajas .dropimage (tienen su propio handler)
			if (e.target && e.target.closest && e.target.closest('.dropimage')) return;

			e.preventDefault();

			// Prevenir drag and drop desde la app misma (Ej: Un icono re-arrastrado web->web)
			if (IS_INTERNAL_DRAG) {
				this.L('Ignoring internal web image drop');
				return;
			}

			var self = this;
			var imgs = this.filesFrom(e.dataTransfer).filter(function (f) { return self.isImgFile(f); });

			if (!imgs.length) { this.L('drop: no images; ignoring'); return; }
			this.L('drop files', imgs.map(function (f) { return { name: f.name, type: f.type, size: f.size }; }));

			imgs.forEach(function (f) { self.upload(f); });
		},

		handlePaste: function (e) {
			var self = this;
			var items = (e.clipboardData || {}).items || [];
			var imgs = [];
			for (var i = 0; i < items.length; i++) {
				if (items[i].kind === 'file') {
					var f = items[i].getAsFile && items[i].getAsFile();
					if (self.isImgFile(f)) imgs.push(f);
				}
			}
			if (!imgs.length) return;
			this.L('paste images', imgs.length);
			imgs.forEach(function (f) { self.upload(f); });
		},

		init: function () {
			var target = typeof this.cfg.dropZone === 'string' ? d.querySelector(this.cfg.dropZone) : this.cfg.dropZone;
			if (!target) target = d.body;

			this.L('binding on', target);
			target.addEventListener('dragenter', this.handleDrag.bind(this), true);
			target.addEventListener('dragover', this.handleDrag.bind(this), true);
			target.addEventListener('drop', this.handleDrop.bind(this), true);
			w.addEventListener('paste', this.handlePaste.bind(this));

			this.L('ready');
		}
	};

	// Exportamos la Clase para otros sistemas
	w.UniversalImageUploader = UniversalImageUploader;

	// Iniciar la librería para las necesidades de Roleplus (Retrocompatibilidad / Comportamiento clásico)
	function autoStart() {
		w.ImageDropUploader = new UniversalImageUploader();
		w.ImageDropUploader.upload = function (f) { UniversalImageUploader.prototype.upload.call(this, f); };
		w.ImageDropUploader.config = w.ImageDropUploader.cfg;
	}

	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', autoStart); else autoStart();
})(window, document); 


/* --- Start of 4-wysiwyg.js --- */
/*console.log('ChatGPT4 5.0');

var sections = document.querySelectorAll('section');

sections.forEach(section => {
    section.addEventListener('click', () => {
        var editableElements = document.querySelectorAll('[contenteditable="true"]');
        editableElements.forEach(element => {
            if (element !== section) {
                element.removeAttribute('contenteditable');
            }
        });
        if (!section.hasAttribute('contenteditable')) {
            section.setAttribute('contenteditable', true);
            section.focus();
        }
    });
});

var editor = document.querySelector('.plantilla');
var menu = document.querySelector('.wysiwyg');

editor.addEventListener('mouseup', (e) => {
    var selection = window.getSelection().toString().trim();
    if (selection.length > 0) {
        var range = window.getSelection().getRangeAt(0);
        var rect = range.getBoundingClientRect();
        var left = rect.left + window.scrollX;
        var top = rect.top + window.scrollY;
        menu.style.left = `${left}px`;
        menu.style.top = `${top - 30}px`;
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
});

menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    console.log(selection); // Devuelve un objeto Range
    const node = document.createElement(e.target.classList[0]);
    console.log(node); // Devuelve una cadena de texto con la etiqueta y su contenido
    const nodeName = e.target.classList[0];
    console.log(nodeName); // Devuelve el nombre de la etiqueta

    const selectedText = selection.toString();
    console.log(selectedText); // Devuelve el texto seleccionado
    const regex = new RegExp(`<${nodeName}>.*<\/${nodeName}>`);
    console.log(regex); // Devuelve una expresión regular que busca la etiqueta en el texto seleccionado
    const nodeInSelection = regex.test(node);
    console.log(nodeInSelection); // Devuelve un booleano indicando si la etiqueta ya existe en el texto seleccionado

    if (nodeInSelection) {
        const div = document.createElement('div');
        div.innerHTML = selectedText;
        const nodesToRemove = div.querySelectorAll(nodeName);
        nodesToRemove.forEach(node => {
            node.outerHTML = node.innerHTML;
        });
    } else {
        node.appendChild(selection.extractContents());
        selection.insertNode(node);
    }
});*/

/*menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    console.log(selection); // return (object)Range
    const nodeName = e.target.classList[0];
    console.log(nodeName); // return (string)'b' 

    const selectedText = selection.toString();
    console.log(selectedText); // return (string)'Traducido por Lobo Blanco'
    const regex = new RegExp(`<${nodeName}>.*<\/${nodeName}>`);
    console.log(regex); // return (string)'/<b>.*<\/b>/'
    const nodeInSelection = regex.test(selectedText);
    console.log(nodeInSelection); // return (bool)false

    if (nodeInSelection) {
        const div = document.createElement('div');
        div.innerHTML = selectedText;
        const nodesToRemove = div.querySelectorAll(nodeName);
        nodesToRemove.forEach(node => {
            node.outerHTML = node.innerHTML;
        });
    } else {
        const node = document.createElement(nodeName);
        node.appendChild(selection.extractContents());
        selection.insertNode(node);
    }
});*/

/*menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    const node = document.createElement(e.target.classList[0]);
    node.appendChild(selection.extractContents());
    selection.insertNode(node);
});*/

//selection.removeAllRanges(); // eliminamos cualquier selección anterior
//selection.addRange(range); // agregamos el rango actual a la selección

/*var newRange = document.createRange();
newRange.setStartBefore(node);
newRange.setEndAfter(node);
selection.removeAllRanges();
selection.addRange(newRange);*/

/*menu.addEventListener('click', (e) => {
    var node = document.createElement(e.target.classList[0]);
    var selection = window.getSelection();
    var range = selection.getRangeAt(0);
    var textNode = range.extractContents();
    var parent = selection.focusNode.parentNode;

    if (parent.nodeName.toLowerCase() === e.target.classList[0]) {
        console.log("El nodo ha sido seleccionado");

        console.log(parent.parentNode);
        console.log(parent);

        parent.parentNode.insertBefore(textNode, parent);
        parent.remove();

    } else {
        console.log("El nodo no ha sido seleccionado");
        node.appendChild(textNode);
        range.insertNode(node);
    }
});*/


/* --- Start of 5-pinceles.js --- */
/**
 * Editor Pinceles — Inserción de etiquetas HTML + Migas de pan
 * Sin IDs ni clases: usa selectores de atributo y elemento.
 */
(function (window, document) {
    'use strict';

    /* -----------------------------------------------------------------
       SELECTORES SEMÁNTICOS (un único lugar para cambiarlos)
    ----------------------------------------------------------------- */
    var SEL_NAV_PINCELES  = 'nav[data-toolbar="pinceles"]';
    var SEL_NAV_CONTEXTO  = 'nav[data-toolbar="contexto"]';
    var SEL_PAGE_SELECT   = SEL_NAV_CONTEXTO + ' select:first-of-type';
    var SEL_POS_SELECT    = SEL_NAV_CONTEXTO + ' select:last-of-type';
    var SEL_BREADCRUMB    = SEL_NAV_CONTEXTO + ' span';
    var SEL_OUTPUT        = 'body > output';
    var SEL_ARTICLE       = 'main div[contenteditable]';
    var SEL_PINCEL_BTN    = SEL_NAV_PINCELES + ' button[data-tag]';

    /* -----------------------------------------------------------------
       CONFIGURACIÓN DE ETIQUETAS
    ----------------------------------------------------------------- */
    var TAGS = [
        { tag: 'article',    block: true,  wrap: false, children: '' },
        { tag: 'div',        block: true,  wrap: false, children: '' },
        { tag: 'header',     block: true,  wrap: false, children: '' },
        { tag: 'section',    block: true,  wrap: false, children: '' },
        { tag: 'footer',     block: true,  wrap: false, children: '' },
        { tag: 'hr',         block: true,  wrap: false, children: null, selfClose: true },
        { tag: 'h1',         block: true,  wrap: false, children: '' },
        { tag: 'h2',         block: true,  wrap: false, children: '' },
        { tag: 'h3',         block: true,  wrap: false, children: '' },
        { tag: 'h4',         block: true,  wrap: false, children: '' },
        { tag: 'h5',         block: true,  wrap: false, children: '' },
        { tag: 'h6',         block: true,  wrap: false, children: '' },
        { tag: 'p',          block: true,  wrap: false, children: '' },
        { tag: 'small',      block: false, wrap: true,  children: 'pequeño' },
        { tag: 'a',          block: false, wrap: true,  children: 'enlace', attrs: 'href="#"' },
        { tag: 'b',          block: false, wrap: true,  children: 'negrita' },
        { tag: 'strong',     block: false, wrap: true,  children: 'negrita' },
        { tag: 'i',          block: false, wrap: true,  children: 'cursiva' },
        { tag: 'em',         block: false, wrap: true,  children: 'cursiva' },
        { tag: 'img',        block: false, wrap: false, children: null, selfClose: true, attrs: 'src="" alt=""' },
        { tag: 'mark',       block: false, wrap: true,  children: 'marcado' },
        { tag: 's',          block: false, wrap: true,  children: 'tachado' },
        { tag: 'span',       block: false, wrap: true,  children: 'texto' },
        { tag: 'sub',        block: false, wrap: true,  children: 'sub' },
        { tag: 'sup',        block: false, wrap: true,  children: 'sup' },
        { tag: 'u',          block: false, wrap: true,  children: 'subrayado' },
        { tag: 'blockquote', block: true,  wrap: false, children: '' },
        { tag: 'q',          block: false, wrap: true,  children: 'cita' },
        { tag: 'ol',         block: true,  wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul',         block: true,  wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul.checkbox', block: true, wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul.radio',    block: true, wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'li',         block: true,  wrap: false, children: '' },
        { tag: 'table',      block: true,  wrap: false, children: '\n\t\t<thead><tr><th></th></tr></thead>\n\t\t<tbody><tr><td></td></tr></tbody>\n\t' },
        { tag: 'table.w100', block: true,  wrap: false, attrs: 'style="width: 100%;"', children: '\n\t\t<thead><tr><th></th></tr></thead>\n\t\t<tbody><tr><td></td></tr></tbody>\n\t' },
        { tag: 'caption',    block: true,  wrap: false, children: '' },
        { tag: 'thead',      block: true,  wrap: false, children: '\n\t\t<tr><th></th></tr>\n\t' },
        { tag: 'tbody',      block: true,  wrap: false, children: '\n\t\t<tr><td></td></tr>\n\t' },
        { tag: 'tfoot',      block: true,  wrap: false, children: '\n\t\t<tr><td></td></tr>\n\t' },
        { tag: 'tr',         block: true,  wrap: false, children: '\n\t\t<td></td>\n\t' },
        { tag: 'th',         block: true,  wrap: false, children: '' },
        { tag: 'td',         block: true,  wrap: false, children: '' }
    ];

    /* -----------------------------------------------------------------
       ESTADO
    ----------------------------------------------------------------- */
    var activeArticle  = null;
    var savedRange     = null;
    var debounceTimers = {};
    var countdownTimers = {};
    var currentCrumbs  = [];

    /* -----------------------------------------------------------------
       BREADCRUMB
    ----------------------------------------------------------------- */
    function getAncestors(node, container) {
        var crumbs = [];
        var cur = node;
        while (cur && cur !== container) {
            if (cur.nodeType === 1) {
                crumbs.unshift(cur);
            }
            cur = cur.parentNode;
        }
        return crumbs;
    }


    function updateBreadcrumb() {
        var bc = document.querySelector(SEL_BREADCRUMB);
        if (!bc) return;

        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0 || !activeArticle) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var node = sel.anchorNode;
        if (node && node.nodeType === 3) node = node.parentNode;

        // Asegurar que el nodo seleccionado pertenece al artículo activo y está dentro de su <article>
        if (!activeArticle.contains(node)) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var targetArticle = activeArticle.querySelector('article');
        if (!targetArticle || !targetArticle.contains(node)) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var elements = getAncestors(node, activeArticle);

        // Comparar con currentCrumbs para ver si ha cambiado la ruta
        var changed = elements.length !== currentCrumbs.length;
        if (!changed) {
            for (var j = 0; j < elements.length; j++) {
                if (elements[j] !== currentCrumbs[j]) {
                    changed = true;
                    break;
                }
            }
        }

        if (!changed) return;

        currentCrumbs = elements;

        // Generar botones para cada etiqueta de migas de pan (ocultar article)
        var html = '';
        var startIndex = (elements.length > 0 && elements[0].tagName.toLowerCase() === 'article') ? 1 : 0;
        
        for (var i = startIndex; i < elements.length; i++) {
            var el = elements[i];
            if (i > startIndex) html += '<i>›</i>';
            
            var name = el.tagName.toLowerCase();
            if (el.id) {
                name += '#' + el.id;
            }
            
            html += '<button type="button" data-index="' + i + '">' + name + '</button>';
        }
        bc.innerHTML = html;
    }

    /* -----------------------------------------------------------------
       ELEMENTOS VACÍOS (badges y placeholders dinámicos)
       ----------------------------------------------------------------- */
    function syncHelpers(art) {
        if (!art) return;
        
        var candidates = art.querySelectorAll('article, p, h1, h2, h3, h4, h5, h6, blockquote, div, header, section, footer');
        candidates.forEach(function (el) {
            if (el === art) return;
            
            var hasText = false;
            var hasRealChildren = false;
            
            for (var i = 0; i < el.childNodes.length; i++) {
                var node = el.childNodes[i];
                if (node.nodeType === 1) {
                    if (node.hasAttribute('data-editor-helper') || node.classList.contains('button-floating')) {
                        continue;
                    }
                    if (node.tagName.toLowerCase() === 'br') {
                        continue;
                    }
                    hasRealChildren = true;
                    break;
                } else if (node.nodeType === 3) {
                    var text = node.textContent.replace(/[\u200B\s]/g, '');
                    if (text.length > 0) {
                        hasText = true;
                        break;
                    }
                }
            }
            
            var isEmpty = !hasText && !hasRealChildren;
            var badge = el.querySelector(':scope > [data-editor-helper="badge"]');
            
            if (isEmpty) {
                el.setAttribute('data-empty-helper', 'true');
                if (!badge) {
                    var tagName = el.tagName.toLowerCase();
                    badge = document.createElement('span');
                    badge.contentEditable = "false";
                    badge.setAttribute('data-editor-helper', 'badge');
                    badge.className = 'editor-badge';
                    badge.innerHTML = '<span class="tag-name">' + tagName + '</span><span class="del-btn">×</span>';
                    el.insertBefore(badge, el.firstChild);
                }
                
                var lastNode = el.lastChild;
                if (!lastNode || lastNode.nodeType !== 3 || lastNode.textContent.indexOf('\u200B') === -1) {
                    el.appendChild(document.createTextNode('\u200B'));
                }
            } else {
                el.removeAttribute('data-empty-helper');
                if (badge) {
                    badge.remove();
                }
            }
        });
    }

    /* -----------------------------------------------------------------
       GUARDAR RANGO antes de perder foco
    ----------------------------------------------------------------- */
    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            var range = sel.getRangeAt(0);
            var cont  = range.commonAncestorContainer;
            var art   = cont.nodeType === 1
                ? cont.closest('div[contenteditable]')
                : (cont.parentNode ? cont.parentNode.closest('div[contenteditable]') : null);
            if (art) {
                savedRange    = range.cloneRange();
                activeArticle = art;
                updateBreadcrumb();
            }
        }

        // 1) Cuando hay texto seleccionado, el control superior se pone en "seleccion"
        var posSelect = document.querySelector(SEL_POS_SELECT);
        if (posSelect) {
            if (sel && sel.toString().trim().length > 0) {
                if (posSelect.value !== 'seleccion') {
                    posSelect.setAttribute('data-prev-value', posSelect.value);
                    posSelect.value = 'seleccion';
                }
            } else {
                if (posSelect.value === 'seleccion') {
                    var prev = posSelect.getAttribute('data-prev-value') || 'dentro';
                    posSelect.value = prev;
                }
            }
        }
    });

    // 3) Excepción: el botón ART PAGE solo añade Antes o Después. Al pasar el ratón (hover), forzar la posición.
    document.addEventListener('mouseover', function (e) {
        var btn = e.target.closest(SEL_PINCEL_BTN);
        if (btn) {
            var tag = btn.getAttribute('data-tag');
            if (tag === 'article') {
                var posSelect = document.querySelector(SEL_POS_SELECT);
                if (posSelect) {
                    var val = posSelect.value;
                    if (val !== 'antes' && val !== 'despues') {
                        posSelect.value = 'antes';
                    }
                }
            }
        }
    });

    /* -----------------------------------------------------------------
       HELPERS DE INSERCIÓN
    ----------------------------------------------------------------- */
    function restoreRange() {
        if (!savedRange || !activeArticle) return false;
        try {
            activeArticle.focus();
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
            return true;
        } catch (e) {
            return false;
        }
    }

    function placeCursorInside(el) {
        if (!el) return;

        // Foco explícito en el contenedor editable primero
        var art = el.closest('div[contenteditable]');
        if (art) {
            art.focus();
        }

        var target = el;
        // Si está vacío, le inyectamos un nodo de texto vacío con zwsp para estabilizar el cursor en contenteditable
        if (target.nodeType === 1 && !target.firstChild) {
            target.appendChild(document.createTextNode('\u200B'));
        }

        // Si es un helper vacío, el foco debe ir al texto invisible del final, no al badge ineditable
        if (target.nodeType === 1 && target.getAttribute('data-empty-helper') === 'true') {
            target = target.lastChild;
        } else {
            // Navegar hasta el nodo de texto o elemento hijo más profundo
            while (target.firstChild) {
                target = target.firstChild;
            }
        }

        var sel   = window.getSelection();
        var range = document.createRange();
        
        try {
            if (target.nodeType === 3) {
                range.setStart(target, 0);
                range.setEnd(target, 0);
            } else {
                range.selectNodeContents(target);
                range.collapse(true);
            }
            sel.removeAllRanges();
            sel.addRange(range);
            
            savedRange = range.cloneRange();
        } catch (e) { /* fallback silencioso */ }
    }

    function buildHtml(def, selectedText, position) {
        var parts = def.tag.split('.');
        var tagName = parts[0];
        var className = parts[1] || '';
        
        var attrs = (className ? ' class="' + className + '"' : '') + (def.attrs ? ' ' + def.attrs : '');

        if (def.selfClose) return '<' + tagName + attrs + '>\n';
        var inner = (def.wrap || position === 'seleccion') && selectedText ? selectedText
            : (def.children !== null && def.children !== undefined ? def.children : '');
        var open  = '<' + tagName + attrs + '>';
        var close = '</' + tagName + '>';
        return def.block ? '\n\t' + open + inner + close + '\n' : open + inner + close;
    }

    function insertTag(def) {
        if (!activeArticle) return;
        restoreRange();

        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;

        var targetArticle = activeArticle.querySelector('article');
        if (!targetArticle) return;

        var range        = sel.getRangeAt(0);
        var selectedText = sel.toString();
        var posSelect    = document.querySelector(SEL_POS_SELECT);
        var position     = posSelect ? posSelect.value : 'dentro';
        var html         = buildHtml(def, selectedText, position);
        var newEl        = null;

        if (position === 'seleccion') {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            newEl = tmp.firstElementChild || tmp.firstChild;
            if (!newEl) return;
            range.deleteContents();
            range.insertNode(newEl);
        } else if (position === 'dentro') {
            if (def.selfClose) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                var frag = document.createDocumentFragment();
                while (tmp.firstChild) frag.appendChild(tmp.firstChild);
                range.deleteContents();
                range.insertNode(frag);
            } else {
                var tmp2 = document.createElement('div');
                tmp2.innerHTML = html;
                newEl = tmp2.firstElementChild;
                if (!newEl) return;

                if (def.block) {
                    var container = range.startContainer;
                    if (container.nodeType === 3) container = container.parentNode;
                    
                    // Buscar el contenedor de bloques estructurado más cercano
                    var blockContainer = container;
                    while (blockContainer && blockContainer !== targetArticle) {
                        var tag = blockContainer.tagName.toLowerCase();
                        if (['article', 'section', 'div', 'header', 'footer', 'blockquote', 'li', 'td', 'th'].indexOf(tag) !== -1) {
                            break;
                        }
                        blockContainer = blockContainer.parentNode;
                    }
                    if (!blockContainer) blockContainer = targetArticle;

                    if (blockContainer === container) {
                        blockContainer.appendChild(newEl);
                    } else {
                        var child = container;
                        while (child && child.parentNode !== blockContainer) {
                            child = child.parentNode;
                        }
                        if (child && child.parentNode === blockContainer) {
                            blockContainer.insertBefore(newEl, child.nextSibling);
                        } else {
                            blockContainer.appendChild(newEl);
                        }
                    }
                } else {
                    if (def.wrap && selectedText) range.deleteContents();
                    range.insertNode(newEl);
                }
            }
        } else {
            var anchor = range.startContainer;
            if (anchor.nodeType === 3) anchor = anchor.parentNode;
            while (anchor && anchor.parentNode && anchor.parentNode !== targetArticle) {
                var parentTag = anchor.parentNode.tagName.toLowerCase();
                if (['header', 'section', 'div', 'blockquote', 'td', 'th', 'li'].indexOf(parentTag) !== -1) {
                    break;
                }
                anchor = anchor.parentNode;
            }
            
            var parentNode = anchor ? anchor.parentNode : targetArticle;
            if (!parentNode) parentNode = targetArticle;

            var tmp3 = document.createElement('div');
            tmp3.innerHTML = html;
            newEl = tmp3.firstElementChild || tmp3.firstChild;
            if (!newEl) return;

            parentNode.insertBefore(newEl,
                position === 'antes' ? anchor : (anchor ? anchor.nextSibling : null));
        }

        if (newEl && newEl.nodeType === 1) placeCursorInside(newEl);
        syncHelpers(activeArticle);
        scheduleSave(activeArticle);
        updateBreadcrumb();
    }

    function recalcularPaginas(newIdu, newNombre, refIdu, position) {
        var pages = document.querySelectorAll(SEL_ARTICLE);
        pages.forEach(function (page, idx) {
            page.setAttribute('data-page', idx + 1);
        });
        
        var pageSelect = document.querySelector(SEL_PAGE_SELECT);
        if (pageSelect) {
            var options = Array.from(pageSelect.options).map(function (opt) {
                return { value: opt.value, text: opt.text };
            });
            
            var refIdx = options.findIndex(function (opt) { return opt.value === refIdu; });
            var newOpt = { value: newIdu, text: newNombre };
            
            if (refIdx !== -1) {
                if (position === 'antes') {
                    options.splice(refIdx, 0, newOpt);
                } else {
                    options.splice(refIdx + 1, 0, newOpt);
                }
            } else {
                options.push(newOpt);
            }
            
            var html = '';
            options.forEach(function (opt) {
                html += '<option value="' + opt.value + '">' + opt.text + '</option>';
            });
            pageSelect.innerHTML = html;
            pageSelect.value = newIdu;
        }
        
        var newPage = document.getElementById(newIdu);
        if (newPage) {
            newPage.focus();
            activeArticle = newPage;
            window.location.hash = newIdu;
        }
    }

    /* -----------------------------------------------------------------
       CLICK EN BOTÓN PINCEL / MIGAS DE PAN
    ----------------------------------------------------------------- */
    document.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('[data-toggle="guias"]');
        if (toggleBtn) {
            e.preventDefault();
            var mainEl = document.querySelector('main');
            if (mainEl) {
                var showAll = mainEl.classList.toggle('show-all-outlines');
                toggleBtn.classList.toggle('active', showAll);
                try {
                    localStorage.setItem('editor-show-all-outlines', showAll ? '1' : '0');
                } catch (err) {}
            }
            return;
        }

        var delBtn = e.target.closest('.editor-badge .del-btn');
        if (delBtn) {
            e.preventDefault();
            e.stopPropagation();
            var badge = delBtn.closest('.editor-badge');
            if (badge) {
                var parent = badge.parentNode;
                if (parent) {
                    var art = parent.closest('div[contenteditable]');
                    
                    // CASO ESPECIAL: Si es el tag principal 'article', eliminar toda la página (ajax)
                    if (parent.tagName.toLowerCase() === 'article') {
                        if (art) {
                            var idu = art.getAttribute('data-idu');
                            if (idu) {
                                if (confirm('¿De verdad quieres eliminar esta página?')) {
                                    var fd = new FormData();
                                    fd.append('idu', idu);
                                    fd.append('action', 'regla_eliminar');
                                    
                                    setSaveStatus('saving', '● eliminando página…');
                                    
                                    fetch(window.location.pathname, {
                                        method: 'POST',
                                        body: fd,
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                    })
                                    .then(function (r) {
                                        if (!r.ok) throw new Error('HTTP ' + r.status);
                                        return r.json();
                                    })
                                    .then(function (res) {
                                        if (res.success) {
                                            setSaveStatus('saved', '✓ página eliminada');
                                            
                                            // Eliminar del select de páginas
                                            var pageSelect = document.querySelector(SEL_PAGE_SELECT);
                                            if (pageSelect) {
                                                var opt = pageSelect.querySelector('option[value="' + idu + '"]');
                                                if (opt) opt.remove();
                                            }
                                            
                                            art.remove();
                                            
                                            // Seleccionar otra página activa
                                            var remainingPages = document.querySelectorAll(SEL_ARTICLE);
                                            if (remainingPages.length > 0) {
                                                activeArticle = remainingPages[0];
                                                if (pageSelect) pageSelect.value = activeArticle.getAttribute('data-idu');
                                                window.location.hash = activeArticle.getAttribute('data-idu');
                                                placeCursorInside(activeArticle);
                                            } else {
                                                activeArticle = null;
                                                if (pageSelect) pageSelect.value = '';
                                                window.location.hash = '';
                                            }
                                            
                                            // Recalcular los números de página de las que quedan
                                            var pages = document.querySelectorAll(SEL_ARTICLE);
                                            pages.forEach(function (page, idx) {
                                                page.setAttribute('data-page', idx + 1);
                                            });
                                            updateBreadcrumb();
                                        } else {
                                            setSaveStatus('error', '✗ error: ' + (res.error || 'al eliminar página'));
                                        }
                                    })
                                    .catch(function (error) {
                                        setSaveStatus('error', '✗ ' + (error && error.message ? error.message : 'sin conexión'));
                                    });
                                }
                            }
                        }
                        return;
                    }
                    
                    parent.remove();
                    if (art) {
                        syncHelpers(art);
                        scheduleSave(art);
                        updateBreadcrumb();
                    }
                }
            }
            return;
        }

        var trigger = e.target.closest('.grupo-trigger');
        if (trigger) {
            e.preventDefault();
            var grupo = trigger.closest('.pinceles-grupo');
            if (grupo) {
                var isActive = grupo.classList.contains('active');
                
                // Cerrar todos los grupos
                var todosLosGrupos = document.querySelectorAll('.pinceles-grupo');
                todosLosGrupos.forEach(function (g) {
                    g.classList.remove('active');
                });
                
                // Si no estaba activo, lo abrimos
                if (!isActive) {
                    grupo.classList.add('active');
                }
            }
            return;
        }

        var btn = e.target.closest(SEL_PINCEL_BTN);
        if (btn) {
            var tag = btn.getAttribute('data-tag');
            if (!tag) return;

            e.preventDefault();

            // CASO ESPECIAL: Crear una página nueva en blanco (PAG / article)
            if (tag === 'article') {
                var refIdu = '';
                var position = 'despues';
                
                // Intentar recuperar el artículo activo en base al select superior si es null
                if (!activeArticle) {
                    var pageSelect = document.querySelector(SEL_PAGE_SELECT);
                    if (pageSelect && pageSelect.value) {
                        var art = document.querySelector(SEL_ARTICLE + '[data-idu="' + pageSelect.value + '"]');
                        if (art) {
                            activeArticle = art;
                        }
                    }
                }
                
                // Si sigue siendo null, intentar usar el último artículo en el DOM (punto de partida)
                if (!activeArticle) {
                    var pages = document.querySelectorAll(SEL_ARTICLE);
                    if (pages.length > 0) {
                        activeArticle = pages[pages.length - 1];
                    }
                }
                
                if (activeArticle) {
                    refIdu = activeArticle.getAttribute('data-idu') || '';
                    var posSelect = document.querySelector(SEL_POS_SELECT);
                    position  = posSelect ? posSelect.value : 'despues';
                    
                    // Si está en 'dentro', forzar a 'despues'
                    if (position === 'dentro') {
                        if (posSelect) posSelect.value = 'despues';
                        position = 'despues';
                    }
                }
                
                var mainEl = document.querySelector('main[data-manuales-idu]');
                var manualesIdu = mainEl ? mainEl.getAttribute('data-manuales-idu') : '';
                
                if (!manualesIdu) return;
                
                var fd = new FormData();
                fd.append('manuales_idu', manualesIdu);
                fd.append('referencia_idu', refIdu);
                fd.append('posicion', position);
                fd.append('action', 'regla_crear_ajax');
                
                setSaveStatus('saving', '● creando página…');
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (res) {
                    if (res.success) {
                        setSaveStatus('saved', '✓ página creada');
                        
                        var newDiv = document.createElement('div');
                        newDiv.id = res.idu;
                        newDiv.contentEditable = "true";
                        newDiv.spellCheck = false;
                        newDiv.setAttribute('data-idu', res.idu);
                        newDiv.innerHTML = '<article id="pag-' + res.idu + '"></article>' +
                            '<a class="button-floating scroll-down-hide" contenteditable="false" data-ajax=".ajax.show" ' +
                            'href="/ev/manuales/formulario_regla/' + manualesIdu + '/' + res.idu + '" ' +
                            'title="Editar en formulario"><img alt="Edit" loading="lazy" src="/img/icons/edit.svg" width="30" height="30"></a>';
                        
                        if (activeArticle) {
                            if (position === 'antes') {
                                activeArticle.parentNode.insertBefore(newDiv, activeArticle);
                            } else {
                                activeArticle.parentNode.insertBefore(newDiv, activeArticle.nextSibling);
                            }
                        } else {
                            var mainContainer = document.querySelector('main[data-manuales-idu]');
                            if (mainContainer) {
                                mainContainer.appendChild(newDiv);
                            }
                        }
                        
                        activeArticle = newDiv;
                        placeCursorInside(newDiv);
                        syncHelpers(newDiv);
                        recalcularPaginas(res.idu, res.nombre, refIdu, position);
                        updateBreadcrumb();
                    } else {
                        setSaveStatus('error', '✗ error: ' + (res.error || 'al crear página'));
                    }
                })
                .catch(function (error) {
                    setSaveStatus('error', '✗ ' + (error && error.message ? error.message : 'sin conexión'));
                });
                return;
            }

            var def = null;
            for (var i = 0; i < TAGS.length; i++) {
                if (TAGS[i].tag === tag) { def = TAGS[i]; break; }
            }
            if (!def) return;

            insertTag(def);
            return;
        }

        var crumbBtn = e.target.closest(SEL_BREADCRUMB + ' button[data-index]');
        if (crumbBtn) {
            var idx = parseInt(crumbBtn.getAttribute('data-index'), 10);
            if (isNaN(idx) || idx < 0 || idx >= currentCrumbs.length) return;

            var targetEl = currentCrumbs[idx];
            if (!targetEl) return;

            e.preventDefault();

            if (activeArticle) {
                var art = targetEl.closest('div[contenteditable]');
                if (art) art.focus();

                var txt = document.createTextNode('\u200B');
                targetEl.appendChild(txt);

                var sel = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(txt);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
                savedRange = range.cloneRange();
            }
            updateBreadcrumb();
        }
    });

    /* -----------------------------------------------------------------
       SELECT DE PÁGINAS → scroll a la página
    ----------------------------------------------------------------- */
    document.addEventListener('change', function (e) {
        if (!e.target.matches(SEL_PAGE_SELECT)) return;
        var idu = e.target.value;
        if (idu) {
            // Guardar inmediatamente la página anterior si tenía cambios pendientes
            if (activeArticle) {
                var activeIdu = activeArticle.getAttribute('data-idu');
                if (activeIdu && debounceTimers[activeIdu]) {
                    clearTimeout(debounceTimers[activeIdu]);
                    delete debounceTimers[activeIdu];
                    saveArticle(activeArticle);
                }
            }

            window.location.hash = idu;
            var art = document.querySelector(SEL_ARTICLE + '[data-idu="' + idu + '"]');
            if (art) {
                activeArticle = art;
                placeCursorInside(art);
                updateBreadcrumb();
            }
        }
    });

    /* -----------------------------------------------------------------
       RASTREAR ARTÍCULO ACTIVO
    ----------------------------------------------------------------- */
    document.addEventListener('focusin', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            activeArticle = art;
            var pageSelect = document.querySelector(SEL_PAGE_SELECT);
            if (pageSelect && art.getAttribute('data-idu')) {
                pageSelect.value = art.getAttribute('data-idu');
            }
        }
    });

    document.addEventListener('mousedown', function (e) {
        if (!activeArticle) return;
        var target = e.target;
        // Si el elemento ha sido removido o desconectado del DOM (ej: al redibujar el breadcrumb
        // o el acordeón al hacer clic), asumimos interacción con el editor y no guardamos de inmediato.
        if (!document.documentElement.contains(target)) {
            return;
        }

        var dentroDelEditor = target.closest('div[contenteditable]') ||
                              target.closest(SEL_NAV_PINCELES) ||
                              target.closest(SEL_NAV_CONTEXTO) ||
                              target.closest('aside.editor') ||
                              target.closest('.button-floating');
                              
        // Forzar el cursor dentro de elementos vacíos al hacer clic con el ratón
        var emptyHelper = target.closest('[data-empty-helper="true"]');
        if (emptyHelper && emptyHelper === target) {
            e.preventDefault(); // Evitar el comportamiento erróneo nativo
            placeCursorInside(emptyHelper);
            updateBreadcrumb();
            return;
        }

        // Si se hace clic directamente en el <article> (en su padding/espacio vacío), 
        // forzar el cursor al final de este para que no salte al hijo más cercano.
        if (target.tagName && target.tagName.toLowerCase() === 'article') {
            e.preventDefault();
            var sel = window.getSelection();
            if (sel) {
                var range = document.createRange();
                range.selectNodeContents(target);
                range.collapse(false); // Mover el cursor al final
                sel.removeAllRanges();
                sel.addRange(range);
                updateBreadcrumb();
                
                // Asegurarnos de que el contenteditable mantenga el foco para la caja-sombra
                var ce = target.closest('div[contenteditable]');
                if (ce) ce.focus();
            }
            return;
        }
                              
        if (!dentroDelEditor) {
            var idu = activeArticle.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                clearTimeout(debounceTimers[idu]);
                delete debounceTimers[idu];
                saveArticle(activeArticle);
            }
        }
    });

    window.addEventListener('beforeunload', function () {
        if (activeArticle) {
            var idu = activeArticle.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                saveArticle(activeArticle);
            }
        }
    });

    /* -----------------------------------------------------------------
       GUARDADO AUTOMÁTICO POR DEBOUNCE
    ----------------------------------------------------------------- */
    function setSaveStatus(status, msg) {
        var el = document.querySelector(SEL_OUTPUT);
        if (!el) return;
        
        // Silenciar por completo los avisos visuales (excepto errores)
        if (status !== 'error') {
            el.removeAttribute('data-status');
            el.textContent = '';
            return;
        }

        el.setAttribute('data-status', status);
        el.textContent = msg;

        var plainMsg = msg.replace(/^[✗●✓]\s*/, '');
        var lowerMsg = plainMsg.toLowerCase();

        if (lowerMsg === 'sin conexión') {
            plainMsg = 'No se ha podido conectar con el servidor. Comprueba tu conexión a internet.';
        } else if (lowerMsg.indexOf('403') !== -1) {
            plainMsg = 'No tienes permisos de edición en este manual.';
        } else if (lowerMsg.indexOf('500') !== -1 || lowerMsg.indexOf('http') !== -1 || lowerMsg.indexOf('error') !== -1) {
            plainMsg = 'El editor ha tenido un problema grave. Por favor, avisa al administrador detallando en qué momento se ha producido este error.';
        } else if (lowerMsg.indexOf('crear página') !== -1) {
            plainMsg = 'No se ha podido crear la nueva página en el servidor.';
        } else if (lowerMsg.indexOf('guardar') !== -1 || lowerMsg.indexOf('guardado') !== -1) {
            plainMsg = 'No se han podido guardar tus cambios en el servidor.';
        }

        if (typeof window.editorToast === 'function') {
            window.editorToast(plainMsg, 'error');
        }
    }

    function scheduleSave(articleEl) {
        var idu = articleEl.getAttribute('data-idu');
        if (!idu) return;
        clearTimeout(debounceTimers[idu]);
        clearInterval(countdownTimers[idu]);
        
        setSaveStatus('saving', '● guardando…');

        var btn = document.querySelector('button[data-action="save-page"]');
        var countdownEl = btn ? btn.querySelector('.save-countdown') : null;
        var iconEl = btn ? btn.querySelector('.save-icon') : null;
        
        if (btn) btn.classList.remove('active');
        if (countdownEl) {
            countdownEl.style.display = 'inline-block';
            countdownEl.textContent = '8';
        }
        if (iconEl) iconEl.style.display = 'none';

        var seconds = 8;
        countdownTimers[idu] = setInterval(function() {
            seconds--;
            if (seconds > 0) {
                if (countdownEl) countdownEl.textContent = seconds;
            } else {
                clearInterval(countdownTimers[idu]);
                if (countdownEl) countdownEl.style.display = 'none';
                if (iconEl) iconEl.style.display = 'inline-block';
                if (btn) btn.classList.add('active');
            }
        }, 1000);

        debounceTimers[idu] = setTimeout(function () {
            saveArticle(articleEl);
        }, 8000);
    }

    function saveArticle(articleEl) {
        var idu = articleEl.getAttribute('data-idu');
        if (!idu) return Promise.resolve();

        if (debounceTimers[idu]) {
            clearTimeout(debounceTimers[idu]);
            delete debounceTimers[idu];
        }
        if (countdownTimers[idu]) {
            clearInterval(countdownTimers[idu]);
            delete countdownTimers[idu];
        }

        var btn = document.querySelector('button[data-action="save-page"]');
        if (btn) {
            btn.classList.add('active');
            var countdownEl = btn.querySelector('.save-countdown');
            var iconEl = btn.querySelector('.save-icon');
            if (countdownEl) countdownEl.style.display = 'none';
            if (iconEl) iconEl.style.display = 'inline-block';
        }

        var mainEl      = document.querySelector('main[data-manuales-idu]');
        var manualesIdu = mainEl ? mainEl.getAttribute('data-manuales-idu') : '';

        var clone = articleEl.cloneNode(true);
        
        // 1. Eliminar el botón de edición
        var editBtn = clone.querySelector('a.button-floating.scroll-down-hide');
        if (editBtn) editBtn.remove();
        
        // 2. Eliminar todos los badges y helpers del editor antes de guardar
        clone.querySelectorAll('[data-editor-helper]').forEach(function (helper) {
            helper.remove();
        });
        
        // 2b. Eliminar el atributo data-empty-helper
        clone.querySelectorAll('[data-empty-helper]').forEach(function (el) {
            el.removeAttribute('data-empty-helper');
        });
        
        var articleNode = clone.querySelector('article');
        var htmlToSend  = articleNode ? articleNode.outerHTML : clone.innerHTML;
        // Clean zero-width space characters (\u200B) used to stabilize contenteditable selection
        htmlToSend = htmlToSend.replace(/\u200B/g, '');


        var fd = new FormData();
        fd.append('idu',         idu);
        fd.append('descripcion', htmlToSend);
        fd.append('action',      'regla_actualizar_ajax');
        if (manualesIdu) fd.append('manuales_idu', manualesIdu);

        return fetch(window.location.pathname, {
            method: 'POST',
            body:   fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            keepalive: true
        }).then(function (r) {
            if (r.ok || r.status === 302) {
                setSaveStatus('saved', '✓ guardado');
            } else {
                setSaveStatus('error', '✗ error ' + r.status);
            }
        }).catch(function () {
            setSaveStatus('error', '✗ sin conexión');
        });
    }

    document.addEventListener('paste', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (!art) return;
        
        // No interferir con la subida de archivos/imágenes
        if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
            return;
        }
        
        e.preventDefault();
        var text = (e.originalEvent || e).clipboardData.getData('text/plain');
        
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            var range = sel.getRangeAt(0);
            range.deleteContents();
            
            var textNode = document.createTextNode(text);
            range.insertNode(textNode);
            
            range.setStartAfter(textNode);
            range.setEndAfter(textNode);
            sel.removeAllRanges();
            sel.addRange(range);
            
            savedRange = range.cloneRange();
            
            syncHelpers(art);
            scheduleSave(art);
            updateBreadcrumb();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            var art = e.target.closest('div[contenteditable]');
            if (art) {
                e.preventDefault();
                document.execCommand('insertLineBreak');
                updateBreadcrumb();
            }
        }
    });

    document.addEventListener('input', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            syncHelpers(art);
            scheduleSave(art);
        }
    });

    document.addEventListener('keyup', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) syncHelpers(art);
    });

    document.addEventListener('focusout', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            var idu = art.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                saveArticle(art);
            }
        }
    });

    /* -----------------------------------------------------------------
       INIT
    ----------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        // Desactivar inyección de CSS nativo del navegador (evitar spans con style=...)
        try {
            document.execCommand('styleWithCSS', false, false);
            document.execCommand('insertBrOnReturn', false, false);
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch (e) {}

        var arts = document.querySelectorAll(SEL_ARTICLE);
        arts.forEach(function (art) {
            syncHelpers(art);
        });
        if (arts.length > 0) activeArticle = arts[0];

        // Cargar preferencia de guías visuales globales
        try {
            var toggleBtn = document.querySelector('[data-toggle="guias"]');
            if (localStorage.getItem('editor-show-all-outlines') === '1') {
                var mainEl = document.querySelector('main');
                if (mainEl) mainEl.classList.add('show-all-outlines');
                if (toggleBtn) toggleBtn.classList.add('active');
            } else {
                if (toggleBtn) toggleBtn.classList.remove('active');
            }
        } catch (e) {}

        // Abrir el primer grupo de pinceles por defecto
        var primerGrupo = document.querySelector('.pinceles-grupo');
        if (primerGrupo) {
            primerGrupo.classList.add('active');
        }
    });

    // Interceptar el mousedown en el botón de edición para forzar guardado antes del focusout
    document.addEventListener('mousedown', function (e) {
        var editFormBtn = e.target.closest('.button-floating[href*="formulario"]');
        if (editFormBtn && activeArticle) {
            var idu = activeArticle.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                saveArticle(activeArticle);
            }
        }
    });

    document.addEventListener('click', function(e) {
        var saveBtn = e.target.closest('button[data-action="save-page"]');
        if (saveBtn && activeArticle) {
            saveArticle(activeArticle);
        }
    });

})(window, document);


/* --- Start of 9-editor.js --- */
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var pagesCount = document.querySelectorAll('main article').length;
        var span = document.querySelector('aside .pages h4 span');
        if (span) span.textContent = pagesCount;

        if (typeof window.initPSColorPicker === 'function') {
            window.initPSColorPicker();
        }

        // Inicializar drag and drop para ordenar reglas
        var elList = document.getElementById('menu-reglas-sortable');
        if (elList && typeof Sortable !== 'undefined') {
            Sortable.create(elList, {
                animation: 150,
                onEnd: function (evt) {
                    var order = [];
                    elList.querySelectorAll('li').forEach(function (li, idx) {
                        var idu = li.getAttribute('data-idu');
                        if (idu) {
                            order.push(idu);
                            // Actualizar visualmente el peso en el sup
                            var sup = li.querySelector('.regla-peso');
                            if (sup) {
                                sup.textContent = idx;
                            }
                        }
                    });

                    // Enviar orden al servidor por AJAX
                    var fd = new FormData();
                    order.forEach(function (idu, idx) {
                        fd.append('orden[' + idx + ']', idu);
                    });

                    fetch('/ev/manuales/ordenar_reglas/', {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data.success) {
                                console.error('Error al ordenar reglas:', data.error);
                            }
                        })
                        .catch(function (err) {
                            console.error('Error de red al ordenar reglas:', err);
                        });

                    // Reordenar dinámicamente los divs de la plantilla principal
                    var main = document.querySelector('main.plantilla');
                    if (main) {
                        order.forEach(function (idu) {
                            var link = main.querySelector('a[href$="/' + idu + '"]');
                            if (link) {
                                var div = link.parentNode;
                                if (div && div.parentNode === main) {
                                    main.appendChild(div);
                                }
                            }
                        });
                    }
                }
            });
        }
    });

    document.body.addEventListener('input', function (e) {
        if (e.target.matches('[name="pages_filter"]')) {
            var val = e.target.value;
            var divs = document.querySelectorAll('main > div');

            // Asegurar que tengan el índice original antes de filtrar
            divs.forEach(function (div, i) {
                if (!div.getAttribute('data-original-index')) {
                    div.setAttribute('data-original-index', i);
                }
            });

            var set = null;
            var cleanVal = val.trim();
            if (cleanVal) {
                set = new Set();
                var parts = cleanVal.split(',');
                parts.forEach(function (part) {
                    part = part.trim();
                    if (part.indexOf('-') !== -1) {
                        var range = part.split('-');
                        var start = parseInt(range[0], 10);
                        var end = parseInt(range[1], 10);
                        if (!isNaN(start) && !isNaN(end)) {
                            var min = Math.min(start, end);
                            var max = Math.max(start, end);
                            for (var p = min; p <= max; p++) {
                                set.add(p - 1);
                            }
                        }
                    } else {
                        var p = parseInt(part, 10);
                        if (!isNaN(p)) {
                            set.add(p - 1);
                        }
                    }
                });
            }

            divs.forEach(function (div) {
                var originalIndex = parseInt(div.getAttribute('data-original-index'), 10);
                if (set === null || set.has(originalIndex)) {
                    div.style.display = '';
                } else {
                    div.style.display = 'none';
                }
            });

            var span = document.querySelector('aside .pages h4 span');
            if (span) {
                var visibleCount = 0;
                document.querySelectorAll('main article').forEach(function (art) {
                    if (art.offsetParent !== null) visibleCount++;
                });
                span.textContent = visibleCount;
            }
        }
    });

    document.body.addEventListener('click', function (e) {
        var t = e.target;

        var printBtn = t.closest('.print');
        if (printBtn) { window.print(); return; }

        var bookletBtn = t.closest('.booklet-toggle');
        if (bookletBtn) {
            var isBooklet = document.body.classList.toggle('booklet-mode');
            var styleEl = document.getElementById('page-style');
            var printFmt = bookletBtn.getAttribute('data-print') || 'A5';
            var bookletFmt = bookletBtn.getAttribute('data-booklet') || 'A4 landscape';
            if (styleEl) { styleEl.innerHTML = isBooklet ? '@page { size: ' + bookletFmt + '; margin: 0; }' : '@page { size: ' + printFmt + '; margin: 0; }'; }

            var container = document.querySelector('.plantilla');
            if (!container) return;

            if (isBooklet) {
                var pages = Array.from(container.children);
                pages.forEach(function (page, i) {
                    if (!page.getAttribute('data-original-index')) {
                        page.setAttribute('data-original-index', i);
                    }
                    // Forzar el número de página original mediante counter-reset
                    page.style.setProperty('counter-reset', 'page ' + i);
                });

                var N = pages.length;
                var order = [];
                for (var i = 0; i < N / 2; i++) {
                    if (i % 2 === 0) {
                        order.push(N - 1 - i);
                        order.push(i);
                    } else {
                        order.push(i);
                        order.push(N - 1 - i);
                    }
                }
                order.forEach(function (index) {
                    if (pages[index]) {
                        container.appendChild(pages[index]);
                    }
                });
                bookletBtn.classList.add('active');
            } else {
                var allPages = Array.from(container.children);
                allPages.sort(function (a, b) {
                    return parseInt(a.getAttribute('data-original-index') || 0) - parseInt(b.getAttribute('data-original-index') || 0);
                });
                allPages.forEach(function (p) {
                    p.style.removeProperty('counter-reset');
                    container.appendChild(p);
                });
                bookletBtn.classList.remove('active');
            }
        }
    });

    // Gestión de bloqueo de scroll para Aside, Modales y Overlays
    (function () {
        function updateScrollLock() {
            var asideOpen = false;
            var modalOpen = false;

            // Aside del editor
            var aside = document.querySelector('aside.template');
            if (aside && !aside.classList.contains('hide')) {
                asideOpen = true;
            }

            // Cualquier .modal o .overlay visible
            var candidates = document.querySelectorAll('.modal, .overlay');
            for (var i = 0; i < candidates.length; i++) {
                var el = candidates[i];
                var cs = getComputedStyle(el);
                if (cs.display !== 'none' && cs.visibility !== 'hidden' && !el.classList.contains('hide')) {
                    modalOpen = true;
                    break;
                }
            }

            // Color picker
            var psp = document.getElementById('ps-color-picker');
            if (psp && psp.style.display === 'block') {
                modalOpen = true;
            }

            document.body.classList.toggle('aside-open', asideOpen);
            document.body.classList.toggle('modal-open', modalOpen);
        }

        // Observar todo el documento para cambios de atributos class/style
        // y de añadido/eliminado de nodos (modales AJAX)
        var scrollObserver = new MutationObserver(updateScrollLock);
        scrollObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'style'],
            subtree: true,
            childList: true
        });

        updateScrollLock();
    })();


    /**
     * Función genérica para guardar ajustes de plantilla vía AJAX
     */
    window.guardarPlantillaAJAX = function (form, data_extra, callback) {
        if (!form) form = document.getElementById('form-manual-estilos');
        if (!form) return;

        var fd = new FormData(form);
        if (data_extra) {
            for (var key in data_extra) {
                fd.set(key, data_extra[key]);
            }
        }

        fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!r.ok) throw new Error('Error ' + r.status);
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Respuesta no válida del servidor'); }
            });
        }).then(function (data) {
            var link = document.getElementById('estilos-personalizados');
            if (link && data.css_url) {
                link.onload = function () {
                    var f = document.getElementById('live-footer-fix'); if (f) f.remove();
                    var t = document.getElementById('live-typography-fix'); if (t) t.remove();
                };
                link.href = data.css_url;
            }
            var inlineCss = document.getElementById('estilos-personalizados-inline');
            if (inlineCss && data.css_inline) {
                inlineCss.textContent = data.css_inline;
            }

            if (data.fuentes) {
                for (var niv in data.fuentes) {
                    var f = data.fuentes[niv];
                    var section = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
                    if (!section) continue;

                    var linkMenu = section.querySelector('a');
                    if (linkMenu) {
                        if (f.fuente) linkMenu.style.fontFamily = "'" + f.fuente + "'";
                        linkMenu.style.fontSize = f.size || '';
                        linkMenu.style.fontWeight = f.weight || 'normal';
                        linkMenu.style.fontStyle = f.style || 'normal';
                        linkMenu.style.textAlign = f.align || 'left';
                        linkMenu.style.textTransform = f.transform === 'small-caps' ? 'none' : (f.transform || 'none');
                        linkMenu.style.fontVariant = f.transform === 'small-caps' ? 'small-caps' : 'normal';
                        linkMenu.style.textDecoration = f.decoration && f.decoration !== '0' ? 'underline' : 'none';
                        if (f.color) linkMenu.style.color = f.color;
                        linkMenu.textContent = niv.toUpperCase() + ': ' + (f.fuente || 'Sin seleccionar');
                    }

                    var fontInput = section.querySelector('input[name="fuente_' + niv + '"]');
                    if (fontInput) fontInput.value = f.fuente;

                    var sizeSelect = section.querySelector('select[name="size_' + niv + '"]');
                    if (sizeSelect) sizeSelect.value = f.size;

                    var variantSelect = section.querySelector('select[name="variant_' + niv + '"]');
                    if (variantSelect) {
                        var isBold = f.weight === 'bold';
                        var isItalic = f.style === 'italic';
                        variantSelect.value = isBold && isItalic ? 'bold-italic' : (isBold ? 'bold' : (isItalic ? 'italic' : ''));
                    }

                    var alignSelect = section.querySelector('select[name="align_' + niv + '"]');
                    if (alignSelect) alignSelect.value = f.align || 'left';

                    var transformSelect = section.querySelector('select[name="transform_' + niv + '"]');
                    if (transformSelect) transformSelect.value = f.transform || 'none';

                    var decorationSelect = section.querySelector('select[name="decoration_' + niv + '"]');
                    if (decorationSelect) decorationSelect.value = f.decoration || '0';

                    var colorInput = section.querySelector('input[name="color_' + niv + '"]');
                    if (colorInput) colorInput.value = f.color;

                    var colorPicker = section.querySelector('.web-color-picker');
                    if (colorPicker) colorPicker.style.backgroundColor = f.color;
                }
            }

            if (data.toast && typeof window.spawnToasts === 'function') {
                window.spawnToasts(data.toast);
            }

            if (typeof callback === 'function') callback(data);
        }).catch(function (err) {
            console.error('Error AJAX:', err);
            if (typeof window.editorToast === 'function') {
                window.editorToast('Error al guardar ajustes: ' + err.message, 'error');
            }
        });
    };

    /**
     * Listener para el nuevo Color Picker Universal
     */
    document.addEventListener('ps-color-saved', function (e) {
        var hex = e.detail.hex;
        var target = e.detail.target;
        var section = target.closest('section');
        if (section && section.dataset.nivel) {
            var niv = section.dataset.nivel;

            // Actualización al vuelo
            var preview = document.querySelector('main.plantilla');
            if (preview) {
                preview.style.setProperty('--' + niv + '-color', hex);
            }
            var linkMenu = section.querySelector('a');
            if (linkMenu) {
                linkMenu.style.color = hex;
            }
            var picker = section.querySelector('.web-color-picker');
            if (picker) {
                picker.style.backgroundColor = hex;
            }

            var colorInput = section.querySelector('input[name="color_' + niv + '"]');
            if (colorInput) {
                colorInput.value = hex;
            }

            var dataExtra = {};
            dataExtra['color_' + niv] = hex;
            window.guardarPlantillaAJAX(section.closest('form'), dataExtra);
        }
    });

    document.addEventListener('toggle', function (e) {
        var details = e.target.closest ? e.target.closest('aside.template details.template-panel') : null;
        if (!details || !details.open) return;

        var form = details.closest('form');
        if (form) {
            var all = form.querySelectorAll('details.template-panel[open]');
            for (var i = 0; i < all.length; i++) {
                if (all[i] !== details) all[i].open = false;
            }
        }
    }, true);


    var debounceTimer;
    ['change', 'input'].forEach(function (evt) {
        Kumbia.utils.on(evt, 'aside.template select[data-change-ajax], aside.template input[data-change-ajax]', function (e) {
            var $el = this, name = $el.name, val = $el.value;

            // 1. Actualización visual inmediata (sin esperar a BD)
            var preview = document.querySelector('main.plantilla');
            if (!preview) return;

            if (name.indexOf('footer_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                preview.style.setProperty(prop, val + (isNaN(val) ? '' : 'px'));
            } else if (name.indexOf('margin_') === 0 || name.indexOf('padding_') === 0) {
                var parts = name.split('_');
                var prop = '--' + parts[1] + '-' + parts[0] + '-' + parts[2];
                preview.style.setProperty(prop, val + 'px');
            } else if (name === 'bullet_ul') {
                preview.style.setProperty('--list-style-bullet', "'" + val + " '");
            } else if (name === 'bullet_ul_ul') {
                preview.style.setProperty('--list-style-sub-bullet', "'" + val + " '");
            } else if (name === 'bullet_checkbox') {
                preview.style.setProperty('--list-style-checkbox', "'" + val + " '");
            } else if (name === 'table_zebra_opacity') {
                var opacity = parseInt(val, 10) || 0;
                var colorEl = document.getElementById('table_zebra_color');
                var color = colorEl ? colorEl.value : 'transparent';
                if (opacity > 0 && color && color !== 'transparent') {
                    preview.style.setProperty('--table-zebra-bg', 'color-mix(in srgb, ' + color + ' ' + opacity + '%, transparent)');
                } else {
                    preview.style.setProperty('--table-zebra-bg', 'transparent');
                }
            } else if (name === 'table_zebra_color') {
                var opacityEl = document.getElementsByName('table_zebra_opacity')[0];
                var opacity = opacityEl ? (parseInt(opacityEl.value, 10) || 0) : 0;
                if (opacity > 0 && val && val !== 'transparent') {
                    preview.style.setProperty('--table-zebra-bg', 'color-mix(in srgb, ' + val + ' ' + opacity + '%, transparent)');
                } else {
                    preview.style.setProperty('--table-zebra-bg', 'transparent');
                }
            } else if (name.indexOf('table_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                var suffix = '';
                if (name.endsWith('_width') || name.endsWith('_padding') || name.endsWith('_x') || name.endsWith('_y')) {
                    suffix = isNaN(val) || val === '' ? '' : 'px';
                }
                preview.style.setProperty(prop, val + suffix);
            } else if (name.indexOf('blockquote_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                var suffix = '';
                if (name.endsWith('_width')) {
                    suffix = isNaN(val) || val === '' ? '' : 'px';
                }
                preview.style.setProperty(prop, val + suffix);
            } else if (name.indexOf('_h') !== -1 || name.indexOf('_body') !== -1 || name.indexOf('_small') !== -1 || name.indexOf('_dropcap') !== -1) {
                // Tipografía: size_h1, variant_h1, align_h1, transform_h1, color_h1, size_dropcap, color_dropcap, etc.
                var parts = name.split('_');
                var propType = parts[0];
                var level = parts[1];

                // Actualizar el documento
                if (propType === 'size') preview.style.setProperty('--' + level + '-size', val);
                if (propType === 'color') preview.style.setProperty('--' + level + '-color', val);
                if (propType === 'align') preview.style.setProperty('--' + level + '-align', val);
                if (propType === 'variant') {
                    preview.style.setProperty('--' + level + '-weight', val.indexOf('bold') !== -1 ? 'bold' : 'normal');
                    preview.style.setProperty('--' + level + '-style', val.indexOf('italic') !== -1 ? 'italic' : 'normal');
                }
                if (propType === 'transform') {
                    preview.style.setProperty('--' + level + '-transform', val === 'small-caps' ? 'none' : val);
                    preview.style.setProperty('--' + level + '-variant', val === 'small-caps' ? 'small-caps' : 'normal');
                }
                if (propType === 'decoration') {
                    preview.style.setProperty('--' + level + '-decoration', val);
                }

                // Actualizar la previsualización en el propio MENÚ
                var section = $el.closest('section[data-nivel]');
                if (section) {
                    var linkMenu = section.querySelector('a');
                    if (linkMenu) {
                        if (propType === 'size') linkMenu.style.fontSize = val;
                        if (propType === 'color') linkMenu.style.color = val;
                        if (propType === 'align') linkMenu.style.textAlign = val;
                        if (propType === 'variant') {
                            linkMenu.style.fontWeight = val.indexOf('bold') !== -1 ? 'bold' : 'normal';
                            linkMenu.style.fontStyle = val.indexOf('italic') !== -1 ? 'italic' : 'normal';
                        }
                        if (propType === 'transform') {
                            linkMenu.style.textTransform = val === 'small-caps' ? 'none' : val;
                            linkMenu.style.fontVariant = val === 'small-caps' ? 'small-caps' : 'normal';
                        }
                        if (propType === 'decoration') {
                            linkMenu.style.textDecoration = val === '0' ? 'none' : 'underline';
                        }
                    }
                }
            }

            // 2. Debounce de dos segundos para el guardado real en BD
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var dataExtra = {};
                dataExtra[name] = val;
                window.guardarPlantillaAJAX($el.closest('form'), dataExtra);
            }, 2000);
        });
    });

    Kumbia.utils.on('change', 'aside.template .dropimage [type="file"]', function (e) {
        var form = this.closest('form'), drop = this.closest('.dropimage'), name = this.name;
        window.guardarPlantillaAJAX(form, null, function (data) {
            var url = name === 'footer_imagen' ? (data.url_footer || '') : (name === 'fondo_pergamino_even' ? (data.url_even || '') : (data.url_imagen || data.url || ''));
            if (url && drop) {
                // 1. Forzamos fondo en el contenedor
                drop.style.setProperty('background-image', 'url(' + url + '?t=' + Date.now() + ')', 'important');
                drop.style.setProperty('background-repeat', 'no-repeat', 'important');
                drop.style.setProperty('background-size', 'cover', 'important');
                drop.style.setProperty('background-position', 'center center', 'important');

                // 2. Si hay etiquetas <img> de previsualización (que no sean del botón de borrar), las actualizamos
                var imgs = drop.querySelectorAll('img:not(button img)');
                if (imgs.length > 0) {
                    imgs.forEach(function (img) {
                        img.src = url + '?t=' + Date.now();
                        img.style.setProperty('width', '100%', 'important');
                        img.style.setProperty('height', '100%', 'important');
                        img.style.setProperty('object-fit', 'cover', 'important');
                        img.style.setProperty('object-position', 'center', 'important');
                    });
                } else {
                    // Si no hay img, creamos una para previsualización
                    var newImg = document.createElement('img');
                    newImg.src = url + '?t=' + Date.now();
                    newImg.style.setProperty('width', '100%', 'important');
                    newImg.style.setProperty('height', '100%', 'important');
                    newImg.style.setProperty('object-fit', 'cover', 'important');
                    drop.appendChild(newImg);
                }

                drop.classList.add('dropnocontent');

                // Actualizar documento al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    var prop = name === 'footer_imagen' ? '--footer-imagen' : (name === 'fondo_pergamino_even' ? '--background-image-even' : '--background-image');
                    preview.style.setProperty(prop, "url('" + url + "')");
                }
            }
            var act = form.querySelector('[name="' + name + '_actual"]');
            if (act) act.value = url;
        });
    });

    Kumbia.utils.on('click', 'aside.template .dropimage button', function (e) {
        var form = this.closest('form'), drop = this.closest('.dropimage');
        if (drop) {
            var input = drop.querySelector('[type="file"]'), name = input ? input.name : '';
            drop.removeAttribute('style'); drop.classList.remove('dropnocontent'); drop.querySelectorAll(':scope > img').forEach(function (i) { i.remove(); });
            var act = name ? form.querySelector('[name="' + name + '_actual"]') : null;
            var extra = {};
            if (name) {
                extra[name] = '';
                extra[name + '_actual'] = act ? act.value : '';
            }
            window.guardarPlantillaAJAX(form, extra, function (data) { var act = form.querySelector('[name="' + name + '_actual"]'); if (act) act.value = ''; });
        }
    });

    document.addEventListener('click', function (e) {
        var it = e.target.closest('.font-item');
        if (it) {
            var niv = it.closest('.font-list-container').dataset.nivel;
            var sec = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
            if (sec) {
                var f = it.dataset.font;
                var inp = sec.querySelector('input[name="fuente_' + niv + '"]');
                if (inp) inp.value = f;

                // Actualización al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    preview.style.setProperty('--' + niv + '-family', "'" + f + "'");
                }
                var linkMenu = sec.querySelector('a');
                if (linkMenu) {
                    linkMenu.style.fontFamily = "'" + f + "'";
                    linkMenu.textContent = (niv === 'dropcap' ? 'CAPITULAR' : niv.toUpperCase()) + ': ' + f;
                }

                var m = it.closest('.modal');
                var o = document.querySelector('.overlay');
                if (m) m.style.display = 'none';
                if (o) o.style.display = 'none';
                var dataExtra = {};
                dataExtra['fuente_' + niv] = f;

                window.guardarPlantillaAJAX(sec.closest('form'), dataExtra);
            }
        }
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest('#btn-import-google-font');
        if (b) {
            var i = document.getElementById('google-font-name');
            var f = i ? i.value.trim() : '';
            if (!f) return;
            var niv = b.closest('.modal').querySelector('.font-list-container').dataset.nivel;
            var sec = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
            if (sec) {
                var h = sec.querySelector('input[name="fuente_' + niv + '"]');
                if (h) h.value = f;

                // Actualización al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    preview.style.setProperty('--' + niv + '-family', "'" + f + "'");
                }
                var linkMenu = sec.querySelector('a');
                if (linkMenu) {
                    linkMenu.style.fontFamily = "'" + f + "'";
                    linkMenu.textContent = (niv === 'dropcap' ? 'CAPITULAR' : niv.toUpperCase()) + ': ' + f;
                }

                var m = b.closest('.modal');
                var o = document.querySelector('.overlay');
                if (m) m.style.display = 'none';
                if (o) o.style.display = 'none';
                var dataExtra = {};
                dataExtra['fuente_' + niv] = f;
                dataExtra['forzar_descarga_fuente'] = '1';  

                window.guardarPlantillaAJAX(sec.closest('form'), dataExtra);
            }
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#btn-ia-generar');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var promptInput = document.getElementById('ai-prompt');
            var promptVal = promptInput ? promptInput.value.trim() : '';
            if (!promptVal) {
                if (typeof window.editorToast === 'function') window.editorToast('PROMPT VACIO', 'error');
                return;
            }

            var form = btn.closest('form');
            var textarea = form ? form.querySelector('textarea[name="descripcion"]') : null;
            var manualesIduInput = form ? form.querySelector('[name="manuales_idu"]') : null;
            var manualesIdu = manualesIduInput ? manualesIduInput.value : '';

            if (!manualesIdu) {
                if (typeof window.editorToast === 'function') window.editorToast('Error: no se encontró manuales_idu', 'error');
                return;
            }

            var currentContent = textarea ? textarea.value : '';

            var fd = new FormData();
            fd.append('prompt', promptVal);
            fd.append('manuales_idu', manualesIdu);
            fd.append('current_content', currentContent);

            if (typeof window.editorToast === 'function') window.editorToast('PROMPT ENVIADO', 'info');

            btn.disabled = true;

            fetch('/ev/manuales/ia_html', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) {
                    if (!r.ok) {
                        return r.text().then(function(errText) {
                            throw new Error(errText || ('Error en la petición: ' + r.status));
                        });
                    }
                    return r.text();
                })
                .then(function (text) {
                    if (typeof window.editorToast === 'function') window.editorToast('RESPUESTA INCORPORADA', 'info');
                    if (textarea) {
                        textarea.value = text;
                        if (typeof textarea_auto_height === 'function') {
                            textarea_auto_height(textarea);
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof window.editorToast === 'function') window.editorToast('Error al generar con IA: ' + err.message, 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        }
    });

})(window, document);

