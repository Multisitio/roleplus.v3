let clon_global = null;

window.textarea_auto_height = (el) => {
    if (el && el.nodeName === 'TEXTAREA') {
        el.style.overflow = 'hidden';

        if (!clon_global) {
            clon_global = document.createElement('textarea');
            clon_global.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;overflow:hidden';
            clon_global.tabIndex = -1;
            document.body.appendChild(clon_global);
        }

        const estilo_computado = window.getComputedStyle(el);

        clon_global.value = el.value;

        const reglas_herencia = [
            'fontFamily', 'fontSize', 'lineHeight', 'letterSpacing',
            'whiteSpace', 'wordBreak', 'wordWrap', 'padding',
            'boxSizing', 'borderWidth', 'borderStyle'
        ];

        reglas_herencia.forEach(regla => {
            clon_global.style[regla] = estilo_computado[regla];
        });

        clon_global.style.width = `${el.getBoundingClientRect().width}px`;
        clon_global.style.height = 'auto';

        let altura_final = clon_global.scrollHeight;

        if (estilo_computado.boxSizing === 'border-box') {
            altura_final += (parseFloat(estilo_computado.borderTopWidth) || 0) + (parseFloat(estilo_computado.borderBottomWidth) || 0);
        } else {
            altura_final -= (parseFloat(estilo_computado.paddingTop) || 0) + (parseFloat(estilo_computado.paddingBottom) || 0);
        }

        el.style.height = `${Math.max(altura_final, 55)}px`;
        return;
    }

    document.querySelectorAll('textarea').forEach(nodo => window.textarea_auto_height(nodo));
};

window.textarea_auto_height();

Kumbia.utils.on('click', 'textarea', function () { window.textarea_auto_height(this); });
Kumbia.utils.on('input', 'textarea', function () { window.textarea_auto_height(this); });