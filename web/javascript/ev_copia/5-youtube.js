(() => {
	"use strict";

	function createThumb(id) {
		var wrap = document.createElement("div");
		wrap.setAttribute("data-yt-thumb", id);

		var img = document.createElement("img");
		img.setAttribute("alt", "YouTube thumbnail");
		img.setAttribute("loading", "lazy");
		img.src = "https://i.ytimg.com/vi/" + id + "/hqdefault.jpg";
		wrap.appendChild(img);

		var play = document.createElement("div");
		play.setAttribute("data-yt-play", "");
		wrap.appendChild(play);

		wrap.onclick = function () { replaceWithIframe(wrap, id); };
		return wrap;
	}

	function replaceWithIframe(thumbEl, id) {
		var iframe = document.createElement("iframe");
		iframe.src = "https://www.youtube.com/embed/" + id + "?autoplay=1";
		iframe.setAttribute("title", "YouTube video");
		iframe.setAttribute("frameborder", "0");
		iframe.setAttribute("allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share");
		iframe.setAttribute("allowfullscreen", "1");
		iframe.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
		iframe.style.background = "transparent";
		iframe.style.width = "100%";
		iframe.style.height = "100%";

		var parent = thumbEl.parentNode;
		if (parent) parent.replaceChild(iframe, thumbEl);
	}

	function initNode(node) {
		if (!node || node.getAttribute("data-yt-ready") === "1") return;
		var id = node.getAttribute("data-youtube");
		if (!id) return;
		node.appendChild(createThumb(id));
		node.setAttribute("data-yt-ready", "1");
	}

	function scan(root) {
		var list = (root || document).querySelectorAll("[data-youtube]");
		for (var i = 0; i < list.length; i++) initNode(list[i]);
	}

	function handle(ev) {
		var root = ev && ev.detail && ev.detail.root ? ev.detail.root : document;
		scan(root);
	}

	if (window.rpDom && typeof window.rpDom.on === "function") {
		window.rpDom.on(handle);
	} else {
		document.addEventListener("DOMContentLoaded", function () { handle({ detail: { root: document } }); });
	}
})();
