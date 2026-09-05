document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('.sortable-list');
    if (!list) return;

    if (typeof Sortable !== 'undefined') {
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const items = list.querySelectorAll('li[data-selector]');
                const orden = [];
                items.forEach(item => {
                    if (item.dataset.selector) {
                        orden.push(item.dataset.selector);
                    }
                });

                fetch('/ev/plantillas/ordenar_reglas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ orden: orden })
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) console.error('Error al guardar el orden', data);
                })
                .catch(e => console.error('Fetch error:', e));
            }
        });

        // Reordenar propiedades dentro de un selector
        document.querySelectorAll('.reglas-grupo').forEach(group => {
            new Sortable(group, {
                handle: '.prop-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    const items = group.querySelectorAll('.regla-line');
                    const orden = [];
                    items.forEach(item => {
                        if (item.dataset.idu) orden.push(item.dataset.idu);
                    });

                    fetch('/ev/plantillas/ordenar_propiedades', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ orden: orden })
                    })
                    .then(r => r.json())
                    .catch(e => console.error('Error ordenando propiedades:', e));
                }
            });
        });
    }
});

/**
 * Eliminar una propiedad individual desde la lista (×)
 */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-delete-rule');
    if (!btn) return;

    if (!confirm('¿Eliminar esta propiedad?')) return;

    const idu = btn.dataset.idu;
    if (!idu) return;

    const fd = new FormData();
    fd.append('action', 'regla_eliminar');
    fd.append('idu', idu);

    fetch(window.location.href, {
        method: 'POST',
        body: fd
    }).then(r => {
        if (r.ok || r.redirected) {
            // Eliminar la línea del DOM
            const line = btn.closest('.regla-line');
            const article = line ? line.closest('article') : null;
            if (line) line.remove();

            // Si era la última propiedad del grupo, eliminar el <li> entero
            if (article && article.querySelectorAll('.regla-line').length === 0) {
                const li = article.closest('li');
                if (li) li.remove();
            }
        }
    }).catch(e => console.error('Error eliminando:', e));
});
