/**
 * Sistema de Toasts para Principal
 * Basado en shared/toast.phtml y editor/4-toast.js
 */

(function () {
    'use strict';

    // ── Configuración ────────────────────────────────────────────
    var TIMEOUT_S      = 8;   // segundos visibles antes de desaparecer
    var ANIMACION_MS   = 600; // duración de la animación de salida (ms)
    // ─────────────────────────────────────────────────────────────

    // Evitar doble inicialización
    var initialized = (typeof WeakSet !== 'undefined') ? new WeakSet() : null;

    function removeToast(el) {
        if (!el || el.classList.contains('removing')) return;
        el.style.maxHeight = el.offsetHeight + 'px';
        el.offsetHeight; // reflow
        el.classList.add('removing');
        setTimeout(function () { el.remove(); }, ANIMACION_MS);
    }

    function setupTimeout(el) {
        if (initialized) {
            if (initialized.has(el)) return;
            initialized.add(el);
        }
        var seconds = parseInt(el.dataset.timeout) || TIMEOUT_S;
        setTimeout(function () { removeToast(el); }, seconds * 1000);
    }

    // Cerrar manualmente
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.toast-container [data-remove="parent"]');
        if (btn) {
            var toast = btn.closest('.toast-container > div');
            if (toast) removeToast(toast);
        }
    });

    // Observar toasts inyectados por AJAX (subtree cubre cualquier nivel)
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                // El nodo puede ser directamente un toast-item
                if (node.dataset && node.dataset.timeout) {
                    setupTimeout(node);
                }
                // O contener toasts dentro (p.ej. .toast-container)
                if (node.querySelectorAll) {
                    node.querySelectorAll('[data-timeout]').forEach(setupTimeout);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Inicializar los que ya existen al cargar (carga normal o recarga)
    document.querySelectorAll('.toast-container [data-timeout]').forEach(setupTimeout);

})();
