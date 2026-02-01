(function (factory) {
	/* [ELIMINABLE] Boot/Polyfill de arranque hasta que cargue jQuery (depende de: window.jQuery y de factory($)) */
	function boot() {
		if (window.jQuery) { factory(window.jQuery); return true; }
		return false;
	}
	if (!boot()) {
        /* [ELIMINABLE] Reintentos de boot por intervalo + DOMContentLoaded (depende de: función boot() anterior) */
		var tries = 0, iv = setInterval(function () { if (boot() || ++tries > 200) clearInterval(iv); }, 50);
		window.addEventListener('DOMContentLoaded', boot, { once: true });
	}
})(function ($) {
	'use strict';

	/* [ELIMINABLE] Consola silenciosa con flag DEBUG (depende de: console.debug disponible) */
	const DEBUG = false;
	function dlog() { if (DEBUG) console.debug.apply(console, arguments); }

	/* [ELIMINABLE] Masonry/Salvattore responsive (depende de: #grid[data-columns] en HTML y /javascript/salvattore.min.js accesible) */
	function injectSalvattoreOnce() {
		if (window.__salvattoreInjected) return;
		window.__salvattoreInjected = true;
		const s = document.createElement('script');
		s.type = 'text/javascript';
		s.src = '/javascript/salvattore.min.js';
		document.body.appendChild(s);
	}

	/* [ELIMINABLE] Estilo para desactivar pseudo-columna cuando no hay 2 columnas (sin dependencias externas) */
	function ensureDisableStyle() {
		let st = document.getElementById('masonry-disable-style');
		if (!st) {
			st = document.createElement('style');
			st.id = 'masonry-disable-style';
			st.textContent = '#grid[data-columns]::before{content:none}';
			document.head.appendChild(st);
		}
	}

	/* [ELIMINABLE] Quitar estilo de desactivación de masonry (depende de: ensureDisableStyle haya podido crearlo) */
	function removeDisableStyle() {
		const st = document.getElementById('masonry-disable-style');
		if (st) st.remove();
	}

	/* [ELIMINABLE] Evaluación de breakpoint y carga de Salvattore (depende de: funciones ensure/remove/inject de este bloque) */
	function evaluateMasonry() {
		if (window.innerWidth > 972) {
			removeDisableStyle();
			injectSalvattoreOnce();
		} else {
			ensureDisableStyle();
		}
	}

	/* [ELIMINABLE] Debounce de resize para evaluateMasonry (depende de: evaluateMasonry) */
	let resizeTimer = null;
	window.addEventListener('resize', function () {
		if (resizeTimer) clearTimeout(resizeTimer);
		resizeTimer = setTimeout(evaluateMasonry, 180);
	});

	/* [ELIMINABLE] Primera evaluación de masonry (depende de: evaluateMasonry) */
	evaluateMasonry();

	/* [ELIMINABLE] Ocultar/mostrar UI al hacer scroll con rAF (depende de: jQuery .fadeIn/.fadeOut y selectores nav/.scroll-down-hide/aside.left) */
	let prevScrollY = window.pageYOffset;
	let ticking = false;
	function onScrollCompute(y) {
		if (y === 0) {
			$('nav, .scroll-down-hide').stop(true, true).fadeIn();
			return;
		}
		if (y < prevScrollY) {
			$('nav, .scroll-down-hide').stop(true, true).fadeIn();
		} else {
			$('aside.left, nav, .scroll-down-hide').stop(true, true).fadeOut();
		}
		prevScrollY = y;
	}
	/* [ELIMINABLE] Listener de scroll con throttle rAF (depende de: onScrollCompute) */
	window.addEventListener('scroll', function () {
		if (!ticking) {
			ticking = true;
			requestAnimationFrame(function () {
				onScrollCompute(window.pageYOffset);
				ticking = false;
			});
		}
	});

	/* [ELIMINABLE] data-change: oculta selector indicado en data-change y muestra clase igual al value (depende de: jQuery y marcado data-*) */
	$('body').on('change', '[data-change]', function () {
		var hide = $(this).data('change');
		var show = $(this).val();
		$(hide).hide();
		$('.' + show).show();
	});

	/* [ELIMINABLE] data-change_load: carga vía AJAX el value en el destino (depende de: jQuery .load y marcado data-*) */
	$('body').on('change', '[data-change_load]', function (eve) {
		eve.preventDefault();
		var to = $(this).data('change_load');
		var url = $(this).val();
		$(to).load(url);
	});

	/* [ELIMINABLE] data-enviar: carga AJAX en .ajax.hide desde la URL del data-enviar (depende de: jQuery .load y .ajax.hide existente) */
	$('body').on('click', '[data-enviar]', function () {
		var url = $(this).data('enviar');
		$('.ajax.hide').load(url);
	});

	/* [ELIMINABLE] data-remove: elimina parent o selector indicado (sin dependencias externas; usa jQuery si está) */
	$('body').on('click', '[data-remove]', function (eve) {
		eve.preventDefault();
		var to = $(this).data('remove');
		dlog('[data-remove] ->', to);
		if (to === 'parent') {
			$(this).parent().remove();
		} else {
			$(to).remove();
		}
	});

	/* [ELIMINABLE] data-show_pass: alterna type password/text y cambia icono (depende de: input destino y img con src*="eye") */
	$('body').on('click', '[data-show_pass]', function (eve) {
		eve.preventDefault();
		var input_password = $(this).data('show_pass');
		var img = $(this).parent().find('[src*="eye"]');
		if ($(input_password).attr('type') === 'text') {
			$(img).attr('src', '/img/icons/eye-s.svg');
			$(input_password).attr('type', 'password');
		} else {
			$(img).attr('src', '/img/icons/eye-off-s.svg');
			$(input_password).attr('type', 'text');
		}
	});

	/* [ELIMINABLE] data-add: concatena texto en el control destino data-add_to (depende de: contenedor con data-add_to apuntando al selector) */
	$('body').on('click', '[data-add]', function (e) {
		e.preventDefault();
		var t = $(this).data('add');
		var n = $(this).parent().data('add_to');
		var r = $(n).val();
		dlog('[data-add]', { append: t, to: n, current: r });
		$(n).val(r + t);
	});

	/* (NO ELIMINABLE) .share: usa Web Share API o copiarAlPortapapeles(url) (depende de: función copiarAlPortapapeles si no hay navigator.share) */
	$('body').on('click', '.share', function (eve) {
		eve.preventDefault();
		var url = $(this).attr('href');
		if (navigator.share) {
			navigator.share({ title: '', text: '', url: url });
		} else {
			copiarAlPortapapeles(url);
		}
	});

	/* [ELIMINABLE] data-toast: carga toasts en .ajax.show (depende de: endpoint /index/toast y .ajax.show existente) */
	$('body').on('click', '[data-toast]', function () {
		var toast = $(this).data('toast');
		$('.ajax.show').load('/index/toast', { 'toast': toast });
	});

	/* [ELIMINABLE] Textarea auto-altura según contenido (sin dependencias externas) */
	function textarea_auto_height() {
		var els = document.querySelectorAll('textarea');
		els.forEach(function (el) {
			var height = el.scrollTop + el.scrollHeight;
			height = (height < 99) ? 99 : height;
			el.style.height = height + 'px';
		});
	}

	/* [ELIMINABLE] Primera ejecución de auto-altura (depende de: textarea_auto_height) */
	textarea_auto_height();

	/* [ELIMINABLE] Reajuste de altura en click/keyup (depende de: textarea_auto_height) */
	$('body').on('click keyup', 'textarea', function () {
		textarea_auto_height();
	});

	/* [ELIMINABLE] Insertar tabulación literal en textarea con tecla TAB (sin dependencias externas) */
	$('body').on('keydown', 'textarea', function (event) {
		if (event.keyCode === 9) {
			var v = this.value;
			var s = this.selectionStart;
			var e = this.selectionEnd;
			this.value = v.substring(0, s) + '\t' + v.substring(e);
			this.selectionStart = this.selectionEnd = s + 1;
			return false;
		}
	});
});
