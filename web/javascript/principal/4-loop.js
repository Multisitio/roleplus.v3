(function() {
	'use strict';

	function toggleFields(el, enabled) {
		var fields = el.querySelectorAll('input,select,textarea');
		var i;

		if (!fields.length && (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA')) {
			fields = [el];
		}

		for (i = 0; i < fields.length; i++) {
			fields[i].disabled = !enabled;
		}
	}

	function handleLoop(btn) {
		var base = btn.getAttribute('data-loop');
		var keepSelector = btn.getAttribute('data-keep');
		var index = parseInt(btn.getAttribute('data-next'), 10);
		var els;
		var target;
		var keep;
		var i;

		if (!base) return;
		if (isNaN(index)) index = 0;

		els = document.querySelectorAll(base);
		if (!els.length) return;

		for (i = 0; i < els.length; i++) {
			els[i].style.display = 'none';
		}

		target = document.querySelector(base + index);
		if (!target) {
			index = 0;
			target = document.querySelector(base + index);
			if (!target) return;
		}

		target.style.display = 'block';

		if (keepSelector) {
			keep = document.querySelector(keepSelector);
			if (keep) keep.value = target.getAttribute('src') || '';
		}

		btn.setAttribute('data-next', String(index + 1));
	}

	function handleLoop2(btn) {
		var selector = btn.getAttribute('data-loop2');
		var nodes;
		var total;
		var current;
		var next;
		var i;
		var el;
		var active;

		if (!selector) return;

		nodes = document.querySelectorAll(selector);
		total = nodes.length;
		if (!total) return;

		current = parseInt(btn.getAttribute('data-current'), 10);
		if (isNaN(current)) current = 0;

		next = (current === total - 1) ? 0 : current + 1;
		btn.setAttribute('data-current', String(next));

		for (i = 0; i < total; i++) {
			el = nodes[i];
			active = (i === next);
			el.style.display = active ? '' : 'none';
			toggleFields(el, active);
		}
	}

	document.addEventListener('click', function(e) {
		var btn = e.target.closest('[data-loop],[data-loop2]');
		if (!btn) return;

		e.preventDefault();

		if (btn.hasAttribute('data-loop')) {
			handleLoop(btn);
		} else {
			handleLoop2(btn);
		}
	});

	document.addEventListener('submit', function(e) {
		var form = e.target;
		var buttons;
		var i;
		var btn;
		var selector;
		var nodes;
		var j;
		var el;
		var visible;

		if (!(form instanceof HTMLFormElement)) return;

		buttons = form.querySelectorAll('[data-loop2]');
		if (!buttons.length) return;

		for (i = 0; i < buttons.length; i++) {
			btn = buttons[i];
			selector = btn.getAttribute('data-loop2');
			if (!selector) continue;

			nodes = form.querySelectorAll(selector);
			for (j = 0; j < nodes.length; j++) {
				el = nodes[j];
				visible = window.getComputedStyle(el).display !== 'none';
				toggleFields(el, visible);
			}
		}
	});
})();
