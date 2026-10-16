(function () {
    'use strict';
    function init() {
        document.querySelectorAll('form[data-autosave]').forEach(initForm);
    }
    function initForm(form) {
        const initialField = form.querySelector('[name="estado_inicial"]');
        if (!initialField) return;
        const initial = JSON.parse(initialField.value);
        const id = form.querySelector('[name="idu"]');
        const ficha = form.querySelector('[name="fichas_idu"]').value;
        const status = form.querySelector('[data-save-status]');
        let button = form.querySelector('button[value="salvar"]');
        const fields = Array.from(form.elements).filter(el => el.name &&
            ['text','textarea','checkbox','hidden'].includes(el.type) &&
            !['idu','fichas_idu','estado_inicial','fotos[]','action'].includes(el.name));
        const files = Array.from(form.querySelectorAll('input[type="file"]'));
        const fotos = Array.from(form.querySelectorAll('[name="fotos[]"]'));
        const base = {};
        const fileBase = new Map();
        const value = el => el.type === 'checkbox' ? (el.checked ? el.value : '') : el.value;
        fields.forEach(el => { base[el.name] = value(el); });
        files.forEach(el => fileBase.set(el, ''));
        const fileKey = el => Array.from(el.files || []).map(f => f.name+'|'+f.size+'|'+f.lastModified).join(';');
        let revision = initial.revision, editable = initial.editable;
        let timer = null, saving = false, submitting = false, syncing = false, stopped = false;
        const message = text => { if (status) status.textContent = text; };
        function dirty() {
            const names = fields.filter(el => value(el) !== base[el.name]).map(el => el.name);
            files.forEach((el,i) => { if (fileKey(el) !== fileBase.get(el) && fotos[i]) names.push(fotos[i].value); });
            return [...new Set(names)];
        }
        function permissions(allowed) {
            editable = allowed;
            if (allowed && !button) {
                button = document.createElement('button');
                button.type = 'submit'; button.name = 'action'; button.value = 'salvar';
                button.className = 'paper-btn btn-success-outline mh5';
                button.textContent = '+ Salvar';
                form.querySelector('.fixed-bottom-right').appendChild(button);
            }
            if (button) button.disabled = !allowed;
            fields.forEach(el => { if (el.type === 'text' || el.type === 'textarea') el.readOnly = !allowed; });
            files.forEach(el => { el.disabled = !allowed; });
        }
        permissions(editable);
        async function requestJson(url, options) {
            const abort = new AbortController();
            const timeout = setTimeout(() => abort.abort(), 30000);
            try {
                const res = await Kumbia.utils.fetch(url, {...options, response:true, signal:abort.signal});
                const data = res.ok && !res.redirected ? await res.json() : null;
                return {res,data};
            } finally { clearTimeout(timeout); }
        }
        function apply(data, sent, sentFiles) {
            if (data.revision < revision) return;
            revision = data.revision;
            id.value = data.idu;
            fields.forEach(el => {
                let remote = String(data.datos[el.name] ?? '');
                // Compare the same representation used by the browser's controls.
                // Multipart/PHP may return CRLF while textarea.value always uses LF.
                if (el.type === 'textarea') remote = remote.replace(/\r\n?/g, '\n');
                else if (el.type === 'text') remote = remote.replace(/[\r\n]/g, '');
                else if (el.type === 'checkbox') remote = remote !== '' && remote !== '0' ? el.value : '';
                const local = value(el);
                const unchanged = local === base[el.name] || (sent && local === sent[el.name]);
                if (unchanged) {
                    if (el.type === 'checkbox') el.checked = remote !== '' && remote !== '0';
                    else el.value = remote;
                }
                base[el.name] = remote;
            });
            if (sentFiles) files.forEach(el => {
                if (fileKey(el) === sentFiles.get(el)) { el.value = ''; fileBase.set(el, ''); }
            });
            // Portrait references are updated independently of the current file selection.
            fotos.forEach(el => {
                const hidden = fields.find(field => field.name === el.value);
                const box = el.closest('.dropimage');
                if (!hidden || !box || files.some(file => file.closest('.dropimage') === box && file.files.length)) return;
                let image = box.querySelector('img');
                if (hidden.value) {
                    if (!image) { image = document.createElement('img'); image.alt = 'Retrato'; box.appendChild(image); }
                    const src = '/img/fichas/retratos/l.'+hidden.value;
                    if (image.getAttribute('src') !== src) image.setAttribute('src', src);
                    box.classList.add('dropnocontent');
                } else { if (image) image.remove(); box.classList.remove('dropnocontent'); }
            });
            permissions(data.editable);
        }
        function schedule() {
            if (!editable) return;
            if (timer) clearTimeout(timer);
            message('Cambios pendientes de guardar…');
            timer = setTimeout(save, 3000);
        }
        async function save(force) {
            if (timer) clearTimeout(timer);
            timer = null;
            if (saving || !editable) return;
            let names = dirty();
            if (!names.length && force && !revision) names = fields.map(el => el.name);
            if (!names.length) { message('Cambios guardados'); return; }
            const sent = Object.fromEntries(fields.map(el => [el.name,value(el)]));
            const sentFiles = new Map(files.map(el => [el,fileKey(el)]));
            const body = new FormData(form);
            body.set('action','salvar'); body.set('campos',JSON.stringify(names));
            saving = true; message('Guardando…');
            try {
                const {res,data} = await requestJson(location.href, {method:'POST',body});
                if (!res.ok || res.redirected) throw new Error('No se ha podido guardar.');
                if (!data.success || !data.idu || !data.datos || !data.url) throw new Error('Guardado no confirmado.');
                apply(data,sent,sentFiles);
                history.replaceState(null,'',data.url);
                message('Cambios guardados');
                if (dirty().length) schedule();
            } catch (err) {
                message(err.name === 'AbortError'
                    ? 'El guardado ha tardado demasiado. Tus cambios siguen pendientes; pulsa Salvar para reintentar.'
                    : 'Error al guardar. Tus cambios siguen pendientes; pulsa Salvar para reintentar.');
            } finally { saving = false; }
        }
        async function sync() {
            if (!revision || syncing || saving || stopped || document.hidden || initial.sincronizar === false) return;
            syncing = true;
            try {
                const {res,data} = await requestJson('/ev/personajes/estado/'+encodeURIComponent(ficha)+'/'+encodeURIComponent(id.value), {});
                if (res.status === 401 || res.status === 404) {
                    stopped = true;
                    message(res.status === 401 ? 'La sesión ha caducado. Conserva tus cambios antes de salir.' : 'El personaje ha sido eliminado. Conserva tus cambios antes de salir.');
                    return;
                }
                if (!res.ok) throw new Error('Sincronización interrumpida.');
                if (!data.success || !data.datos) throw new Error('Respuesta inválida.');
                if (data.revision > revision) {
                    apply(data);
                    message(dirty().length ? 'Cambios pendientes de guardar…' : 'Datos actualizados');
                }
            } catch (err) {
                if (!dirty().length) message('Sin conexión. Se reintentará la sincronización.');
            } finally { syncing = false; }
        }
        fields.forEach(el => {
            el.addEventListener(el.type === 'checkbox' ? 'change' : 'input',schedule);
            if (el.type === 'checkbox') el.addEventListener('click',e => { if (!editable) e.preventDefault(); });
        });
        files.forEach(el => el.addEventListener('change',schedule));
        form.addEventListener('submit',e => {
            if (e.submitter && e.submitter.value === 'salvar') {
                e.preventDefault(); if (timer) clearTimeout(timer); timer = null; save(true);
            } else submitting = true;
        });
        window.addEventListener('beforeunload',e => {
            if (!submitting && (saving || dirty().length)) { e.preventDefault(); e.returnValue = ''; }
        });
        // These requests deliver other editors' changes; they are not session keep-alives.
        setInterval(sync,3000);
        document.addEventListener('visibilitychange',() => { if (!document.hidden) sync(); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init);
    else init();
})();
