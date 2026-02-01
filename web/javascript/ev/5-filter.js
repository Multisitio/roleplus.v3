/* 5-filter.js - Vanilla JS version */

function replaceAccents(q) {
    q = q.replace(/[eéèêëEÉÈÊË]/gi, '[eéèêëEÉÈÊË]');
    q = q.replace(/[aàâäAÀÁÂÃÄÅÆ]/gi, '[aàâäAÀÁÂÃÄÅÆ]');
    q = q.replace(/[cçC]/gi, '[cçC]');
    q = q.replace(/[iïîIÌÍÎÏ]/gi, '[iïîIÌÍÎÏ]');
    q = q.replace(/[oôöÒÓÔÕÖ]/gi, '[oôöÒÓÔÕÖ]');
    q = q.replace(/[uüûUÜÛÙÚ]/gi, '[uüûUÜÛÙÚ]');
    q = q.replace(/[yYÿÝ]/gi, '[yYÿÝ]');
    return q;
}

document.body.addEventListener('keyup', e => {
    const el = e.target;
    const item = el.getAttribute('data-filter');
    if (!item) return;
    const search = replaceAccents(el.value).toUpperCase();
    document.querySelectorAll(item).forEach(n => {
        const text = replaceAccents(n.textContent).toUpperCase();
        n.style.display = text.includes(search) ? '' : 'none';
    });
});
