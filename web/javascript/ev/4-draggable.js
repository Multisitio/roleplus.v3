/* 4-draggable.js – Drag & Drop con guardado POST + rejilla ondrag y pegamento */

/* === CONFIGURACIÓN (ajusta aquí) === */
const DRAG_GRID_PX = 10;	// tamaño de la rejilla
const GLUE_THRESHOLD_PX = 15;	// distancia para pegar a bordes de vecinos
/* =================================== */

(function () {
	"use strict";

	var rpDragTopZ = 1000;
	var scanned = new WeakSet();

	function log(){ try{ console.info.apply(console, arguments); }catch(e){} }
	function toInt(n){ return n|0; }

	function data(el, key){
		var ds = el.dataset || {};
		if (key in ds) return ds[key];
		var s = key.replace(/[A-Z]/g, function(m){ return "_"+m.toLowerCase(); });
		if (s in ds) return ds[s];
		var d = key.replace(/[A-Z]/g, function(m){ return "-"+m.toLowerCase(); });
		var v = el.getAttribute("data-"+d);
		if (v==null) v = el.getAttribute("data-"+s);
		if (v==null) v = el.getAttribute("data-"+key);
		return v;
	}

	function savePos(box){
		var idu = data(box,"id") || box.getAttribute("data-id");
		if (!idu){ log("[drag] savePos: falta data-id"); return; }
		var x = toInt(box.offsetLeft), y = toInt(box.offsetTop);
		var url = "/ev/panel/posicion_partida/"+encodeURIComponent(idu);
		var fd = new FormData(); fd.append("x",x); fd.append("y",y);
		log("[drag] savePos POST ⇒", url, {x:x,y:y});
		fetch(url,{
			method:"POST",
			cache:"no-store",
			credentials:"same-origin",
			headers:{ "X-Requested-With":"XMLHttpRequest" },
			body:fd
		})
		.then(function(r){ log("[drag] savePos status:", r.status); })
		.catch(function(e){ log("[drag] savePos error:", String(e && e.message ? e.message : e)); });
	}

	function snapGrid(v, step){ var s=step||DRAG_GRID_PX; return toInt(Math.round(v/s)*s); }

	function others(target){
		var all = document.querySelectorAll("[data-draggable]");
		var out = [];
		for (var i=0;i<all.length;i++) if (all[i]!==target) out.push(all[i]);
		return out;
	}

	function snapToNeighbors(box, left, top){
		var thr = GLUE_THRESHOLD_PX;
		var w = box.offsetWidth, h = box.offsetHeight;
		var L = left, T = top, R = L + w, B = T + h;
		var hits = [];

		var list = others(box);
		for (var i=0;i<list.length;i++){
			var o = list[i];
			var ol = o.offsetLeft, ot = o.offsetTop, ow = o.offsetWidth, oh = o.offsetHeight;
			var orr = ol + ow, obb = ot + oh;

			if (Math.abs(L - ol) <= thr){ L = ol; hits.push("L=OL"); }
			if (Math.abs(L - orr) <= thr){ L = orr; hits.push("L=OR"); }
			if (Math.abs(R - ol) <= thr){ L = ol - w; hits.push("R=OL"); }
			if (Math.abs(R - orr) <= thr){ L = orr - w; hits.push("R=OR"); }

			if (Math.abs(T - ot) <= thr){ T = ot; hits.push("T=OT"); }
			if (Math.abs(T - obb) <= thr){ T = obb; hits.push("T=OB"); }
			if (Math.abs(B - ot) <= thr){ T = ot - h; hits.push("B=OT"); }
			if (Math.abs(B - obb) <= thr){ T = obb - h; hits.push("B=OB"); }

			R = L + w; B = T + h;
		}
		var res = { left: toInt(L), top: toInt(T), hits: hits.length, tags: hits.slice(0,6) };
		/*log("[drag] glue ⇒", res);*/
		return res;
	}

	function makeDraggable(box){
		if (!box || scanned.has(box)) return;
		scanned.add(box);

		if (box.style.position !== "absolute") box.style.position = "absolute";

		box.addEventListener("mousedown", function(){ rpDragTopZ++; box.style.zIndex = rpDragTopZ; });
		box.addEventListener("click",    function(){ rpDragTopZ++; box.style.zIndex = rpDragTopZ; });

		var header = box.querySelector("header");
		if (!header) return;

		header.style.cursor = "grab";

		var sx, sy, ix, iy, moved = 0;

		function onMove(e){
			var dx = e.clientX - sx;
			var dy = e.clientY - sy;

			var gL = snapGrid(ix + dx, DRAG_GRID_PX);
			var gT = snapGrid(iy + dy, DRAG_GRID_PX);

			var glued = snapToNeighbors(box, gL, gT);

			box.style.left = glued.left + "px";
			box.style.top  = glued.top  + "px";
			moved = 1;
		}

		function onEnd(){
			document.removeEventListener("mousemove", onMove);
			document.removeEventListener("mouseup", onEnd);
			box.removeAttribute("data-dragging");

			if (!moved){ log("[drag] end (sin movimiento) id=", data(box,"id")||box.getAttribute("data-id")); return; }

			var before = { left: toInt(box.offsetLeft), top: toInt(box.offsetTop) };
			var gL = snapGrid(before.left, DRAG_GRID_PX);
			var gT = snapGrid(before.top, DRAG_GRID_PX);
			var glued = snapToNeighbors(box, gL, gT);

			box.style.left = glued.left + "px";
			box.style.top  = glued.top  + "px";

			log("[drag] end ⇒", { before: before, grid:{L:gL,T:gT}, applied:{L:glued.left,T:glued.top} });
			savePos(box);
			moved = 0;
		}

		header.addEventListener("mousedown", function(e){
			var control = (e.target && e.target.closest) ? e.target.closest("a,button,[data-action]") : null;
			if (control) return;

			e.preventDefault();
			sx = e.clientX; sy = e.clientY; ix = box.offsetLeft; iy = box.offsetTop;
			box.setAttribute("data-dragging","1");
			log("[drag] start ⇒", { id: data(box,"id")||box.getAttribute("data-id"), startX:sx, startY:sy, initX:ix, initY:iy });
			document.addEventListener("mousemove", onMove);
			document.addEventListener("mouseup", onEnd);
		});
	}

	function init(root){
		var scope = root || document;
		var items = scope.querySelectorAll("[data-draggable]");
		for (var i=0;i<items.length;i++) makeDraggable(items[i]);
		log("[drag] init ok. encontrados:", items.length);
	}

	var obs = new MutationObserver(function(muts){
		for (var i=0;i<muts.length;i++){
			var m = muts[i];
			if (m.type!=="childList" || !m.addedNodes) continue;
			for (var j=0;j<m.addedNodes.length;j++){
				var n = m.addedNodes[j];
				if (!n || n.nodeType!==1) continue;
				if (n.matches && n.matches("[data-draggable]")) init(n);
				var inner = n.querySelectorAll ? n.querySelectorAll("[data-draggable]") : null;
				if (inner && inner.length) init(n);
			}
		}
	});

	try{ obs.observe(document.body,{childList:true,subtree:true}); }catch(e){}

	document.addEventListener("DOMContentLoaded", function(){ init(document); });
	document.addEventListener("kumbia:inject", function(ev){
		var into = (ev && ev.detail && ev.detail.into) ? ev.detail.into : document;
		init(into);
	});

	window.initDraggables = init;
})();
