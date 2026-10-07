(function () {
    'use strict';

    var requestHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    document.addEventListener('toggle', async function (event) {
        var panel = event.target;
        if (!panel.matches('details.template-component-panel') || !panel.open) return;
        var content = panel.querySelector('.template-component-content');
        var status = panel.querySelector('.template-component-status');
        if (!content || content.dataset.loaded || content.dataset.loading) return;
        content.dataset.loading = 'true';
        status.textContent = 'Cargando reglas…';
        try {
            var response = await fetch(content.dataset.componentesUrl, { headers: requestHeaders });
            if (!response.ok) throw new Error('No se han podido cargar las reglas de la plantilla.');
            var html = await response.text();
            if (/<(?:html|head|body)\b/i.test(html)) throw new Error('La sesión ha caducado. Guarda tu trabajo y vuelve a identificarte.');
            content.innerHTML = html;
            content.dataset.loaded = 'true';
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            delete content.dataset.loading;
        }
    }, true);

    document.addEventListener('submit', async function (event) {
        var form = event.target;
        if (!form.matches('form[data-componentes-form]')) return;
        event.preventDefault();
        var buttons = form.querySelectorAll('button[type="submit"]');
        var deleting = event.submitter && event.submitter.value === 'eliminar';
        var status = form.closest('.template-component-panel').querySelector('.template-component-status');
        if (Array.from(buttons).some(function (button) { return button.disabled; })) return;
        if (deleting && !window.confirm('¿Eliminar esta regla de la plantilla?')) return;
        buttons.forEach(function (button) { button.disabled = true; });
        status.textContent = deleting ? 'Eliminando regla…' : 'Guardando regla…';
        try {
            var data = new FormData(form);
            data.set('operacion', deleting ? 'eliminar' : 'guardar');
            var response = await fetch(form.action, { method: 'POST', headers: requestHeaders, body: data });
            if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('No se ha podido guardar la regla. Comprueba tu sesión.');
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'No se ha podido guardar la regla.');
            var stylesheet = document.getElementById('estilos-personalizados');
            if (stylesheet && result.css_url) stylesheet.href = result.css_url;
            if (deleting) {
                var group = form.closest('details');
                form.remove();
                if (group && !group.querySelector('form[data-componentes-form]')) group.remove();
            }
            status.textContent = deleting ? 'Regla eliminada.' : 'Regla guardada.';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            buttons.forEach(function (button) { button.disabled = false; });
        }
    });
}());
