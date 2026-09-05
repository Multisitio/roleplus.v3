/* 0-dom-bus.js - Vanilla JS (ya era vanilla) */

(() => {
	"use strict";

	var BUS_EVT = "rp:dom:changed";

	function emit(root) {
		try {
			document.dispatchEvent(new CustomEvent(BUS_EVT, { detail: { root: root || document } }));
		} catch (_) {}
	}

	function on(handler) {
		if (typeof handler !== "function") return;
		try { handler({ detail: { root: document } }); } catch (_) {}
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
	} catch (_) {}

	try {
		window.rpDom = Object.freeze({
			emit: emit,
			on: on
		});
	} catch (_) {}
})();
