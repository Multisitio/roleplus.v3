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
			const ds = el.dataset || {};
			if (key in ds) return ds[key];

			const snake = key.replace(/[A-Z]/g, function (m) { return "_" + m.toLowerCase(); });
			if (snake in ds) return ds[snake];

			const dash = key.replace(/[A-Z]/g, function (m) { return "-" + m.toLowerCase(); });
			var v = el.getAttribute("data-" + dash);
			if (v == null) v = el.getAttribute("data-" + snake);
			if (v == null) v = el.getAttribute("data-" + key);
			return v == null ? null : v;
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
			if (el.classList && el.classList.contains("overlay")) return true;
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
			var list = document.querySelectorAll(".overlay, [role=\"dialog\"]");
			for (var i = 0; i < list.length; i++) {
				if (Overlay.isVisible(list[i])) return true;
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

	/* ========================================
	 * HANDLERS
	 * ======================================== */
	const Handlers = {
		// GET AJAX en enlaces
		ajaxLink: async function (ev) {
			ev.preventDefault();

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
			return function () {
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
			if (!confirm(Utils.getData(this, "confirm"))) {
				ev.preventDefault();
				ev.stopImmediatePropagation();
			}
		},

		remove: function () {
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
