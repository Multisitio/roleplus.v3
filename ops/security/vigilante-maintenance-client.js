/* Aviso sobre la pagina abierta. Estado estatico: sin PHP, cookies ni SQL. */
(() => {
    'use strict';
    if (window.__vigilanteMaintenanceClient) return;
    window.__vigilanteMaintenanceClient = true;
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = '/css/vigilante-maintenance-client.css?v=20261020a';
    document.head.append(css);
    let notice, estimate, timer, ticker, request, savedFocus, selection;
    let active = false;
    let supported = true;
    let deadline = 0;
    let estimateDeadline = 0;
    const host = location.hostname.toLowerCase().replace(/\.$/, '');
    const matches = pattern => typeof pattern === 'string' &&
        (host === pattern || (pattern.startsWith('*.') && host.endsWith(pattern.slice(1))
            && host.length > pattern.length - 1));

    function hide() {
        if (!active) return;
        active = false;
        document.documentElement.classList.remove('vigilante-maintenance-open');
        clearInterval(ticker);
        notice.close();
        document.dispatchEvent(new CustomEvent('vigilante:maintenance', { detail: { active: false } }));
        if (savedFocus && savedFocus.isConnected) {
            savedFocus.focus({ preventScroll: true });
            if (selection && typeof savedFocus.setSelectionRange === 'function') {
                try { savedFocus.setSelectionRange(...selection); } catch (_) { /* No todos los campos lo admiten. */ }
            }
        }
    }

    function tick() {
        if (performance.now() >= deadline) { hide(); return; }
        const seconds = Math.max(0, Math.ceil((estimateDeadline - performance.now()) / 1000));
        estimate.textContent = seconds ? `${seconds} segundos` : 'finalizando';
    }

    function show() {
        if (active) { tick(); return; }
        if (!notice) {
            notice = document.createElement('dialog');
            notice.id = 'vigilante-maintenance-notice';
            notice.setAttribute('aria-labelledby', 'vigilante-maintenance-title');
            notice.setAttribute('aria-describedby', 'vigilante-maintenance-description');
            const title = document.createElement('h2');
            title.id = 'vigilante-maintenance-title';
            title.textContent = 'Mantenimiento · Modo de solo lectura';
            const description = document.createElement('p');
            description.id = 'vigilante-maintenance-description';
            description.textContent = 'Espera un momento. Tu formulario y lo que has escrito se conservan. Podrás continuar aquí cuando termine.';
            const timing = document.createElement('p');
            timing.append('Tiempo estimado: ');
            estimate = document.createElement('strong');
            timing.append(estimate);
            const info = document.createElement('p');
            info.textContent = 'No necesitas actualizar la página.';
            notice.append(title, description, timing, info);
            notice.addEventListener('cancel', event => event.preventDefault());
            document.body.append(notice);
        }
        savedFocus = document.activeElement;
        selection = savedFocus && typeof savedFocus.selectionStart === 'number'
            ? [savedFocus.selectionStart, savedFocus.selectionEnd, savedFocus.selectionDirection] : null;
        active = true;
        document.documentElement.classList.add('vigilante-maintenance-open');
        notice.showModal();
        tick();
        ticker = setInterval(tick, 250);
        document.dispatchEvent(new CustomEvent('vigilante:maintenance', { detail: { active: true } }));
    }

    function accept(data, serverDate) {
        const serverNow = Date.parse(serverDate);
        const remaining = Number(data.expires_at) * 1000 - serverNow;
        if (!Array.isArray(data.hosts) || !data.hosts.some(matches) || !Number.isFinite(remaining)
                || remaining <= 0 || remaining > 120000) { hide(); return; }
        deadline = performance.now() + remaining;
        estimateDeadline = performance.now() + Math.max(0, Number(data.estimated_until) * 1000 - serverNow);
        show();
    }

    async function check() {
        clearTimeout(timer);
        if (document.hidden || request || !supported) return;
        request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 2500);
        try {
            const response = await fetch('/__vigilante_status', {
                credentials: 'omit', cache: 'no-store', signal: request.signal
            });
            if (response.status === 404 && !active) { supported = false; return; }
            if (!response.ok) throw new Error('Estado no disponible');
            accept(await response.json(), response.headers.get('Date'));
        } catch (error) {
            // Una interrupcion de red no destruye formularios ni retira antes
            // de tiempo una proteccion conocida. Su plazo sigue siendo local.
            if (active) tick();
            else if (error instanceof SyntaxError) supported = false;
        } finally {
            clearTimeout(timeout);
            request = null;
            if (!document.hidden && supported) timer = setTimeout(check, active ? 1000 : 4000);
        }
    }
    function guard(event) {
        if (active && !notice.contains(event.target)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }
    window.addEventListener('submit', guard, true);
    window.addEventListener('click', guard, true);
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (!document.hidden) check();
        else if (request) request.abort();
    });
    window.addEventListener('pageshow', check);
    window.addEventListener('online', check);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', check, { once: true });
    else check();
})();
