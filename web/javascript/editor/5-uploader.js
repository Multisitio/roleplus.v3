
(function (w, d) {
	'use strict';

	var UPLOAD_URL = '/uploader/upload';
	var DZ = d.body;

	function L() { try { console.log.apply(console, arguments); } catch (_) { } }
	function E() { try { console.error.apply(console, arguments); } catch (_) { } }

	var IMG_EXT = /\.(png|jpe?g|webp|gif|bmp|svg|ico|avif)$/i;

	function isImgFile(f) {
		if (!f) return false;
		if (/^image\//i.test(f.type || '')) return true;
		return IMG_EXT.test((f.name || '') + '');
	}
	function isFileDrag(e) {
		var dt = e.dataTransfer;
		if (!dt) return false;
		if (dt.types && typeof dt.types.indexOf === 'function' && dt.types.indexOf('Files') !== -1) return true;
		if (dt.items && dt.items.length) for (var i = 0; i < dt.items.length; i++) if (dt.items[i].kind === 'file') return true;
		return false;
	}
	function filesFrom(dt) {
		var out = [], i, f;
		if (dt.files && dt.files.length) { for (i = 0; i < dt.files.length; i++) out.push(dt.files[i]); }
		else if (dt.items && dt.items.length) { for (i = 0; i < dt.items.length; i++) { var it = dt.items[i]; if (it.kind === 'file') { f = it.getAsFile && it.getAsFile(); if (f) out.push(f); } } }
		return out;
	}

	var panel = null, panelMsg = null;
	function ensurePanel() {
		if (panel) return panel;
		var p = d.createElement('div');
		p.id = 'uploader-debug';
		p.style.cssText = 'display:none;position:fixed;z-index:2147483647;top:12px;left:12px;width:380px;max-height:42vh;overflow:auto;background:rgba(0,0,0,.85);color:#fff;padding:18px 12px 12px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.4);font:12px/1.4 ui-monospace,Menlo,Consolas,monospace';
		var b = d.createElement('button');
		b.textContent = '×';
		b.style.cssText = 'position:absolute;top:6px;right:6px;width:22px;height:22px;border:0;border-radius:50%;background:#444;color:#fff;cursor:pointer;line-height:22px;font:14px/22px ui-sans-serif,system-ui;padding:0';
		b.onclick = function () { p.style.display = 'none'; p.dataset.closed = '1'; };
		var m = d.createElement('div'); m.id = 'uploader-debug-msg';
		p.appendChild(b); p.appendChild(m);
		d.body.appendChild(p);
		panel = p; panelMsg = m;
		return p;
	}
	function showPanel(text) {
		var p = ensurePanel();
		panelMsg.textContent = text || '';
		p.style.display = 'block';
		p.dataset.closed = '0';
	}
	function copyText(txt, cb) {
		var nav = w.navigator;
		if (nav && nav.clipboard && nav.clipboard.writeText) {
			nav.clipboard.writeText(txt).then(function () { cb && cb(true); }, function () { cb && cb(false); });
			return;
		}
		var ta = d.createElement('textarea');
		ta.value = txt; ta.style.cssText = 'position:fixed;top:-1000px;left:-1000px';
		d.body.appendChild(ta); ta.focus(); ta.select();
		try { var ok = d.execCommand('copy'); d.body.removeChild(ta); cb && cb(!!ok); }
		catch (_) { d.body.removeChild(ta); cb && cb(false); }
	}

	function upload(file) {
		L('[Uploader] upload →', UPLOAD_URL, { name: file.name, type: file.type, size: file.size });
		var fd = new FormData(); fd.append('file', file, file.name || 'image');
		var x = new XMLHttpRequest();
		x.open('POST', UPLOAD_URL, true);
		x.withCredentials = true;
		x.upload.onprogress = function (e) { if (e && e.lengthComputable) L('[Uploader] progress', Math.round(e.loaded * 100 / e.total) + '%', e.loaded + '/' + e.total); };
		x.onreadystatechange = function () {
			if (x.readyState === 4) {
				var body; try { body = JSON.parse(x.responseText); } catch (_) { body = { raw: x.responseText }; }
				var ok = x.status >= 200 && x.status < 300 && !body.error;
				L('[Uploader] done', { status: x.status, ok: ok, body: body });
				if (ok) {
					var url = body.url || '';
					copyText(url, function (copied) {
						var msg = 'Imagen guardada: ' + url + ' (ruta copiada).';
						if (!copied) msg = 'Imagen guardada: ' + url + ' (no se pudo copiar).';
						showPanel(msg);
					});
					d.dispatchEvent(new CustomEvent('image-uploaded', { detail: { status: x.status, ok: true, body: body, url: url, file: file } }));
				} else {
					showPanel('Error: ' + (body.error || body.raw || ('HTTP ' + x.status)));
					d.dispatchEvent(new CustomEvent('image-upload-error', { detail: { status: x.status, ok: false, body: body, file: file } }));
				}
			}
		};
		x.onerror = function () { E('[Uploader] network error'); showPanel('Error: red'); };
		x.send(fd);
	}

	var lastOver = 0, LOG_MS = 150;
	function onDragEnter(e) {
		if (!isFileDrag(e)) return;
		L('[Uploader] dragenter');
		e.preventDefault();
		e.dataTransfer.dropEffect = 'copy';
	}
	function onDragOver(e) {
		if (!isFileDrag(e)) return;
		var now = Date.now(); if (now - lastOver > LOG_MS) { lastOver = now; L('[Uploader] dragover'); }
		e.preventDefault();
		e.dataTransfer.dropEffect = 'copy';
	}
	function onDrop(e) {
		if (!isFileDrag(e)) return;
		var fs = filesFrom(e.dataTransfer), imgs = [], i;
		for (i = 0; i < fs.length; i++) if (isImgFile(fs[i])) imgs.push(fs[i]);
		L('[Uploader] drop files', fs.map(function (f) { return { name: f.name, type: f.type, size: f.size }; }));
		e.preventDefault();
		if (!imgs.length) { L('[Uploader] drop: no images; ignoring'); return; }
		for (i = 0; i < imgs.length; i++) upload(imgs[i]);
	}
	function onPaste(e) {
		var it = e.clipboardData && e.clipboardData.items ? e.clipboardData.items : [];
		var imgs = [], i, f;
		for (i = 0; i < it.length; i++) { var x = it[i]; if (x.kind === 'file') { f = x.getAsFile && x.getAsFile(); if (isImgFile(f)) imgs.push(f); } }
		L('[Uploader] paste images', imgs.length);
		for (i = 0; i < imgs.length; i++) upload(imgs[i]);
	}

	function bind() {
		L('[Uploader] binding on <body>');
		DZ.addEventListener('dragenter', onDragEnter, true);
		DZ.addEventListener('dragover', onDragOver, true);
		DZ.addEventListener('drop', onDrop, true);
		w.addEventListener('paste', onPaste);
		L('[Uploader] ready');
	}

	L('[Uploader] load');
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', bind); else bind();

	w.ImageDropUploader = { upload: upload, config: { uploadUrl: UPLOAD_URL } };
})(window, document);
