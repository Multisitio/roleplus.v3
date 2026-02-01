/* 5-data.js - Vanilla JS version – USA 4-masonry.js */

// Ocultar/mostrar UI al scroll
let prevScrollY = window.pageYOffset;
let ticking = false;
function onScrollCompute(y) {
    if (y === 0) {
        showElements('nav, .scroll-down-hide');
        return;
    }
    if (y < prevScrollY) {
        showElements('nav, .scroll-down-hide');
    } else {
        hideElements('aside.left, nav, .scroll-down-hide');
    }
    prevScrollY = y;
}
window.addEventListener('scroll', () => {
    if (!ticking) {
        ticking = true;
        requestAnimationFrame(() => {
            onScrollCompute(window.pageYOffset);
            ticking = false;
        });
    }
});

function showElements(selector) {
    document.querySelectorAll(selector).forEach(el => el.style.display = '');
}
function hideElements(selector) {
    document.querySelectorAll(selector).forEach(el => el.style.display = 'none');
}

// data-change: hide/show based on value
document.body.addEventListener('change', e => {
    const el = e.target;
    const hide = el.getAttribute('data-change');
    if (!hide) return;
    const show = el.value;
    document.querySelectorAll(hide).forEach(n => n.style.display = 'none');
    document.querySelectorAll('.' + show).forEach(n => n.style.display = '');
});

// data-change_load: AJAX load + recalcular masonry
document.body.addEventListener('change', async e => {
    const el = e.target;
    const url = el.value;
    const to = el.getAttribute('data-change_load');
    if (!to || !url) return;

    const container = document.querySelector(to);
    if (!container) return;

    const html = await fetch(url).then(res => res.text());
    container.innerHTML = html;

    if (container.hasAttribute('data-columns') && window.MasonryGrid) {
        requestAnimationFrame(() => window.MasonryGrid.recalculate(container));
    }
});

// data-enviar: AJAX load + recalcular
document.body.addEventListener('click', async e => {
    const el = e.target.closest('[data-enviar]');
    if (!el) return;
    e.preventDefault();
    const url = el.getAttribute('data-enviar');
    const hideAjax = document.querySelector('.ajax.hide');
    if (!hideAjax) return;

    const html = await fetch(url).then(res => res.text());
    hideAjax.innerHTML = html;

    if (hideAjax.hasAttribute('data-columns') && window.MasonryGrid) {
        requestAnimationFrame(() => window.MasonryGrid.recalculate(hideAjax));
    }
});

// data-remove: remove parent or selector
document.body.addEventListener('click', e => {
    const el = e.target.closest('[data-remove]');
    if (!el) return;
    e.preventDefault();
    const to = el.getAttribute('data-remove');
    let target = null;
    if (to === 'parent') target = el.parentElement;
    else target = document.querySelector(to);
    if (target) target.remove();
});

// data-show_pass: toggle password
document.body.addEventListener('click', e => {
    const el = e.target.closest('[data-show_pass]');
    if (!el) return;
    e.preventDefault();
    const inputSel = el.getAttribute('data-show_pass');
    const input = document.querySelector(inputSel);
    if (!input) return;
    const img = el.parentElement.querySelector('[src*="eye"]');
    if (input.type === 'text') {
        input.type = 'password';
        if (img) img.src = '/img/icons/eye-s.svg';
    } else {
        input.type = 'text';
        if (img) img.src = '/img/icons/eye-off-s.svg';
    }
});

// data-add: append text
document.body.addEventListener('click', e => {
    const el = e.target.closest('[data-add]');
    if (!el) return;
    e.preventDefault();
    const t = el.getAttribute('data-add');
    const n = el.parentElement.getAttribute('data-add_to');
    const target = document.querySelector(n);
    if (!target) return;
    target.value += t;
});

// data-toast: load toast
document.body.addEventListener('click', async e => {
    const el = e.target.closest('[data-toast]');
    if (!el) return;
    const toast = el.getAttribute('data-toast');
    const showAjax = document.querySelector('.ajax.show');
    if (!showAjax) return;
    const url = '/index/toast?toast=' + encodeURIComponent(toast);
    const html = await fetch(url).then(res => res.text());
    showAjax.innerHTML = html;
});

// Textarea auto-height
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

// Tab in textarea
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
