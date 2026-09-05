Kumbia.utils.on('click', '.inserta-apodo', function () {
    var pub = Kumbia.utils.getData(this, 'publicacion');
    var textarea = document.querySelector('.pub-' + pub + ' [name="comentario"]');
    var apodo = '{@' + Kumbia.utils.getData(this, 'apodo') + '} ';
    var val = textarea ? textarea.value : '';
    if (textarea) textarea.value = val + apodo;
});

// Backspace solo funciona con keyup
window.palabra = '';
Kumbia.utils.on('keyup', '[name="comentario"], [name="contenido"]', function (eve) {
    if (eve.key !== 'Backspace') return;
    if (window.palabra !== "") {
        window.palabra = window.palabra.slice(0, -1);
        if (window.palabra !== "") {
            let enc = typeof base64_encode === "function" ? base64_encode(window.palabra) : btoa(window.palabra);
            let url = '/usuarios/apodos/' + enc;
            var apodosDiv = document.querySelector('.apodos');
            if (apodosDiv) {
                apodosDiv.setAttribute('data-trozo', window.palabra);
                Kumbia.utils.fetch(url).then(html => apodosDiv.innerHTML = html);
            }
        }
    }
});

// Para el resto usamos keypress
window.llave = 0;
window.arroba = 0;
Kumbia.utils.on('keypress', '[name="comentario"], [name="contenido"]', function (eve) {
    if (eve.key == 'AltGraph' || eve.key == 'Control' || eve.key == 'Dead' || eve.key == 'Shift') {
        return;
    }

    if (eve.key == '{') {
        window.llave = 1;
        window.arroba = 0;
        window.palabra = '';
        return;
    }

    if (eve.key == 'Enter') {
        window.llave = 0;
    }

    if (window.llave == 1 && eve.key == '@') {
        window.arroba = 1;
        var existing = document.querySelector('.apodos');
        if (existing) existing.remove();
        this.insertAdjacentHTML('afterend', '<div class="apodos"></div>');
        return;
    } else if (window.llave == 1 && window.arroba == 0) {
        window.llave = 0;
    }

    if (window.llave == 0) {
        var existing2 = document.querySelector('.apodos');
        if (existing2) existing2.remove();
        window.arroba = 0;
        window.palabra = '';
        return;
    }

    if (window.arroba == 1) {
        window.palabra += eve.key;
        let enc = typeof base64_encode === "function" ? base64_encode(window.palabra) : btoa(window.palabra);
        let url = '/usuarios/apodos/' + enc;
        var apodosDiv = document.querySelector('.apodos');
        if (apodosDiv) {
            apodosDiv.setAttribute('data-trozo', window.palabra);
            let fd = new FormData();
            fd.append('trozo', window.palabra);
            Kumbia.utils.fetch(url, { method: 'POST', body: fd }).then(html => apodosDiv.innerHTML = html);
        }
    }
});

Kumbia.utils.on('click', '.tomar-apodo', function () {
    let parent = this.parentElement;
    if (!parent) return;
    let textarea = null;
    var el = parent.previousElementSibling;
    while (el) {
        if (el.tagName === 'TEXTAREA') { textarea = el; break; }
        el = el.previousElementSibling;
    }
    if (!textarea) return;

    let trozo = parent.getAttribute('data-trozo') || '';
    let apodo = '{@' + (this.textContent || this.innerText) + '} ';

    let val = textarea.value;
    let cursorPosition = textarea.selectionStart;
    let lastOpenBracket = val.lastIndexOf('{@' + trozo, cursorPosition);

    let newValue = val.substring(0, lastOpenBracket) + apodo;
    textarea.value = newValue;
    textarea.focus();

    parent.remove();
    window.llave = 0;
    window.arroba = 0;
    window.palabra = '';
});