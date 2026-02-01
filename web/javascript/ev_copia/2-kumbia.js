/* 2-kumbia.js
 * Framework data-* autónomo (sin dependencias externas ni otros archivos).
 *
 * Reglas:
 * ✦ Siempre debes dar selector en data-ajax, data-live, data-ajax_append, etc.
 * ✦ Nada de heurística prev/next.
 * ✦ Sin optional chaining.
 * ✦ Contenedores con auto-scroll al inyectar AJAX: pon data-autoscroll.
 * ✦ El JS debe enganchar también a contenido inyectado dinámicamente (AJAX / SSE / WS).
 */

(() => {
	"use strict";

	/* ======================
	 * UTILS INTERNOS
	 * ====================== */

	function getData(el, key) {
		const ds = el.dataset || {};
		if (key in ds) return ds[key];

		const snake = key.replace(/[A-Z]/g, function (m) { return "_" + m.toLowerCase(); });
		if (snake in ds) return ds[snake];

		const dash = key.replace(/[A-Z]/g, function (m) { return "-" + m.toLowerCase(); });
		let v = el.getAttribute("data-" + dash);
		if (v == null) v = el.getAttribute("data-" + snake);
		if (v == null) v = el.getAttribute("data-" + key);
		return v == null ? null : v;
	}

	const DEFAULT_HEADERS = { "X-Requested-With": "XMLHttpRequest" };

	function toQuery(p) {
		if (!p) return "";
		const qs = new URLSearchParams(p).toString();
		return qs ? "?" + qs : "";
	}

	async function fetchHtml(url, params) {
		const full = url + toQuery(params);
		const res = await fetch(full, {
			method: "GET",
			cache: "no-store",
			headers: DEFAULT_HEADERS,
			credentials: "same-origin"
		});
		if (!res.ok) throw new Error("GET " + full + " => " + res.status);
		return res.text();
	}

	async function postForm(url, body) {
		const res = await fetch(url, {
			method: "POST",
			cache: "no-store",
			headers: DEFAULT_HEADERS,
			body: body,
			credentials: "same-origin"
		});
		if (!res.ok) throw new Error("POST " + url + " => " + res.status);
		return res.text();
	}

	// Sanitiza fragmento: elimina <script> antes de inyectar
	function parseHTMLWithoutScripts(html) {
		const t = document.createElement("template");
		t.innerHTML = html;
		t.content.querySelectorAll("script").forEach(function (s) {
			s.remove();
		});
		return t.content;
	}

	function selectorOf(el, raw) {
		if (raw && raw.trim()) return raw.trim();
		if (!el) return "(sin selector)";
		if (el.id) return "#" + el.id;

		const clsRaw = (el.className || "").toString().trim();
		const clsArr = clsRaw.split(/\s+/).filter(Boolean);
		if (clsArr.length) {
			return el.tagName.toLowerCase() + "." + clsArr[0];
		}

		const nm = el.getAttribute ? el.getAttribute("name") : null;
		if (nm) {
			return el.tagName.toLowerCase() + '[name="' + nm + '"]';
		}

		return el.tagName ? el.tagName.toLowerCase() : "(nodo)";
	}

	function on(type, selector, handler) {
		document.body.addEventListener(type, function (ev) {
			const target = ev.target.closest(selector);
			if (target && document.body.contains(target)) {
				handler.call(target, ev);
			}
		});
	}

	/* ======================
	 * FX INTERNOS
	 * ====================== */

	function fxShow(el) {
		if (el.classList && el.classList.contains("hide")) {
			el.classList.remove("hide");
		}

		if (getComputedStyle(el).display === "none") {
			var disp = el.getAttribute("data-display");
			if (disp === "flex") {
				el.style.display = "flex";
			} else {
				el.style.display = "";
				if (getComputedStyle(el).display === "none") {
					el.style.display = "block";
				}
			}
		}
	}

	function fxHide(el) {
		if (el.classList && !el.classList.contains("hide")) {
			el.classList.add("hide");
		}
		el.style.display = "none";
	}

	function fxToggle(el) {
		if (getComputedStyle(el).display === "none" || (el.classList && el.classList.contains("hide"))) {
			fxShow(el);
		} else {
			fxHide(el);
		}
	}

	function fxFadeOut(el, ms) {
		if (ms == null) ms = 200;
		el.style.transition = "opacity " + ms + "ms";
		el.style.opacity = "1";
		requestAnimationFrame(function () {
			el.style.opacity = "0";
			setTimeout(function () {
				fxHide(el);
			}, ms);
		});
	}

	function fxSlideDown(el, ms) {
		if (ms == null) ms = 200;
		if (el.style.display === "none" || (el.classList && el.classList.contains("hide"))) {
			if (el.classList && el.classList.contains("hide")) {
				el.classList.remove("hide");
			}
			el.style.display = "";
		}
		const h = el.scrollHeight;
		el.style.overflow = "hidden";
		el.style.maxHeight = "0";
		el.style.transition = "max-height " + ms + "ms ease";
		requestAnimationFrame(function () {
			el.style.maxHeight = h + "px";
			setTimeout(function () {
				el.style.maxHeight = "";
				el.style.overflow = "";
			}, ms);
		});
	}

	/* ======================
	 * CORE data-*
	 * ====================== */

	function requireSelector(rawSel, label) {
		if (!rawSel || !rawSel.trim()) {
			console.warn(label + ": falta selector data-* obligatorio");
			return null;
		}
		return rawSel.trim();
	}

	const Kumbia = {
		async aAjax(ev) {
			ev.preventDefault();

			const rawSel = getData(this, "ajax") || "";
			const sel = requireSelector(rawSel, "ajax");
			if (!sel) return;

			const href = this.href || "";
			if (!href) {
				console.warn("ajax: falta href");
				return;
			}

			const to = document.querySelector(sel);
			if (!to) {
				console.warn("ajax: selector no resuelve:", sel);
				return;
			}

			const selStr = selectorOf(to, sel);
			console.log([selStr, href]);

			const html = await fetchHtml(href);
			to.innerHTML = "";
			to.appendChild(parseHTMLWithoutScripts(html));
			to.style.display = "";
		},

		active() {
			const explicit = getData(this, "active");
			if (explicit && explicit.trim()) {
				Array.prototype.forEach.call(document.querySelectorAll(explicit), function (el) {
					el.removeAttribute("aria-current");
					el.classList.remove("active");
				});
				this.setAttribute("aria-current", "true");
				this.classList.add("active");
				return;
			}

			const parent = this.parentElement;
			if (parent && parent.matches("nav, ul, ol, section, div")) {
				Array.prototype.forEach.call(parent.querySelectorAll("button, a, li > a, li > button"), function (el) {
					el.removeAttribute("aria-current");
					el.classList.remove("active");
				});
				this.setAttribute("aria-current", "true");
				this.classList.add("active");
			}
		},

		alert() {
			alert(getData(this, "alert"));
		},

		clone_append() {
			const raw = getData(this, "cloneAppend") || "";
			const i = raw.indexOf(", ");
			if (i === -1) {
				console.warn("clone_append: falta 'selectorOrigen, selectorDestino'");
				return;
			}
			const a = raw.slice(0, i);
			const b = raw.slice(i + 2);

			const el = document.querySelector(a);
			const to = document.querySelector(b);
			if (el && to) {
				to.appendChild(el.cloneNode(true));
			} else {
				console.warn("clone_append: no resuelve", a, b);
			}
		},

		confirm(ev) {
			if (!confirm(getData(this, "confirm"))) {
				ev.preventDefault();
				ev.stopImmediatePropagation();
			}
		},

		effect(name) {
			return function () {
				const raw = getData(this, name) || "";
				const list = (raw && raw.trim())
					? Array.prototype.slice.call(document.querySelectorAll(raw))
					: [this];

				list.forEach(function (el) {
					if (name === "click") el.click();
					if (name === "hide") fxHide(el);
					if (name === "show") fxShow(el);
					if (name === "toggle") fxToggle(el);
					if (name === "fadeOut") fxFadeOut(el);
					if (name === "slideDown") fxSlideDown(el);
				});
			};
		},

		async formAjax(ev) {
			ev.preventDefault();

			const form = this.closest("form");
			if (!form) return;

			Array.prototype.forEach.call(form.querySelectorAll('[style*="none"]'), function (n) {
				n.remove();
			});

			// Resolver URL: atributo action vacío usa la URL actual (form.action).
			let url = (form.getAttribute("action") || "").trim();
			if (!url) {
                url = (form.action || location.href).toString().trim();
			}

			const sel = (form.getAttribute("data-ajax_append")
				|| form.getAttribute("data-ajax_prepend")
				|| form.getAttribute("data-ajax")
				|| "").trim();
			const needed = requireSelector(sel, "form");
			if (!needed) return;

			const to = document.querySelector(sel);
			if (!to) {
				console.warn("form: selector no resuelve:", sel);
				return;
			}

			const fd = new FormData(form);
			const buttons = Array.prototype.slice.call(form.querySelectorAll('[type="submit"]'));
			buttons.forEach(function (b) { b.disabled = true; });

			const btnName = this.getAttribute("name");
			if (btnName !== null) fd.append(btnName, this.value);

			try {
				const selStr = selectorOf(to, sel);
				console.log([selStr, url]);

				const html = await postForm(url, fd);

				if (form.hasAttribute("data-ajax_append")) {
					to.appendChild(parseHTMLWithoutScripts(html));
					to.style.display = "";
				} else if (form.hasAttribute("data-ajax_prepend")) {
					const frag = parseHTMLWithoutScripts(html);
					to.insertBefore(frag, to.firstChild);
					to.style.display = "";
				} else {
					to.innerHTML = "";
					to.appendChild(parseHTMLWithoutScripts(html));
					to.style.display = "";
				}
			} finally {
				buttons.forEach(function (b) { b.disabled = false; });
			}
		},

		async live() {
			const href = (getData(this, "href") || "").trim();
			if (!href) {
				console.warn("live: falta href");
				return;
			}

			const rawSel = (getData(this, "live") || "").trim();
			const sel = requireSelector(rawSel, "live");
			if (!sel) return;

			const to = document.querySelector(sel);
			if (!to) {
				console.warn("live: selector no resuelve:", sel);
				return;
			}

			const selStr = selectorOf(to, sel);
			console.log([selStr, href]);

			const html = await fetchHtml(href, { keywords: this.value });
			to.innerHTML = "";
			to.appendChild(parseHTMLWithoutScripts(html));
			to.style.display = "";
		},

		remove() {
			const to = getData(this, "remove");

			var el = null;
			if (!to) {
				el = this;
			} else if (to === "parent parent") {
				el = (this.parentElement && this.parentElement.parentElement)
					? this.parentElement.parentElement
					: null;
			} else if (to === "parent") {
				el = this.parentElement;
			} else {
				el = document.querySelector(to);
			}

			if (el) el.remove();
		},

		async selectAjax() {
			const base = (getData(this, "href") || "").trim();
			if (!base) {
				console.warn("select: falta href base");
				return;
			}
			const href = base + this.value;

			const rawSel = (getData(this, "ajax") || "").trim();
			const sel = requireSelector(rawSel, "select");
			if (!sel) return;

			const to = document.querySelector(sel);
			if (!to) {
				console.warn("select: selector no resuelve:", sel);
				return;
			}

			const selStr = selectorOf(to, sel);
			console.log([selStr, href]);

			const html = await fetchHtml(href);
			to.innerHTML = "";
			to.appendChild(parseHTMLWithoutScripts(html));
			to.style.display = "";
		},

		selectRedirect() {
			const base = getData(this, "redirect") || "";
			location.href = base + this.value;
		},

		selectToggle() {
			const scopeSel = getData(this, "changeToggle");
			if (!(scopeSel && scopeSel.trim())) {
				console.warn("selectToggle: falta data-change_toggle (selector base)");
				return;
			}

			const val = this.value;
			const target = document.querySelector(scopeSel + '[data-grp="' + CSS.escape(val) + '"]');

			if (val && target) {
				Array.prototype.forEach.call(target.querySelectorAll("input"), function (i) {
					i.value = "";
				});

				Array.prototype.forEach.call(document.querySelectorAll(scopeSel), function (el) {
					fxToggle(el);
				});
			}
		},

		style() {
			const raw = getData(this, "style") || "";
			const i = raw.indexOf(", ");
			if (i === -1) {
				this.setAttribute("style", raw);
				return;
			}

			const sel = raw.slice(0, i);
			const styleText = raw.slice(i + 2);

			Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
				el.setAttribute("style", styleText);
			});
		},

		toggleClass() {
			const raw = getData(this, "toggleClass") || "";
			const i = raw.indexOf(", ");

			if (i === -1) {
				const cn = raw.trim();
				if (cn) this.classList.toggle(cn);
				return;
			}

			const cn = raw.slice(0, i);
			const sel = raw.slice(i + 2);

			Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
				el.classList.toggle(cn);
			});
		},

		toggleDisplay() {
			const sel = getData(this, "toggleDisplay");
			if (!(sel && sel.trim())) {
				console.warn("toggleDisplay: falta selector en data-toggle_display");
				return;
			}

			const nodes = Array.prototype.slice.call(document.querySelectorAll(sel));
			nodes.forEach(function (el) {
				if (getComputedStyle(el).display === "none") {
					el.style.display = el.getAttribute("data-display") === "flex" ? "flex" : "";
				} else {
					el.style.display = "none";
				}
			});
		},

		bind() {
			on("click", "[data-active]", this.active);
			on("click", "nav[data-group] > button, ul[data-group] > li > *", this.active);

			on("click", "a[data-ajax]", this.aAjax);

			on("click", 'form[data-ajax] [type="submit"]', this.formAjax);
			on("click", 'form[data-ajax_append] [type="submit"]', this.formAjax);
			on("click", 'form[data-ajax_prepend] [type="submit"]', this.formAjax);

			on("change", "select[data-ajax]", this.selectAjax);
			on("change", "select[data-redirect]", this.selectRedirect);
			on("change", "select[data-change_toggle]", this.selectToggle);

			on("click", "[data-alert]", this.alert);
			on("click", "[data-click]", this.effect("click"));
			on("click", "[data-clone_append]", this.clone_append);
			on("click", "[data-confirm]", this.confirm);
			on("click", "[data-fadeOut]", this.effect("fadeOut"));
			on("click", "[data-hide]", this.effect("hide"));
			on("keyup", "[data-live]", this.live);
			on("click", "[data-remove]", this.remove);
			on("click", "[data-show]", this.effect("show"));
			on("click", "[data-slideDown]", this.effect("slideDown"));
			on("click", "[data-style]", this.style);
			on("click", "[data-toggle]", this.effect("toggle"));
			on("click", "[data-toggle_class]", this.toggleClass);
			on("click", "[data-toggle_display]", this.toggleDisplay);
		}
	};

	/* ======================
	 * UTILIDAD REUTILIZABLE: ELIMINACIÓN DIFERIDA
	 * ======================
	 * Convención declarativa y genérica:
	 * ⮞ <div data-id="XYZ"> ...contenido vivo... </div>
	 * ⮞ <span data-remove_id="XYZ"></span>
	 *
	 * Donde aparezca data-remove_id="XYZ":
	 * ⮞ se busca [data-id="XYZ"], se elimina ese nodo
	 * ⮞ se elimina también el marcador data-remove_id
	 *
	 * Se puede usar para borrar nodos ya existentes o recién inyectados
	 * desde AJAX / SSE / WS sin <script> inline.
	 */

	function applyDeferredRemovals(root) {
		var marks = (root || document).querySelectorAll('[data-remove_id]');
		marks.forEach(function (mk) {
			var id = mk.getAttribute('data-remove_id');
			if (id) {
				var objetivo = document.querySelector('[data-id="' + CSS.escape(id) + '"]');
				if (objetivo) objetivo.remove();
			}
			mk.remove();
		});
	}

	/* ======================
	 * AUTOSCROLL + OBSERVER GLOBAL
	 * ======================
	 * REGLA: el elemento que TIENE barra de scroll vertical
	 * es el que lleva data-autoscroll.
	 *
	 * Usamos el MISMO observer global también para:
	 * ⮞ eliminación diferida (applyDeferredRemovals)
	 */

	function forceScrollBottom(box) {
		if (!box) return;

		var beforeTop = box.scrollTop;
		var beforeLeft = box.scrollLeft;

		box.scrollTop = box.scrollHeight;
		box.scrollLeft = box.scrollWidth;

		console.log("[autoscroll] force ⇒", selectorOf(box, ""), {
			top: beforeTop + "→" + box.scrollTop,
			left: beforeLeft + "→" + box.scrollLeft
		});
	}

	function setupAutoScrollBox(box) {
		if (!box || box.__autoScrollObs) return;

		console.log("[autoscroll] hook", selectorOf(box, ""));

		var obs = new MutationObserver(function (mutList) {
			var added = 0;

			mutList.forEach(function (m) {
				if (m.type === "childList" && m.addedNodes && m.addedNodes.length) {
					added += m.addedNodes.length;
				}
			});

			if (added > 0) {
				console.log("[autoscroll] +" + added + " nuevos nodos en", selectorOf(box, ""));
				setTimeout(function () { forceScrollBottom(box); }, 0);
			}
		});

		try {
			obs.observe(box, { childList: true, subtree: true });
			box.__autoScrollObs = obs;
		} catch (e) {
			console.warn("[autoscroll] observer fail", e && e.message ? e.message : e);
		}

		// Primera vez: baja al final tal cual
		setTimeout(function () { forceScrollBottom(box); }, 0);
	}

	function scanAutoScroll(root) {
		var boxes = (root || document).querySelectorAll("[data-autoscroll]");
		boxes.forEach(function (b) {
			setupAutoScrollBox(b);
		});
	}

	function bootAutoScroll() {
		// Enganchar las cajas actuales
		scanAutoScroll(document);

		// Aplicar eliminaciones diferidas iniciales
		applyDeferredRemovals(document);

		// Vigilar DOM global para cajas nuevas y marcas de borrado
		var mo = new MutationObserver(function (muts) {
			for (var i = 0; i < muts.length; i++) {
				var m = muts[i];
				if (m.type !== "childList" || !m.addedNodes) continue;

				m.addedNodes.forEach(function (n) {
					if (n.nodeType !== 1) return;

					// 1) autoscroll: enganchar cajas nuevas
					if (n.matches && n.matches("[data-autoscroll]")) {
						setupAutoScrollBox(n);
					}
					var qsScroll = n.querySelectorAll ? n.querySelectorAll("[data-autoscroll]") : [];
					if (qsScroll && qsScroll.length) {
						qsScroll.forEach(function (x) {
							setupAutoScrollBox(x);
						});
					}

					// 2) removals diferidos:
					//    si entra un marcador data-remove_id,
					//    puede venir sin el nodo objetivo en el mismo fragmento.
					//    Para cubrir TODOS los casos, re-barrimos todo el DOM.
					if (
						(n.matches && n.matches("[data-remove_id]")) ||
						(n.querySelectorAll && n.querySelectorAll("[data-remove_id]").length)
					) {
						applyDeferredRemovals(document);
					}
				});
			}
		});

		try {
			mo.observe(document.documentElement, { childList: true, subtree: true });
		} catch (e) {
			console.warn("[autoscroll] global observer fail", e && e.message ? e.message : e);
		}
	}

	/* ======================
	 * ARRANQUE
	 * ====================== */

	window.Kumbia = Kumbia;
	Kumbia.bind();
	bootAutoScroll();
})();
