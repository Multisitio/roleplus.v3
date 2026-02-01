/* Mocks fetch (respuestas HTML simples) */
(() => {
	const realFetch = window.fetch.bind(window);
	window.fetch = async (input, init = {}) => {
		const urlStr = typeof input === "string" ? input : input.url;
		const u = new URL(urlStr, location.href);
		const path = u.pathname;
		const method = (init.method || "GET").toUpperCase();

		if (path === "/mock/get1" && method === "GET")
			return new Response(`<div data-frag data-src="/mock/get1">GET OK</div>`, { status: 200, headers: { "Content-Type": "text/html" } });

		if (path.startsWith("/mock/option/") && method === "GET") {
			const v = path.split("/mock/option/")[1] || "";
			return new Response(`<div data-opt>Option ${v}</div>`, { status: 200, headers: { "Content-Type": "text/html" } });
		}
		if (path === "/mock/search" && method === "GET") {
			const q = u.searchParams.get("keywords") || "";
			return new Response(`<ul><li>Resultado: ${q}</li></ul>`, { status: 200, headers: { "Content-Type": "text/html" } });
		}
		if (path === "/mock/postNormal" && method === "POST")
			return new Response(`<div data-posted>POST Normal</div><script>window.__should_not_run=true<\/script>`, { status: 200, headers: { "Content-Type": "text/html" } });

		if (path === "/mock/postAppend" && method === "POST")
			return new Response(`<span data-row>APPEND</span>`, { status: 200, headers: { "Content-Type": "text/html" } });

		if (path === "/mock/postPrepend" && method === "POST")
			return new Response(`<span data-row>PREPEND</span>`, { status: 200, headers: { "Content-Type": "text/html" } });

		return realFetch(input, init);
	};
})();

