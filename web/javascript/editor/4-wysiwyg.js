/*console.log('ChatGPT4 5.0');

var sections = document.querySelectorAll('section');

sections.forEach(section => {
    section.addEventListener('click', () => {
        var editableElements = document.querySelectorAll('[contenteditable="true"]');
        editableElements.forEach(element => {
            if (element !== section) {
                element.removeAttribute('contenteditable');
            }
        });
        if (!section.hasAttribute('contenteditable')) {
            section.setAttribute('contenteditable', true);
            section.focus();
        }
    });
});

var editor = document.querySelector('.resultado');
var menu = document.querySelector('.wysiwyg');

editor.addEventListener('mouseup', (e) => {
    var selection = window.getSelection().toString().trim();
    if (selection.length > 0) {
        var range = window.getSelection().getRangeAt(0);
        var rect = range.getBoundingClientRect();
        var left = rect.left + window.scrollX;
        var top = rect.top + window.scrollY;
        menu.style.left = `${left}px`;
        menu.style.top = `${top - 30}px`;
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
});

menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    console.log(selection); // Devuelve un objeto Range
    const node = document.createElement(e.target.classList[0]);
    console.log(node); // Devuelve una cadena de texto con la etiqueta y su contenido
    const nodeName = e.target.classList[0];
    console.log(nodeName); // Devuelve el nombre de la etiqueta

    const selectedText = selection.toString();
    console.log(selectedText); // Devuelve el texto seleccionado
    const regex = new RegExp(`<${nodeName}>.*<\/${nodeName}>`);
    console.log(regex); // Devuelve una expresión regular que busca la etiqueta en el texto seleccionado
    const nodeInSelection = regex.test(node);
    console.log(nodeInSelection); // Devuelve un booleano indicando si la etiqueta ya existe en el texto seleccionado

    if (nodeInSelection) {
        const div = document.createElement('div');
        div.innerHTML = selectedText;
        const nodesToRemove = div.querySelectorAll(nodeName);
        nodesToRemove.forEach(node => {
            node.outerHTML = node.innerHTML;
        });
    } else {
        node.appendChild(selection.extractContents());
        selection.insertNode(node);
    }
});*/

/*menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    console.log(selection); // return (object)Range
    const nodeName = e.target.classList[0];
    console.log(nodeName); // return (string)'b' 

    const selectedText = selection.toString();
    console.log(selectedText); // return (string)'Traducido por Lobo Blanco'
    const regex = new RegExp(`<${nodeName}>.*<\/${nodeName}>`);
    console.log(regex); // return (string)'/<b>.*<\/b>/'
    const nodeInSelection = regex.test(selectedText);
    console.log(nodeInSelection); // return (bool)false

    if (nodeInSelection) {
        const div = document.createElement('div');
        div.innerHTML = selectedText;
        const nodesToRemove = div.querySelectorAll(nodeName);
        nodesToRemove.forEach(node => {
            node.outerHTML = node.innerHTML;
        });
    } else {
        const node = document.createElement(nodeName);
        node.appendChild(selection.extractContents());
        selection.insertNode(node);
    }
});*/

/*menu.addEventListener('click', (e) => {
    const selection = window.getSelection().getRangeAt(0);
    const node = document.createElement(e.target.classList[0]);
    node.appendChild(selection.extractContents());
    selection.insertNode(node);
});*/

//selection.removeAllRanges(); // eliminamos cualquier selección anterior
//selection.addRange(range); // agregamos el rango actual a la selección

/*var newRange = document.createRange();
newRange.setStartBefore(node);
newRange.setEndAfter(node);
selection.removeAllRanges();
selection.addRange(newRange);*/

/*menu.addEventListener('click', (e) => {
    var node = document.createElement(e.target.classList[0]);
    var selection = window.getSelection();
    var range = selection.getRangeAt(0);
    var textNode = range.extractContents();
    var parent = selection.focusNode.parentNode;

    if (parent.nodeName.toLowerCase() === e.target.classList[0]) {
        console.log("El nodo ha sido seleccionado");

        console.log(parent.parentNode);
        console.log(parent);

        parent.parentNode.insertBefore(textNode, parent);
        parent.remove();

    } else {
        console.log("El nodo no ha sido seleccionado");
        node.appendChild(textNode);
        range.insertNode(node);
    }
});*/