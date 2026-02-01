(function() {
    "use strict";

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

        // ej: data-autosave="salvar"
        var actionVal = (form.getAttribute("data-autosave") || "").trim();

        if (!actionVal) return;

        console.log('Encontrado [data-autosave="' + actionVal + '"]');

        // buscamos el botón equivalente al de guardar manual
        var saveBtnSel = 'button[name="action"][value="' + actionVal + '"]';
        var saveBtn = form.querySelector(saveBtnSel);

        // si no hay botón => no es tu PJ => no hay autosave
        if (!saveBtn) {
            console.warn("Encontrado [data-autosave], pero botón no encontrado:", saveBtnSel);
            return;
        }

        // ocultar el botón salvar
        saveBtn.style.display = "none";

        // vigilamos estos campos
        var watched = form.querySelectorAll(
            'textarea, input[type="text"], input[type="checkbox"], input[type="file"]'
        );

        var lastData = takeSnapshot(watched);
        var timer = null;
        var saving = false;

        function scheduleSave() {
            if (timer) clearTimeout(timer);
            timer = setTimeout(function() {
                console.log("Guardado después de dejar de escribir por 3 segundos");
                doSave();
            }, 3000);
        }

        function doSave() {
            timer = null;
            if (saving) return;

            var currentData = takeSnapshot(watched);
            if (currentData === lastData) return;

            var fd = new FormData(form);
            fd.set("action", actionVal); // imita el submit del botón "salvar"

            var url = (form.getAttribute("action") || "").trim();
            if (!url) url = window.location.href;

            saving = true;
            console.log("Autosave… POST ->", url);

            fetch(url, {
                    method: "POST",
                    cache: "no-store",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: fd,
                    credentials: "same-origin"
                })
                .then(function(res) {
                    if (res.ok) {
                        lastData = currentData;
                        console.log("Autosave OK (" + res.status + ")");
                    } else {
                        console.warn("Autosave fallo HTTP:", res.status);
                    }
                })
                .catch(function(err) {
                    console.warn("Autosave error de red:", err);
                })
                .finally(function() {
                    saving = false;
                });
        }

        for (var i = 0; i < watched.length; i++) {
            var el = watched[i];
            var evt = (el.type === "checkbox" || el.type === "file") ? "change" : "input";
            el.addEventListener(evt, scheduleSave);
        }

        window.addEventListener("beforeunload", function() {
            if (timer) {
                console.log("beforeunload con cambios pendientes -> forzando autosave inmediato");
                doSave();
            }
        });
    }

    function takeSnapshot(nodeList) {
        var out = [];
        for (var i = 0; i < nodeList.length; i++) {
            var el = nodeList[i];
            var name = el.name || ("__idx" + i);
            if (el.type === "checkbox") {
                out.push(name + "=" + (el.checked ? "1" : "0"));
            } else if (el.type === "file") {
                // Para ficheros usamos nombre + tamaño + fecha como firma
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