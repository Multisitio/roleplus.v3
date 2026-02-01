/* 9-ev.js - Vanilla JS version – SIN DEBUG, SIN OPTIONAL CHAINING */

// Resize hook (opcional)
window.addEventListener('resize', () => {});

// Scroll hide UI
let prevScrollpos = window.pageYOffset;
let stopScrollAnim = 0;
window.addEventListener('scroll', () => {
	const y = window.pageYOffset;
	if (y === 0) {
		showElements('nav, .scroll-down-hide');
		return;
	}
	if (stopScrollAnim !== 0) return;
	stopScrollAnim = 1;
	if (prevScrollpos > y) {
		showElements('nav, .scroll-down-hide');
	} else {
		hideElements('aside.left, nav, .scroll-down-hide');
	}
	stopScrollAnim = 0;
	prevScrollpos = y;
});

// Share / copiar (captura + corta propagacion)
document.body.addEventListener('click', e => {
	const el = e.target.closest('.share');
	if (!el) return;

	e.preventDefault();
	e.stopPropagation();
	e.stopImmediatePropagation();

	const url = el.href;
	if (navigator.share) {
		navigator.share({ title: '', text: '', url });
	} else {
		copiarAlPortapapeles(url);
	}
}, true);

// Apartados click
document.body.addEventListener('click', e => {
	const el = e.target.closest('.apartados a');
	if (!el) return;
	const destino = el.href;
	if (destino === '#tapiz') {
		hideElements('.post-it, .jitsi');
	} else if (destino === '#notas') {
		showElements('.post-it');
		hideElements('.jitsi');
	} else if (destino === '#jitsi') {
		showElements('.jitsi');
		hideElements('.post-it');
	}
});

// Textarea auto-height + tab
function textareaAutoHeight() {
	document.querySelectorAll('textarea').forEach(el => {
		let height = el.scrollTop + el.scrollHeight;
		if (height < 99) height = 99;
		el.style.height = height + 'px';
	});
}
textareaAutoHeight();
document.body.addEventListener('click', e => {
	if (e.target.tagName === 'TEXTAREA') textareaAutoHeight();
});
document.body.addEventListener('keyup', e => {
	if (e.target.tagName === 'TEXTAREA') textareaAutoHeight();
});
document.body.addEventListener('keydown', e => {
	if (e.target.tagName === 'TEXTAREA' && e.keyCode === 9) {
		const v = e.target.value;
		const s = e.target.selectionStart;
		const eEnd = e.target.selectionEnd;
		e.target.value = v.substring(0, s) + '\t' + v.substring(eEnd);
		e.target.selectionStart = e.target.selectionEnd = s + 1;
		e.preventDefault();
	}
});

// Copiar al portapapeles (fallback clasico)
function copiarAlPortapapeles(link) {
	var aux = document.createElement('input');
	aux.setAttribute('type', 'text');
	aux.setAttribute('value', link);
	aux.style.position = 'fixed';
	aux.style.top = '0';
	aux.style.left = '-9999px';
	aux.style.opacity = '0';
	document.body.appendChild(aux);
	aux.focus();
	aux.select();
	try { document.execCommand('copy'); } catch (_) { }
	document.body.removeChild(aux);
}

// Funciones auxiliares (show/hide)
function showElements(selector) {
	document.querySelectorAll(selector).forEach(el => el.style.display = '');
}
function hideElements(selector) {
	document.querySelectorAll(selector).forEach(el => el.style.display = 'none');
}
