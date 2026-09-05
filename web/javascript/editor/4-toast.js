/**
 * Sistema de Toasts para el Editor
 * Basado en el partial shared/toast.phtml
 */

(function () {
    "use strict";

    /**
     * Elimina un toast de forma suave
     */
    function removeToast(el) {
        if (!el || el.classList.contains('removing')) return;

        // Obtenemos la altura actual para que la transición de max-height funcione
        el.style.maxHeight = el.offsetHeight + 'px';

        // Forzamos un reflow
        el.offsetHeight;

        el.classList.add('removing');

        setTimeout(() => {
            el.remove();
        }, 600); // Un poco más que la transición CSS
    }

    /**
     * Configura el temporizador de auto-remoción para un elemento
     */
    function setupTimeout(el) {
        const seconds = parseInt(el.dataset.timeout);
        if (seconds > 0) {
            setTimeout(() => {
                removeToast(el);
            }, seconds * 1000);
        }
    }

    // Delegación para cerrar toasts manualmente
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.toast-container [data-remove="parent"]');
        if (btn) {
            const toast = btn.closest('.toast-container > div');
            if (toast) removeToast(toast);
        }
    });

    /**
     * Inyecta HTML de toasts en la página
     */
    window.spawnToasts = function (html) {
        if (!html) return;

        let container = document.querySelector('.toast-container');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const newToasts = Array.from(wrapper.querySelectorAll('.toast-container > div, [data-timeout]'));

        if (!container && newToasts.length > 0) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        newToasts.forEach(toast => {
            container.appendChild(toast);
            setupTimeout(toast);
        });
    };

    /**
     * Helper para lanzar toasts con el mismo formato que el partial PHP
     */
    window.editorToast = function (message, type = 'info') {
        const title = (type === 'error') ? 'ERROR' : 'INFO';
        const html = '<div class="toast-container"><div data-timeout="8"> ' +
            '<button type="button" class="transparent" data-remove="parent"><img src="/img/icons/x.svg"></button> ' +
            '<h3>' + title + '</h3><hr><p>' + message + '</p></div></div>';
        window.spawnToasts(html);
    };

    // Al cargar, inicializamos los toasts que ya existan en el DOM (si los hay)
    document.querySelectorAll('.toast-container [data-timeout]').forEach(setupTimeout);

})();
