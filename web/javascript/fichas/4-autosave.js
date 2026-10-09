(function () {
    "use strict";

    function showToast(message, type) {
        var container = document.querySelector(".toast-container");
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.querySelector('main').appendChild(container);
        }
        var toastId = "toast-js-" + Math.floor(Math.random() * 1000000);
        var title = (type === "error") ? "ERROR" : "INFO";
        var html =
            '<div class="mb15 ' + toastId + '">' +
            '<button type="button" class="btn-small" data-remove="parent">' +
            '<img alt="Cerrar" src="/img/icons/x.svg">' +
            '</button>' +
            '<h3>' + title + '</h3>' +
            '<p>' + message + '</p>' +
            '</div>';
            
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        var toast = wrapper.firstElementChild;
        container.appendChild(toast);
        
        var btn = toast.querySelector('[data-remove="parent"]');
        if(btn) {
            btn.addEventListener('click', function() {
                if(window.Kumbia && Kumbia.fx) Kumbia.fx.fadeOut(toast);
                else toast.remove();
            });
        }
        setTimeout(function () {
            if (toast.parentElement) {
                if(window.Kumbia && Kumbia.fx) Kumbia.fx.fadeOut(toast);
                else toast.remove();
            }
        }, 4000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }

    function initAll() {
        var forms = document.querySelectorAll("form[data-autosave]");
        for (var f = 0; f < forms.length; f++) {
            initForm(forms[f]);
        }
    }

    function initForm(form) {
        if (!form) return;
        var actionVal = (form.getAttribute("data-autosave") || "").trim();
        if (!actionVal) return;
        var saveBtnSel = 'button[name="action"][value="' + actionVal + '"]';
        var saveBtn = form.querySelector(saveBtnSel);
        if (!saveBtn) return;

        var watched = form.querySelectorAll(
            'textarea, input[type="text"], input[type="checkbox"], input[type="file"]'
        );
        var lastData = takeSnapshot(watched);
        var timer = null;
        var saving = false;
        var isSubmitting = false;
        var status = form.querySelector('[data-save-status]');

        function setStatus(message) {
            if (status) status.textContent = message;
        }

        form.addEventListener("submit", function(e) {
            if (e.submitter === saveBtn) {
                e.preventDefault();
                if (timer) clearTimeout(timer);
                timer = null;
                doSave(true);
                return;
            }
            isSubmitting = true;
        });

        window.addEventListener("beforeunload", function (e) {
            if (isSubmitting) return;

            var currentSnapshot = takeSnapshot(watched);
            if (saving || timer || currentSnapshot !== lastData) {
                var msg = "¿Guardar cambios antes de salir?";
                e.preventDefault();
                e.returnValue = msg;
                return msg;
            }
        });

        function scheduleSave() {
            if (timer) clearTimeout(timer);
            setStatus('Cambios pendientes de guardar…');
            timer = setTimeout(function () { doSave(); }, 3000);
        }

        function doSave(force) {
            timer = null;
            if (saving) return;
            var currentData = takeSnapshot(watched);
            if ( ! force && currentData === lastData) return;
            var fd = new FormData(form);
            fd.set("action", actionVal);
            var url = (form.getAttribute("action") || "").trim() || window.location.href;
            saving = true;
            setStatus('Guardando…');
            fetch(url, {
                method: "POST",
                cache: "no-store",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: fd,
                credentials: "same-origin"
            })
                .then(function (res) {
                    if (!res.ok || res.redirected) throw new Error('No se ha podido guardar.');
                    return res.json();
                })
                .then(function (data) {
                    if (!data.success || !data.idu || !data.url) throw new Error('Guardado no confirmado.');
                    form.querySelector('[name="idu"]').value = data.idu;
                    window.history.replaceState(null, '', data.url);
                    lastData = currentData;
                    setStatus('Cambios guardados');
                    saving = false;
                    // Preserve edits made while the previous request was in flight.
                    if (takeSnapshot(watched) !== lastData) scheduleSave();
                })
                .catch(function () {
                    saving = false;
                    setStatus('Error al guardar. Tus cambios siguen pendientes.');
                    showToast('No se han podido guardar tus cambios. No salgas de la ficha; pulsa Salvar para reintentar.', 'error');
                });
        }

        for (var i = 0; i < watched.length; i++) {
            var el = watched[i];
            var evt = (el.type === "checkbox" || el.type === "file") ? "change" : "input";
            el.addEventListener(evt, scheduleSave);
        }
    }

    function takeSnapshot(nodeList) {
        var out = [];
        for (var i = 0; i < nodeList.length; i++) {
            var el = nodeList[i];
            var name = el.name || ("__idx" + i);
            if (el.type === "checkbox") {
                out.push(name + "=" + (el.checked ? "1" : "0"));
            } else if (el.type === "file") {
                if (el.files && el.files.length > 0) {
                    var f = el.files[0];
                    out.push(name + "=" + f.name + "|" + f.size + "|" + f.lastModified);
                } else {
                    out.push(name + "=");
                }
            } else {
                out.push(name + "=" + (el.value === undefined ? "" : el.value));
            }
        }
        return out.join("&");
    }
})();