/* Runner de pruebas */
(async () => {
	const cards = document.querySelector('section[data-role="cards"]');
	const outT = document.querySelector('[data-out="total"]');
	const outOk = document.querySelector('[data-out="oks"]');
	const outFail = document.querySelector('[data-out="fails"]');
	const summary = { total: 0, ok: 0, fail: 0 };
	const setSummary = () => { outT.textContent = summary.total; outOk.textContent = summary.ok; outFail.textContent = summary.fail; };

	function addCard(title, subtitle) {
		const el = document.createElement("article");
		el.innerHTML = `
<header>
  <strong>${title}</strong>
  <small>${subtitle || ""}</small>
  <output data-role="status" aria-live="polite">PEND</output>
</header>
<section></section>`;
		cards.appendChild(el);
		return el;
	}

	function mark(card, ok, note = "") {
		let st = card.querySelector('output[data-role="status"]');
		if (!st) {
			const hdr = card.querySelector("header") || card;
			st = document.createElement("output");
			st.setAttribute("data-role", "status");
			st.setAttribute("aria-live", "polite");
			hdr.appendChild(st);
		}
		const body = card.querySelector("section") || card;
		st.textContent = ok ? "OK" : "FALLO";
		st.setAttribute("data-state", ok ? "ok" : "fail");
		if (note) {
			const p = document.createElement("p");
			p.setAttribute("data-note", "");
			p.textContent = note;
			body.appendChild(p);
		}
		summary.total++; ok ? summary.ok++ : summary.fail++; setSummary();
	}

	const sleep = (ms) => new Promise(r => setTimeout(r, ms));

	// stubs alert/confirm
	let lastAlert = null, nextConfirm = true;
	const realAlert = window.alert, realConfirm = window.confirm;
	window.alert = (m) => { lastAlert = String(m); };
	window.confirm = () => nextConfirm;

	async function run(title, subtitle, fn) {
		const card = addCard(title, subtitle);
		try { await fn(card); } catch (e) { mark(card, false, String(e?.message || e)); }
	}

	await run("aAjax / loadHtml", "Carga GET en contenedor y sanitiza", async (card) => {
		document.querySelector('a[data-ajax]').click();
		await sleep(50);
		const ok = document.querySelector('[data-ref="ajax-target"] [data-frag]') && !window.__should_not_run;
		mark(card, !!ok, "fragmento GET presente");
	});

	await run("formAjax (normal)", "Reemplaza contenedor sin ejecutar <script>", async (card) => {
		document.querySelector('form[action="/mock/postNormal"] button[type="submit"]').click();
		await sleep(50);
		const ok = document.querySelector('[data-ref="form-target"] [data-posted]') && !window.__should_not_run;
		mark(card, !!ok, "reemplazo OK");
	});

	await run("formAjax (append)", "Hace append y muestra", async (card) => {
		const before = document.querySelectorAll('[data-ref="form-target-append"] [data-row]').length;
		document.querySelector('form[action="/mock/postAppend"] button[type="submit"]').click();
		await sleep(50);
		const after = document.querySelectorAll('[data-ref="form-target-append"] [data-row]').length;
		mark(card, after === before + 1, "row añadido");
	});

	await run("formAjax (prepend)", "Inserta al inicio", async (card) => {
		document.querySelector('form[action="/mock/postPrepend"] button[type="submit"]').click();
		await sleep(50);
		const first = document.querySelector('[data-ref="form-target-prepend"]').firstElementChild;
		mark(card, first && first.textContent === "PREPEND", "PREPEND primero");
	});

	await run("live (keyup)", "Consulta /mock/search?keywords=…", async (card) => {
		const input = document.querySelector('input[data-live]');
		input.value = "abc";
		input.dispatchEvent(new KeyboardEvent("keyup", { bubbles: true }));
		await sleep(50);
		const ok = /abc/.test(document.querySelector('[data-ref="live-target"]').textContent);
		mark(card, ok, "Resultado: abc");
	});

	await run("selectAjax (change)", "Carga por valor", async (card) => {
		const sel = document.querySelector('select[data-ajax]');
		sel.value = "B";
		sel.dispatchEvent(new Event("change", { bubbles: true }));
		await sleep(50);
		const ok = /Option B/.test(document.querySelector('[data-ref="sel-target"]').textContent);
		mark(card, ok, "Option B cargado");
	});

	await run("selectRedirect (hash)", "No navega, cambia location.hash", async (card) => {
		const sel = document.querySelector('select[data-redirect]');
		sel.value = "x";
		sel.dispatchEvent(new Event("change", { bubbles: true }));
		await sleep(20);
		mark(card, location.hash === "#goto-x", "hash == #goto-x");
	});

	await run("active (cascada)", "Marca aria-current en el pulsado", async (card) => {
		const btns = Array.from(document.querySelectorAll('nav[data-group="tabs"] > button'));
		btns[1].click(); await sleep(10);
		const ok = btns[1].getAttribute("aria-current") === "true" && !btns[0].hasAttribute("aria-current");
		mark(card, ok, "segundo activo");
	});

	await run("alert", "Llama a window.alert", async (card) => {
		lastAlert = null;
		document.querySelector('button[data-alert]').click();
		mark(card, lastAlert === "hola", "alert capturado");
	});

	await run("confirm", "Previene si confirm = false", async (card) => {
		nextConfirm = false;
		const btn = document.querySelector('button[data-confirm]');
		const ev = new MouseEvent("click", { bubbles: true, cancelable: true });
		const dispatched = btn.dispatchEvent(ev); // false si preventDefault()
		nextConfirm = true;
		mark(card, dispatched === false, "evento prevenido");
	});

	await run("clone_append", "Clona y añade al destino", async (card) => {
		document.querySelector('button[data-clone_append]').click();
		await sleep(10);
		mark(card, document.querySelectorAll('section + section [data-mark]').length >= 1, "copia presente");
	});

	await run("effects: show/hide/toggle", "Visibilidad", async (card) => {
		const tgt = document.querySelector('section[style]');
		document.querySelector('button[data-show]').click(); await sleep(10);
		const s1 = getComputedStyle(tgt).display !== "none";
		document.querySelector('button[data-hide]').click(); await sleep(10);
		const s2 = getComputedStyle(tgt).display === "none";
		document.querySelector('button[data-toggle]').click(); await sleep(10);
		const s3 = getComputedStyle(tgt).display !== "none";
		mark(card, s1 && s2 && s3, "ciclos OK");
	});

	await run("effect: fadeOut", "Transición y ocultación", async (card) => {
		const tgt = document.querySelector('section[style]');
		document.querySelector('button[data-fadeOut]').click();
		await sleep(250);
		mark(card, getComputedStyle(tgt).display === "none", "oculto tras fade");
	});

	await run("effect: slideDown", "Despliegue simple", async (card) => {
		const tgt = document.querySelector('section[style*="padding"]');
		document.querySelector('button[data-slideDown]').click();
		await sleep(250);
		mark(card, getComputedStyle(tgt).display !== "none", "desplegado");
	});

	await run("effect: click", "Proxy de click", async (card) => {
		let fired = false;
		const b = document.querySelector('button[data-proxy]');
		b.addEventListener("click", () => fired = true, { once: true });
		document.querySelector('button[data-click]').click();
		await sleep(10);
		mark(card, fired, "click proxied");
	});

	await run("remove", "Elimina por relación o selector", async (card) => {
		document.querySelector('button[data-remove="parent"]').click();
		document.querySelector('button[data-remove="parent parent"]').click();
		document.querySelector('button[data-remove=\'section[data-ref="rmSel"]\']').click();
		const ok = !document.querySelector('button[data-remove="parent"]')
			&& !document.querySelector('button[data-remove="parent parent"]')
			&& !document.querySelector('section[data-ref="rmSel"]');
		mark(card, ok, "3 elementos eliminados");
	});

	await run("style", "Aplica atributo style", async (card) => {
		document.querySelector('button[data-style]').click();
		mark(card, document.querySelector('section[data-ref="sty"]').getAttribute("style")?.includes("color: red"), "style aplicado");
	});

	await run("toggleClass", "Alterna clase en destino", async (card) => {
		const sel = 'section[data-role="tcbox"]';
		document.querySelector(`button[data-toggle_class='active, ${sel}']`).click();
		const on = document.querySelector(sel).classList.contains("active");
		document.querySelector(`button[data-toggle_class='active, ${sel}']`).click();
		const off = !document.querySelector(sel).classList.contains("active");
		mark(card, on && off, "on/off correcto");
	});

	await run("toggleDisplay", "Alterna display (incluye flex via data-display)", async (card) => {
		const cont = document.querySelector('section + button[data-toggle_display]').previousElementSibling;
		const nodes = cont.querySelectorAll('[data-disp]');
		document.querySelector('button[data-toggle_display]').click(); await sleep(10);
		const ok1 = getComputedStyle(nodes[0]).display === "none" && getComputedStyle(nodes[1]).display === "flex";
		document.querySelector('button[data-toggle_display]').click(); await sleep(10);
		const ok2 = getComputedStyle(nodes[0]).display !== "none" && getComputedStyle(nodes[1]).display === "none";
		mark(card, ok1 && ok2, "alternancia OK");
	});

	await run("selectToggle", "Vacía inputs y alterna grupos", async (card) => {
		const sel = document.querySelector('select[data-change_toggle]');
		const before = document.querySelector('section[data-grp="alpha"] input').value;
		sel.value = "alpha";
		sel.dispatchEvent(new Event("change", { bubbles: true }));
		await sleep(10);
		const emptied = document.querySelector('section[data-grp="alpha"] input').value === "";
		mark(card, emptied && before === "preset", "inputs vaciados");
	});

	window.alert = realAlert;
	window.confirm = realConfirm;

	document.querySelector('a[rel="run"]').addEventListener("click", e => { e.preventDefault(); location.reload(); });
})();
