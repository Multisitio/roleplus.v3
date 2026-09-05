// w = window (Referencia al objeto global del navegador)
// d = document (Referencia al árbol DOM de la página actual)
(function (w, d) {
	'use strict';

	// ==========================================
	// Endpoint genérico temporal para la subida
	var UPLOAD_URL = '/uploader/upload';
	// Calidad WebP (0-100). El backend la limita a un rango seguro.
	var COMPRESSION_LOSS = 82;
	// Límite de tamaño por archivo en Megabytes
	var MAX_FILE_SIZE_MB = 16;
	// Extensiones válidas
	var ALLOWED_EXTENSIONS = ['avif', 'bmp', 'gif', 'heic', 'heif', 'jpeg', 'jpg', 'png', 'svg', 'tif', 'tiff', 'webp'];
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
			p.style.cssText = 'display:none!important;position:fixed!important;z-index:2147483647!important;top:20px!important;left:50%!important;transform:translateX(-50%)!important;width:400px!important;max-width:90vw!important;max-height:60vh!important;overflow:auto!important;background:rgba(0,0,0,.9)!important;color:#fff!important;padding:16px 48px 16px 16px!important;border-radius:8px!important;box-shadow:0 4px 15px rgba(0,0,0,.5)!important;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace!important;box-sizing:border-box!important;';

			var b = d.createElement('button');
			b.innerHTML = '&times;';
			b.style.cssText = 'position:absolute!important;top:8px!important;right:8px!important;width:30px!important;height:30px!important;border:0!important;border-radius:50%!important;background-color:rgba(255,255,255,0.15)!important;color:#fff!important;cursor:pointer!important;line-height:30px!important;font-size:24px!important;font-family:sans-serif!important;padding:0!important;text-align:center!important;display:flex!important;align-items:center!important;justify-content:center!important;margin:0!important;box-shadow:none!important;';
			var self = this;
			b.onclick = function () { self.panel.style.display = 'none'; };

			var m = d.createElement('div');
			m.style.wordBreak = 'break-all';
			p.appendChild(b); p.appendChild(m);
			d.body.appendChild(p);
			this.panel = p; this.panelMsg = m;
			return p;
		},

		showPanel: function (text, isHtml) {
			this.ensurePanel();
			if (isHtml) {
				this.panelMsg.innerHTML = text || '';
			} else {
				this.panelMsg.textContent = text || '';
			}
			this.panel.style.display = 'block';
		},

		copyText: function (txt, cb) {
			if (w.navigator && w.navigator.clipboard && w.navigator.clipboard.writeText) {
				w.navigator.clipboard.writeText(txt).then(function () { cb && cb(true); }, function () { cb && cb(false); });
				return;
			}
			
			var active = d.activeElement;
			var ta = d.createElement('textarea');
			ta.value = txt; ta.style.cssText = 'position:fixed;top:-1000px;left:-1000px';
			d.body.appendChild(ta); ta.focus(); ta.select();

			var ok = false;
			try { ok = d.execCommand('copy'); d.body.removeChild(ta); }
			catch (_) { d.body.removeChild(ta); }
			
			if (active && active.focus) {
				active.focus();
			}

			cb && cb(ok);
		},

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
							if (copied) {
								var msg = '<div style="margin-bottom:8px!important;font-size:14px!important;color:#4CAF50!important;"><b>✔ Imagen guardada (Ruta copiada)</b></div><div style="word-break:break-all!important;font-family:monospace!important;font-size:13px!important;color:#ccc!important;margin-top:8px!important;">' + url + '</div>';
								self.showPanel(msg, true);
							} else {
								var msg = '<div style="margin-bottom:8px!important;font-size:14px!important;"><b>Imagen guardada:</b></div><input type="text" value="'+url+'" style="display:block!important;width:100%!important;background-color:#fff!important;color:#000!important;padding:10px!important;border-radius:4px!important;border:none!important;outline:none!important;font-family:monospace!important;margin-bottom:12px!important;box-sizing:border-box!important;font-size:13px!important;" readonly id="fallback-copy-input"><button id="btn-fallback-copy" style="display:block!important;padding:12px 16px!important;background-color:#4CAF50!important;color:#fff!important;border:none!important;border-radius:4px!important;cursor:pointer!important;font-weight:bold!important;width:100%!important;box-sizing:border-box!important;line-height:normal!important;height:auto!important;font-size:14px!important;margin:0!important;box-shadow:none!important;">Hacer clic aquí para copiar</button>';
								self.showPanel(msg, true);
								setTimeout(function() {
									var inp = d.getElementById('fallback-copy-input');
									var btn = d.getElementById('btn-fallback-copy');
									if (inp) { inp.focus(); inp.select(); }
									if (btn) {
										btn.onclick = function() {
											self.copyText(url, function(c) {
												if (c) {
													btn.textContent = '¡Copiado al portapapeles!';
													btn.style.background = '#2e7d32';
												} else {
													btn.textContent = 'Pulsa Ctrl+C en el cajón de texto';
													btn.style.background = '#d32f2f';
												}
											});
										};
									}
								}, 50);
							}
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
