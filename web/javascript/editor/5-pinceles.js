/**
 * Editor Pinceles — Inserción de etiquetas HTML + Migas de pan
 * Sin IDs ni clases: usa selectores de atributo y elemento.
 */
(function (window, document) {
    'use strict';

    /* -----------------------------------------------------------------
       SELECTORES SEMÁNTICOS (un único lugar para cambiarlos)
    ----------------------------------------------------------------- */
    var SEL_NAV_PINCELES  = 'nav[data-toolbar="pinceles"]';
    var SEL_NAV_CONTEXTO  = 'nav[data-toolbar="contexto"]';
    var SEL_PAGE_SELECT   = SEL_NAV_CONTEXTO + ' select:first-of-type';
    var SEL_POS_SELECT    = SEL_NAV_CONTEXTO + ' select:last-of-type';
    var SEL_BREADCRUMB    = SEL_NAV_CONTEXTO + ' span';
    var SEL_OUTPUT        = 'body > output';
    var SEL_ARTICLE       = 'main div[contenteditable]';
    var SEL_PINCEL_BTN    = SEL_NAV_PINCELES + ' button[data-tag]';

    /* -----------------------------------------------------------------
       CONFIGURACIÓN DE ETIQUETAS
    ----------------------------------------------------------------- */
    var TAGS = [
        { tag: 'article',    block: true,  wrap: false, children: '' },
        { tag: 'div',        block: true,  wrap: false, children: '' },
        { tag: 'header',     block: true,  wrap: false, children: '' },
        { tag: 'section',    block: true,  wrap: false, children: '' },
        { tag: 'footer',     block: true,  wrap: false, children: '' },
        { tag: 'hr',         block: true,  wrap: false, children: null, selfClose: true },
        { tag: 'h1',         block: true,  wrap: false, children: '' },
        { tag: 'h2',         block: true,  wrap: false, children: '' },
        { tag: 'h3',         block: true,  wrap: false, children: '' },
        { tag: 'h4',         block: true,  wrap: false, children: '' },
        { tag: 'h5',         block: true,  wrap: false, children: '' },
        { tag: 'h6',         block: true,  wrap: false, children: '' },
        { tag: 'p',          block: true,  wrap: false, children: '' },
        { tag: 'small',      block: false, wrap: true,  children: 'pequeño' },
        { tag: 'a',          block: false, wrap: true,  children: 'enlace', attrs: 'href="#"' },
        { tag: 'b',          block: false, wrap: true,  children: 'negrita' },
        { tag: 'strong',     block: false, wrap: true,  children: 'negrita' },
        { tag: 'i',          block: false, wrap: true,  children: 'cursiva' },
        { tag: 'em',         block: false, wrap: true,  children: 'cursiva' },
        { tag: 'img',        block: false, wrap: false, children: null, selfClose: true, attrs: 'src="" alt=""' },
        { tag: 'mark',       block: false, wrap: true,  children: 'marcado' },
        { tag: 's',          block: false, wrap: true,  children: 'tachado' },
        { tag: 'span',       block: false, wrap: true,  children: 'texto' },
        { tag: 'sub',        block: false, wrap: true,  children: 'sub' },
        { tag: 'sup',        block: false, wrap: true,  children: 'sup' },
        { tag: 'u',          block: false, wrap: true,  children: 'subrayado' },
        { tag: 'blockquote', block: true,  wrap: false, children: '' },
        { tag: 'q',          block: false, wrap: true,  children: 'cita' },
        { tag: 'ol',         block: true,  wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul',         block: true,  wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul.checkbox', block: true, wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'ul.radio',    block: true, wrap: false, children: '\n\t\t<li></li>\n\t' },
        { tag: 'li',         block: true,  wrap: false, children: '' },
        { tag: 'table',      block: true,  wrap: false, children: '\n\t\t<thead><tr><th></th></tr></thead>\n\t\t<tbody><tr><td></td></tr></tbody>\n\t' },
        { tag: 'table.w100', block: true,  wrap: false, attrs: 'style="width: 100%;"', children: '\n\t\t<thead><tr><th></th></tr></thead>\n\t\t<tbody><tr><td></td></tr></tbody>\n\t' },
        { tag: 'caption',    block: true,  wrap: false, children: '' },
        { tag: 'thead',      block: true,  wrap: false, children: '\n\t\t<tr><th></th></tr>\n\t' },
        { tag: 'tbody',      block: true,  wrap: false, children: '\n\t\t<tr><td></td></tr>\n\t' },
        { tag: 'tfoot',      block: true,  wrap: false, children: '\n\t\t<tr><td></td></tr>\n\t' },
        { tag: 'tr',         block: true,  wrap: false, children: '\n\t\t<td></td>\n\t' },
        { tag: 'th',         block: true,  wrap: false, children: '' },
        { tag: 'td',         block: true,  wrap: false, children: '' }
    ];

    /* -----------------------------------------------------------------
       ESTADO
    ----------------------------------------------------------------- */
    var activeArticle  = null;
    var savedRange     = null;
    var debounceTimers = {};
    var countdownTimers = {};
    var savePromises   = {};
    var isDirty        = {};
    var currentCrumbs  = [];

    /* -----------------------------------------------------------------
       BREADCRUMB
    ----------------------------------------------------------------- */
    function getAncestors(node, container) {
        var crumbs = [];
        var cur = node;
        while (cur && cur !== container) {
            if (cur.nodeType === 1) {
                crumbs.unshift(cur);
            }
            cur = cur.parentNode;
        }
        return crumbs;
    }


    function updateBreadcrumb() {
        var bc = document.querySelector(SEL_BREADCRUMB);
        if (!bc) return;

        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0 || !activeArticle) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var node = sel.anchorNode;
        if (node && node.nodeType === 3) node = node.parentNode;

        // Asegurar que el nodo seleccionado pertenece al artículo activo y está dentro de su <article>
        if (!activeArticle.contains(node)) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var targetArticle = activeArticle.querySelector('article');
        if (!targetArticle || !targetArticle.contains(node)) {
            bc.innerHTML = '';
            currentCrumbs = [];
            return;
        }

        var elements = getAncestors(node, activeArticle);

        // Comparar con currentCrumbs para ver si ha cambiado la ruta
        var changed = elements.length !== currentCrumbs.length;
        if (!changed) {
            for (var j = 0; j < elements.length; j++) {
                if (elements[j] !== currentCrumbs[j]) {
                    changed = true;
                    break;
                }
            }
        }

        if (!changed) return;

        currentCrumbs = elements;

        // Generar botones para cada etiqueta de migas de pan
        var html = '';
        
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (i > 0) html += '<i> &gt; </i>';
            
            var name = el.tagName.toLowerCase();
            if (el.id && name !== 'article') {
                name += '>' + el.id;
            }
            
            html += '<button type="button" data-index="' + i + '">' + name + '</button>';
        }
        bc.innerHTML = html;
    }

    /* -----------------------------------------------------------------
       ELEMENTOS VACÍOS (badges y placeholders dinámicos)
       ----------------------------------------------------------------- */
    function syncHelpers(art) {
        if (!art) return;
        
        var candidates = art.querySelectorAll('article, p, h1, h2, h3, h4, h5, h6, blockquote, div, header, section, footer, table, thead, tbody, tfoot, tr, td, th, caption, ul, ol, li');
        candidates.forEach(function (el) {
            if (el === art) return;
            
            var hasText = false;
            var hasRealChildren = false;
            
            for (var i = 0; i < el.childNodes.length; i++) {
                var node = el.childNodes[i];
                if (node.nodeType === 1) {
                    if (node.hasAttribute('data-editor-helper') || node.classList.contains('button-floating')) {
                        continue;
                    }
                    if (node.tagName.toLowerCase() === 'br') {
                        continue;
                    }
                    hasRealChildren = true;
                    break;
                } else if (node.nodeType === 3) {
                    var text = node.textContent.replace(/[\u200B\s]/g, '');
                    if (text.length > 0) {
                        hasText = true;
                        break;
                    }
                }
            }
            
            var isEmpty = !hasText && !hasRealChildren;
            var badge = el.querySelector(':scope > [data-editor-helper="badge"]');
            
            if (isEmpty) {
                el.setAttribute('data-empty-helper', 'true');
                if (!badge) {
                    var tagName = el.tagName.toLowerCase();
                    badge = document.createElement('span');
                    badge.contentEditable = "false";
                    badge.setAttribute('data-editor-helper', 'badge');
                    badge.className = 'editor-badge';
                    badge.innerHTML = '<span class="tag-name">' + tagName + '</span><span class="del-btn">×</span>';
                    el.insertBefore(badge, el.firstChild);
                }
                
                var lastNode = el.lastChild;
                if (!lastNode || lastNode.nodeType !== 3 || lastNode.textContent.indexOf('\u200B') === -1) {
                    el.appendChild(document.createTextNode('\u200B'));
                }
            } else {
                el.removeAttribute('data-empty-helper');
                if (badge) {
                    badge.remove();
                }
            }
        });
    }

    /* -----------------------------------------------------------------
       CONJUNTO DE ETIQUETAS DE BLOQUE (usado en Backspace/Delete y cleanSpuriousBr)
    ----------------------------------------------------------------- */
    var BLOCK_TAGS_SET = {
        'h1':true,'h2':true,'h3':true,'h4':true,'h5':true,'h6':true,
        'p':true,'div':true,'header':true,'footer':true,'section':true,'article':true,
        'blockquote':true,'ul':true,'ol':true,'li':true,
        'table':true,'tbody':true,'thead':true,'tfoot':true,'tr':true,'td':true,'th':true,'caption':true
    };

    /* -----------------------------------------------------------------
       LIMPIAR <br> ESPÚRIOS
       Elimina cualquier <br> que sea hijo directo de un contenedor de bloque
       y tenga al menos un hermano que también sea un elemento de bloque.
       Estos <br> los inyecta el navegador durante manipulación del DOM;
       el usuario no los ha pedido y no deben existir como separadores entre bloques.
    ----------------------------------------------------------------- */
    function cleanSpuriousBr(art) {
        if (!art) return;
        var brs = art.querySelectorAll('br');
        brs.forEach(function (br) {
            var parent = br.parentNode;
            if (!parent || !parent.tagName) return;
            // Solo actuar si el padre es un contenedor de bloque
            if (!BLOCK_TAGS_SET[parent.tagName.toLowerCase()]) return;
            // Comprobar si tiene al menos un hermano elemento de bloque
            var hasBlockSibling = false;
            for (var i = 0; i < parent.childNodes.length; i++) {
                var sib = parent.childNodes[i];
                if (sib === br) continue;
                if (sib.nodeType === 1 &&
                    !sib.hasAttribute('data-editor-helper') &&
                    BLOCK_TAGS_SET[sib.tagName.toLowerCase()]) {
                    hasBlockSibling = true;
                    break;
                }
            }
            if (hasBlockSibling) br.remove();
        });
    }

    /* -----------------------------------------------------------------
       GUARDAR RANGO antes de perder foco
    ----------------------------------------------------------------- */
    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            var range = sel.getRangeAt(0);
            var cont  = range.commonAncestorContainer;
            var art   = cont.nodeType === 1
                ? cont.closest('div[contenteditable]')
                : (cont.parentNode ? cont.parentNode.closest('div[contenteditable]') : null);
            if (art) {
                savedRange    = range.cloneRange();
                activeArticle = art;
                updateBreadcrumb();
            }
        }

        // 1) Cuando hay texto seleccionado, el control superior se pone en "seleccion"
        var posSelect = document.querySelector(SEL_POS_SELECT);
        if (posSelect) {
            if (sel && sel.toString().trim().length > 0) {
                if (posSelect.value !== 'seleccion') {
                    posSelect.setAttribute('data-prev-value', posSelect.value);
                    posSelect.value = 'seleccion';
                }
            } else {
                if (posSelect.value === 'seleccion') {
                    var prev = posSelect.getAttribute('data-prev-value') || 'dentro';
                    posSelect.value = prev;
                }
            }
        }
    });

    // 3) Excepción: el botón ART PAGE solo añade Antes o Después. Al pasar el ratón (hover), forzar la posición.
    document.addEventListener('mouseover', function (e) {
        var btn = e.target.closest(SEL_PINCEL_BTN);
        if (btn) {
            var tag = btn.getAttribute('data-tag');
            if (tag === 'article') {
                var posSelect = document.querySelector(SEL_POS_SELECT);
                if (posSelect) {
                    var val = posSelect.value;
                    if (val !== 'antes' && val !== 'despues') {
                        posSelect.value = 'antes';
                    }
                }
            }
        }
    });

    /* -----------------------------------------------------------------
       HELPERS DE INSERCIÓN
    ----------------------------------------------------------------- */
    function restoreRange() {
        if (!savedRange || !activeArticle) return false;
        try {
            activeArticle.focus();
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
            return true;
        } catch (e) {
            return false;
        }
    }

    function placeCursorInside(el) {
        if (!el) return;

        // Foco explícito en el contenedor editable primero
        var art = el.closest('div[contenteditable]');
        if (art) {
            art.focus();
        }

        var target = el;
        // Si está vacío, le inyectamos un nodo de texto vacío con zwsp para estabilizar el cursor en contenteditable
        if (target.nodeType === 1 && !target.firstChild) {
            target.appendChild(document.createTextNode('\u200B'));
        }

        // Si es un helper vacío, el foco debe ir al texto invisible del final, no al badge ineditable
        if (target.nodeType === 1 && target.getAttribute('data-empty-helper') === 'true') {
            target = target.lastChild;
        } else {
            // Navegar hasta el nodo de texto o elemento hijo más profundo
            while (target.firstChild) {
                target = target.firstChild;
            }
        }

        var sel   = window.getSelection();
        var range = document.createRange();
        
        try {
            if (target.nodeType === 3) {
                range.setStart(target, 0);
                range.setEnd(target, 0);
            } else {
                range.selectNodeContents(target);
                range.collapse(true);
            }
            sel.removeAllRanges();
            sel.addRange(range);
            
            savedRange = range.cloneRange();
        } catch (e) { /* fallback silencioso */ }
    }

    function buildHtml(def, selectedText, position) {
        var parts = def.tag.split('.');
        var tagName = parts[0];
        var className = parts[1] || '';
        
        var attrs = (className ? ' class="' + className + '"' : '') + (def.attrs ? ' ' + def.attrs : '');

        if (def.selfClose) return '<' + tagName + attrs + '>\n';
        var inner = (def.wrap || position === 'seleccion') && selectedText ? selectedText
            : (def.children !== null && def.children !== undefined ? def.children : '');
        var open  = '<' + tagName + attrs + '>';
        var close = '</' + tagName + '>';
        return def.block ? '\n\t' + open + inner + close + '\n' : open + inner + close;
    }

    /**
     * Devuelve el elemento temporal correcto para parsear HTML de la etiqueta indicada.
     * El browser descarta etiquetas de tabla (<tr>, <td>, etc.) cuando se parsean
     * dentro de un <div>. Es necesario usar el contenedor semántico adecuado.
     */
    function createTempContainer(tag) {
        var t = tag.split('.')[0];
        if (t === 'tr') {
            // <tr> solo se parsea bien dentro de <tbody>/<thead>/<tfoot>
            var table = document.createElement('table');
            var tbody = document.createElement('tbody');
            table.appendChild(tbody);
            return tbody;
        }
        if (t === 'td' || t === 'th') {
            // <td>/<th> solo se parsean bien dentro de <tr>
            var table = document.createElement('table');
            var tbody = document.createElement('tbody');
            var tr    = document.createElement('tr');
            table.appendChild(tbody);
            tbody.appendChild(tr);
            return tr;
        }
        if (t === 'thead' || t === 'tbody' || t === 'tfoot' || t === 'caption') {
            return document.createElement('table');
        }
        if (t === 'li') {
            return document.createElement('ul');
        }
        return document.createElement('div');
    }

    function insertTag(def) {
        if (!activeArticle) return;
        restoreRange();

        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;

        var targetArticle = activeArticle.querySelector('article');
        if (!targetArticle) return;

        var range        = sel.getRangeAt(0);
        
        var divHtml = document.createElement('div');
        divHtml.appendChild(range.cloneContents());
        var selectedHtml = divHtml.innerHTML || sel.toString();
        
        var selectedText = sel.toString();
        var posSelect    = document.querySelector(SEL_POS_SELECT);
        var position     = posSelect ? posSelect.value : 'dentro';
        
        if (selectedText.replace(/[\s\u200B]/g, '').length > 0 && def.wrap) {
            position = 'seleccion';
        }
        var html         = buildHtml(def, selectedHtml, position);
        var newEl        = null;

        if (position === 'seleccion') {
            document.execCommand('insertHTML', false, html);
            
            // Limpiar estilos en línea inyectados por Chromium (bug de insertHTML)
            if (targetArticle) {
                var garbageElements = targetArticle.querySelectorAll('[style*="text-wrap-mode"]');
                for (var i = 0; i < garbageElements.length; i++) {
                    var el = garbageElements[i];
                    el.style.removeProperty('text-wrap-mode');
                    if (el.getAttribute('style') === '') el.removeAttribute('style');
                    
                    // Si queda un span vacío (sin atributos), lo desenvolvemos
                    if (el.tagName.toLowerCase() === 'span' && el.attributes.length === 0) {
                        var fragCleanup = document.createDocumentFragment();
                        while(el.firstChild) { fragCleanup.appendChild(el.firstChild); }
                        if (el.parentNode) el.parentNode.replaceChild(fragCleanup, el);
                    }
                }
            }
        } else if (position === 'dentro') {
            if (def.selfClose) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                var frag = document.createDocumentFragment();
                while (tmp.firstChild) frag.appendChild(tmp.firstChild);
                range.deleteContents();
                range.insertNode(frag);
            } else {
                var tmp2 = createTempContainer(def.tag);
                tmp2.innerHTML = html;
                newEl = tmp2.firstElementChild;
                if (!newEl) return;

                if (def.block) {
                    var container = range.startContainer;
                    if (container.nodeType === 3) container = container.parentNode;

                    // Contenedores válidos para cada familia de etiquetas
                    var insertedTag = def.tag.split('.')[0];
                    var blockContainerTags;
                    if (insertedTag === 'td' || insertedTag === 'th') {
                        blockContainerTags = ['tr'];
                    } else if (insertedTag === 'tr') {
                        blockContainerTags = ['tbody', 'thead', 'tfoot', 'table'];
                    } else if (insertedTag === 'thead' || insertedTag === 'tbody' || insertedTag === 'tfoot' || insertedTag === 'caption') {
                        blockContainerTags = ['table'];
                    } else if (insertedTag === 'li') {
                        blockContainerTags = ['ul', 'ol'];
                    } else {
                        blockContainerTags = ['article', 'section', 'div', 'header', 'footer', 'blockquote', 'li', 'td', 'th'];
                    }

                    var blockContainer = container;
                    while (blockContainer && blockContainer !== targetArticle) {
                        var tag = blockContainer.tagName.toLowerCase();
                        if (blockContainerTags.indexOf(tag) !== -1) { break; }
                        blockContainer = blockContainer.parentNode;
                    }
                    if (!blockContainer) blockContainer = targetArticle;

                    if (blockContainer === container) {
                        blockContainer.appendChild(newEl);
                    } else {
                        var child = container;
                        while (child && child.parentNode !== blockContainer) {
                            child = child.parentNode;
                        }
                        if (child && child.parentNode === blockContainer) {
                            blockContainer.insertBefore(newEl, child.nextSibling);
                        } else {
                            blockContainer.appendChild(newEl);
                        }
                    }
                } else {
                    if (def.wrap && selectedText) range.deleteContents();
                    range.insertNode(newEl);
                }
            }
        } else {
            var anchor = range.startContainer;
            if (anchor.nodeType === 3) anchor = anchor.parentNode;

            // Contenedores válidos (padres) para cada familia de etiquetas
            var insertedTag = def.tag.split('.')[0];
            var validParents;
            if (insertedTag === 'tr') {
                validParents = ['tbody', 'thead', 'tfoot'];
            } else if (insertedTag === 'td' || insertedTag === 'th') {
                validParents = ['tr'];
            } else if (insertedTag === 'thead' || insertedTag === 'tbody' || insertedTag === 'tfoot' || insertedTag === 'caption') {
                validParents = ['table'];
            } else if (insertedTag === 'li') {
                validParents = ['ul', 'ol'];
            } else {
                validParents = ['header', 'section', 'div', 'blockquote', 'td', 'th', 'li', 'article', 'footer'];
            }

            while (anchor && anchor.parentNode && anchor.parentNode !== targetArticle) {
                var parentTag = anchor.parentNode.tagName.toLowerCase();
                if (validParents.indexOf(parentTag) !== -1) { break; }
                anchor = anchor.parentNode;
            }

            var parentNode = anchor ? anchor.parentNode : targetArticle;
            if (!parentNode) parentNode = targetArticle;

            var tmp3 = createTempContainer(def.tag);
            tmp3.innerHTML = html;
            newEl = tmp3.firstElementChild || tmp3.firstChild;
            if (!newEl) return;

            parentNode.insertBefore(newEl,
                position === 'antes' ? anchor : (anchor ? anchor.nextSibling : null));
        }

        if (newEl && newEl.nodeType === 1) placeCursorInside(newEl);
        cleanSpuriousBr(activeArticle);
        syncHelpers(activeArticle);
        scheduleSave(activeArticle);
        updateBreadcrumb();
    }

    function recalcularPaginas(newIdu, newNombre, refIdu, position) {
        var pages = document.querySelectorAll(SEL_ARTICLE);
        pages.forEach(function (page, idx) {
            page.setAttribute('data-page', idx + 1);
        });
        
        var pageSelect = document.querySelector(SEL_PAGE_SELECT);
        if (pageSelect) {
            var options = Array.from(pageSelect.options).map(function (opt) {
                return { value: opt.value, text: opt.text };
            });
            
            var refIdx = options.findIndex(function (opt) { return opt.value === refIdu; });
            var newOpt = { value: newIdu, text: newNombre };
            
            if (refIdx !== -1) {
                if (position === 'antes') {
                    options.splice(refIdx, 0, newOpt);
                } else {
                    options.splice(refIdx + 1, 0, newOpt);
                }
            } else {
                options.push(newOpt);
            }
            
            var html = '';
            options.forEach(function (opt) {
                html += '<option value="' + opt.value + '">' + opt.text + '</option>';
            });
            pageSelect.innerHTML = html;
            pageSelect.value = newIdu;
        }
        
        var newPage = document.getElementById(newIdu);
        if (newPage) {
            newPage.focus();
            activeArticle = newPage;
            window.location.hash = newIdu;
        }
    }

    /* -----------------------------------------------------------------
       CLICK EN BOTÓN PINCEL / MIGAS DE PAN
    ----------------------------------------------------------------- */
    document.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('[data-toggle="guias"]');
        if (toggleBtn) {
            e.preventDefault();
            var mainEl = document.querySelector('main');
            if (mainEl) {
                var showAll = mainEl.classList.toggle('show-all-outlines');
                toggleBtn.classList.toggle('active', showAll);
                try {
                    localStorage.setItem('editor-show-all-outlines', showAll ? '1' : '0');
                } catch (err) {}
            }
            return;
        }

        var btnNoPage = e.target.closest('[data-toggle="no-page-number"]');
        if (btnNoPage && activeArticle) {
            e.preventDefault();
            var innerArt = activeArticle.querySelector('article');
            if (innerArt) {
                var isActive = innerArt.classList.toggle('no-page-number');
                btnNoPage.classList.toggle('active', isActive);
                saveArticle(activeArticle);
            }
            return;
        }

        var btnResetPage = e.target.closest('[data-toggle="page-1"]');
        if (btnResetPage && activeArticle) {
            e.preventDefault();
            var innerArt = activeArticle.querySelector('article');
            if (innerArt) {
                var isActive = innerArt.classList.toggle('reset-page-number');
                btnResetPage.classList.toggle('active', isActive);
                saveArticle(activeArticle);
            }
            return;
        }

        var delBtn = e.target.closest('.editor-badge .del-btn');
        if (delBtn) {
            e.preventDefault();
            e.stopPropagation();
            var badge = delBtn.closest('.editor-badge');
            if (badge) {
                var parent = badge.parentNode;
                if (parent) {
                    var art = parent.closest('div[contenteditable]');
                    
                    // CASO ESPECIAL: Si es el tag principal 'article', eliminar toda la página (ajax)
                    if (parent.tagName.toLowerCase() === 'article') {
                        if (art) {
                            var idu = art.getAttribute('data-idu');
                            if (idu) {
                                if (confirm('¿De verdad quieres eliminar esta página?')) {
                                    var fd = new FormData();
                                    fd.append('idu', idu);
                                    fd.append('action', 'regla_eliminar');
                                    
                                    setSaveStatus('saving', '● eliminando página…');
                                    
                                    fetch(window.location.pathname, {
                                        method: 'POST',
                                        body: fd,
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                    })
                                    .then(function (r) {
                                        if (!r.ok) throw new Error('HTTP ' + r.status);
                                        return r.json();
                                    })
                                    .then(function (res) {
                                        if (res.success) {
                                            setSaveStatus('saved', '✓ página eliminada');
                                            
                                            // Eliminar del select de páginas
                                            var pageSelect = document.querySelector(SEL_PAGE_SELECT);
                                            if (pageSelect) {
                                                var opt = pageSelect.querySelector('option[value="' + idu + '"]');
                                                if (opt) opt.remove();
                                            }
                                            
                                            art.remove();
                                            
                                            // Seleccionar otra página activa
                                            var remainingPages = document.querySelectorAll(SEL_ARTICLE);
                                            if (remainingPages.length > 0) {
                                                activeArticle = remainingPages[0];
                                                if (pageSelect) pageSelect.value = activeArticle.getAttribute('data-idu');
                                                window.location.hash = activeArticle.getAttribute('data-idu');
                                                placeCursorInside(activeArticle);
                                            } else {
                                                activeArticle = null;
                                                if (pageSelect) pageSelect.value = '';
                                                window.location.hash = '';
                                            }
                                            
                                            // Recalcular los números de página de las que quedan
                                            var pages = document.querySelectorAll(SEL_ARTICLE);
                                            pages.forEach(function (page, idx) {
                                                page.setAttribute('data-page', idx + 1);
                                            });
                                            updateBreadcrumb();
                                        } else {
                                            setSaveStatus('error', '✗ error: ' + (res.error || 'al eliminar página'));
                                        }
                                    })
                                    .catch(function (error) {
                                        setSaveStatus('error', '✗ ' + (error && error.message ? error.message : 'sin conexión'));
                                    });
                                }
                            }
                        }
                        return;
                    }
                    
                    parent.remove();
                    if (art) {
                        syncHelpers(art);
                        scheduleSave(art);
                        updateBreadcrumb();
                    }
                }
            }
            return;
        }

        var trigger = e.target.closest('.grupo-trigger');
        if (trigger) {
            e.preventDefault();
            var grupo = trigger.closest('.pinceles-grupo');
            if (grupo) {
                var isActive = grupo.classList.contains('active');
                
                // Cerrar todos los grupos
                var todosLosGrupos = document.querySelectorAll('.pinceles-grupo');
                todosLosGrupos.forEach(function (g) {
                    g.classList.remove('active');
                });
                
                // Si no estaba activo, lo abrimos
                if (!isActive) {
                    grupo.classList.add('active');
                }
            }
            return;
        }

        var btn = e.target.closest(SEL_PINCEL_BTN);
        if (btn) {
            var tag = btn.getAttribute('data-tag');
            if (!tag) return;

            e.preventDefault();

            if (tag === 'clear') {
                document.execCommand('removeFormat', false, null);
                return;
            }

            // CASO ESPECIAL: Crear una página nueva en blanco (PAG / article)
            if (tag === 'article') {
                var refIdu = '';
                var position = 'despues';
                
                // Intentar recuperar el artículo activo en base al select superior si es null
                if (!activeArticle) {
                    var pageSelect = document.querySelector(SEL_PAGE_SELECT);
                    if (pageSelect && pageSelect.value) {
                        var art = document.querySelector(SEL_ARTICLE + '[data-idu="' + pageSelect.value + '"]');
                        if (art) {
                            activeArticle = art;
                        }
                    }
                }
                
                // Si sigue siendo null, intentar usar el último artículo en el DOM (punto de partida)
                if (!activeArticle) {
                    var pages = document.querySelectorAll(SEL_ARTICLE);
                    if (pages.length > 0) {
                        activeArticle = pages[pages.length - 1];
                    }
                }
                
                if (activeArticle) {
                    refIdu = activeArticle.getAttribute('data-idu') || '';
                    var posSelect = document.querySelector(SEL_POS_SELECT);
                    position  = posSelect ? posSelect.value : 'despues';
                    
                    // Si está en 'dentro', forzar a 'despues'
                    if (position === 'dentro') {
                        if (posSelect) posSelect.value = 'despues';
                        position = 'despues';
                    }
                }
                
                var mainEl = document.querySelector('main[data-manuales-idu]');
                var manualesIdu = mainEl ? mainEl.getAttribute('data-manuales-idu') : '';
                
                if (!manualesIdu) return;
                
                var fd = new FormData();
                fd.append('manuales_idu', manualesIdu);
                fd.append('referencia_idu', refIdu);
                fd.append('posicion', position);
                fd.append('action', 'regla_crear_ajax');
                
                setSaveStatus('saving', '● creando página…');
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (res) {
                    if (res.success) {
                        setSaveStatus('saved', '✓ página creada');
                        
                        var newDiv = document.createElement('div');
                        newDiv.id = res.idu;
                        newDiv.contentEditable = "true";
                        newDiv.spellCheck = false;
                        newDiv.setAttribute('data-idu', res.idu);
                        newDiv.innerHTML = '<article id="pag-' + res.idu + '"></article>' +
                            '<a class="button-floating scroll-down-hide" contenteditable="false" data-ajax=".ajax.show" ' +
                            'href="/ev/manuales/formulario_regla/' + manualesIdu + '/' + res.idu + '" ' +
                            'title="Editar en formulario"><img alt="Edit" loading="lazy" src="/img/icons/edit.svg" width="30" height="30"></a>';
                        
                        if (activeArticle) {
                            if (position === 'antes') {
                                activeArticle.parentNode.insertBefore(newDiv, activeArticle);
                            } else {
                                activeArticle.parentNode.insertBefore(newDiv, activeArticle.nextSibling);
                            }
                        } else {
                            var mainContainer = document.querySelector('main[data-manuales-idu]');
                            if (mainContainer) {
                                mainContainer.appendChild(newDiv);
                            }
                        }
                        
                        activeArticle = newDiv;
                        placeCursorInside(newDiv);
                        syncHelpers(newDiv);
                        recalcularPaginas(res.idu, res.nombre, refIdu, position);
                        updateBreadcrumb();
                    } else {
                        setSaveStatus('error', '✗ error: ' + (res.error || 'al crear página'));
                    }
                })
                .catch(function (error) {
                    setSaveStatus('error', '✗ ' + (error && error.message ? error.message : 'sin conexión'));
                });
                return;
            }

            var def = null;
            for (var i = 0; i < TAGS.length; i++) {
                if (TAGS[i].tag === tag) { def = TAGS[i]; break; }
            }
            if (!def) return;

            insertTag(def);
            return;
        }

        var crumbBtn = e.target.closest(SEL_BREADCRUMB + ' button[data-index]');
        if (crumbBtn) {
            var idx = parseInt(crumbBtn.getAttribute('data-index'), 10);
            if (isNaN(idx) || idx < 0 || idx >= currentCrumbs.length) return;

            var targetEl = currentCrumbs[idx];
            if (!targetEl) return;

            e.preventDefault();

            if (activeArticle) {
                var art = targetEl.closest('div[contenteditable]');
                if (art) art.focus();

                var txt = document.createTextNode('\u200B');
                targetEl.appendChild(txt);

                var sel = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(txt);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
                savedRange = range.cloneRange();
            }
            updateBreadcrumb();
        }
    });

    /* -----------------------------------------------------------------
       SELECT DE PÁGINAS → scroll a la página
    ----------------------------------------------------------------- */
    document.addEventListener('change', function (e) {
        if (!e.target.matches(SEL_PAGE_SELECT)) return;
        var idu = e.target.value;
        if (idu) {
            // Guardar inmediatamente la página anterior si tenía cambios pendientes
            if (activeArticle) {
                var activeIdu = activeArticle.getAttribute('data-idu');
                if (activeIdu && debounceTimers[activeIdu]) {
                    clearTimeout(debounceTimers[activeIdu]);
                    delete debounceTimers[activeIdu];
                    saveArticle(activeArticle);
                }
            }

            window.location.hash = idu;
            var art = document.querySelector(SEL_ARTICLE + '[data-idu="' + idu + '"]');
            if (art) {
                activeArticle = art;
                placeCursorInside(art);
                updateBreadcrumb();
            }
        }
    });

    /* -----------------------------------------------------------------
       RASTREAR ARTÍCULO ACTIVO
    ----------------------------------------------------------------- */
    document.addEventListener('focusin', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            // Garantizar que styleWithCSS está desactivado cada vez que el editable recibe foco,
            // ya que algunos navegadores lo resetean al cambiar el elemento enfocado.
            try {
                document.execCommand('styleWithCSS', false, false);
                document.execCommand('defaultParagraphSeparator', false, 'p');
            } catch (ex) {}

            activeArticle = art;
            var pageSelect = document.querySelector(SEL_PAGE_SELECT);
            if (pageSelect && art.getAttribute('data-idu')) {
                pageSelect.value = art.getAttribute('data-idu');
            }
            var innerArt = art.querySelector('article');
            if (innerArt) {
                var btnNoPage = document.querySelector('[data-toggle="no-page-number"]');
                if (btnNoPage) btnNoPage.classList.toggle('active', innerArt.classList.contains('no-page-number'));
                var btnResetPage = document.querySelector('[data-toggle="page-1"]');
                if (btnResetPage) btnResetPage.classList.toggle('active', innerArt.classList.contains('reset-page-number'));
            }
        }
    });

    document.addEventListener('mousedown', function (e) {
        if (!activeArticle) return;
        var target = e.target;
        // Si el elemento ha sido removido o desconectado del DOM (ej: al redibujar el breadcrumb
        // o el acordeón al hacer clic), asumimos interacción con el editor y no guardamos de inmediato.
        if (!document.documentElement.contains(target)) {
            return;
        }

        var dentroDelEditor = target.closest('div[contenteditable]') ||
                              target.closest(SEL_NAV_PINCELES) ||
                              target.closest(SEL_NAV_CONTEXTO) ||
                              target.closest('aside.editor') ||
                              target.closest('.button-floating');
                              
        // Forzar el cursor dentro de elementos vacíos al hacer clic con el ratón
        var emptyHelper = target.closest('[data-empty-helper="true"]');
        if (emptyHelper && emptyHelper === target) {
            e.preventDefault(); // Evitar el comportamiento erróneo nativo
            placeCursorInside(emptyHelper);
            updateBreadcrumb();
            return;
        }

        // Si se hace clic directamente en el <article> (en su padding/espacio vacío), 
        // forzar el cursor al final de este para que no salte al hijo más cercano.
        if (target.tagName && target.tagName.toLowerCase() === 'article') {
            e.preventDefault();
            var sel = window.getSelection();
            if (sel) {
                var range = document.createRange();
                range.selectNodeContents(target);
                range.collapse(false); // Mover el cursor al final
                sel.removeAllRanges();
                sel.addRange(range);
                updateBreadcrumb();
                
                // Asegurarnos de que el contenteditable mantenga el foco para la caja-sombra
                var ce = target.closest('div[contenteditable]');
                if (ce) ce.focus();
            }
            return;
        }
                              
        if (!dentroDelEditor) {
            var idu = activeArticle.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                clearTimeout(debounceTimers[idu]);
                delete debounceTimers[idu];
                saveArticle(activeArticle);
            }
        }
    });

    window.addEventListener('beforeunload', function () {
        if (activeArticle) {
            var idu = activeArticle.getAttribute('data-idu');
            if (idu && debounceTimers[idu]) {
                saveArticle(activeArticle);
            }
        }
    });

    /* -----------------------------------------------------------------
       GUARDADO AUTOMÁTICO POR DEBOUNCE
    ----------------------------------------------------------------- */
    function setSaveStatus(status, msg) {
        var el = document.querySelector(SEL_OUTPUT);
        if (!el) return;
        
        // Silenciar por completo los avisos visuales (excepto errores)
        if (status !== 'error') {
            el.removeAttribute('data-status');
            el.textContent = '';
            return;
        }

        el.setAttribute('data-status', status);
        el.textContent = msg;

        var plainMsg = msg.replace(/^[✗●✓]\s*/, '');
        var lowerMsg = plainMsg.toLowerCase();

        if (lowerMsg === 'sin conexión') {
            plainMsg = 'No se ha podido conectar con el servidor. Comprueba tu conexión a internet.';
        } else if (lowerMsg.indexOf('403') !== -1) {
            plainMsg = 'No tienes permisos de edición en este manual.';
        } else if (lowerMsg.indexOf('500') !== -1 || lowerMsg.indexOf('http') !== -1 || lowerMsg.indexOf('error') !== -1) {
            plainMsg = 'El editor ha tenido un problema grave. Por favor, avisa al administrador detallando en qué momento se ha producido este error.';
        } else if (lowerMsg.indexOf('crear página') !== -1) {
            plainMsg = 'No se ha podido crear la nueva página en el servidor.';
        } else if (lowerMsg.indexOf('guardar') !== -1 || lowerMsg.indexOf('guardado') !== -1) {
            plainMsg = 'No se han podido guardar tus cambios en el servidor.';
        }

        if (typeof window.editorToast === 'function') {
            window.editorToast(plainMsg, 'error');
        }
    }

    function scheduleSave(articleEl) {
        var idu = articleEl.getAttribute('data-idu');
        if (!idu) return;
        isDirty[idu] = true;
        clearTimeout(debounceTimers[idu]);
        clearInterval(countdownTimers[idu]);
        
        setSaveStatus('saving', '● guardando…');

        var btn = document.querySelector('button[data-action="save-page"]');
        var countdownEl = btn ? btn.querySelector('.save-countdown') : null;
        var iconEl = btn ? btn.querySelector('.save-icon') : null;
        
        if (btn) btn.classList.remove('active');
        if (countdownEl) {
            countdownEl.style.display = 'inline-block';
            countdownEl.textContent = '8';
        }
        if (iconEl) iconEl.style.display = 'none';

        var seconds = 8;
        countdownTimers[idu] = setInterval(function() {
            seconds--;
            if (seconds > 0) {
                if (countdownEl) countdownEl.textContent = seconds;
            } else {
                clearInterval(countdownTimers[idu]);
                if (countdownEl) countdownEl.style.display = 'none';
                if (iconEl) iconEl.style.display = 'inline-block';
                if (btn) btn.classList.add('active');
            }
        }, 1000);

        debounceTimers[idu] = setTimeout(function () {
            saveArticle(articleEl);
        }, 8000);
    }

    function saveArticle(articleEl) {
        var idu = articleEl.getAttribute('data-idu');
        if (!idu) return Promise.resolve();

        if (savePromises[idu]) {
            return savePromises[idu];
        }

        delete isDirty[idu];

        if (debounceTimers[idu]) {
            clearTimeout(debounceTimers[idu]);
            delete debounceTimers[idu];
        }
        if (countdownTimers[idu]) {
            clearInterval(countdownTimers[idu]);
            delete countdownTimers[idu];
        }

        var btn = document.querySelector('button[data-action="save-page"]');
        if (btn) {
            btn.classList.add('active');
            var countdownEl = btn.querySelector('.save-countdown');
            var iconEl = btn.querySelector('.save-icon');
            if (countdownEl) countdownEl.style.display = 'none';
            if (iconEl) iconEl.style.display = 'inline-block';
        }

        var mainEl      = document.querySelector('main[data-manuales-idu]');
        var manualesIdu = mainEl ? mainEl.getAttribute('data-manuales-idu') : '';

        var clone = articleEl.cloneNode(true);
        
        // 1. Eliminar el botón de edición
        var editBtn = clone.querySelector('a.button-floating.scroll-down-hide');
        if (editBtn) editBtn.remove();
        
        // 2. Eliminar todos los badges y helpers del editor antes de guardar
        clone.querySelectorAll('[data-editor-helper]').forEach(function (helper) {
            helper.remove();
        });
        
        // 2b. Eliminar el atributo data-empty-helper
        clone.querySelectorAll('[data-empty-helper]').forEach(function (el) {
            el.removeAttribute('data-empty-helper');
        });
        
        var articleNode = clone.querySelector('article');
        var htmlToSend  = articleNode ? articleNode.outerHTML : clone.innerHTML;
        // Clean zero-width space characters (\u200B) used to stabilize contenteditable selection
        htmlToSend = htmlToSend.replace(/\u200B/g, '');

        var fd = new FormData();
        fd.append('idu',         idu);
        fd.append('descripcion', htmlToSend);
        fd.append('action',      'regla_actualizar_ajax');
        if (manualesIdu) fd.append('manuales_idu', manualesIdu);

        var p = fetch(window.location.pathname, {
            method: 'POST',
            body:   fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            keepalive: true
        }).then(function (r) {
            if (r.ok || r.status === 302) {
                setSaveStatus('saved', '✓ guardado');
            } else {
                setSaveStatus('error', '✗ error ' + r.status);
            }
        }).catch(function () {
            setSaveStatus('error', '✗ sin conexión');
        }).finally(function () {
            delete savePromises[idu];
        });

        savePromises[idu] = p;
        return p;
    }

    document.addEventListener('paste', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (!art) return;
        
        // No interferir con la subida de archivos/imágenes
        if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
            return;
        }
        
        e.preventDefault();
        var text = (e.originalEvent || e).clipboardData.getData('text/plain');
        
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            var range = sel.getRangeAt(0);
            range.deleteContents();
            
            var textNode = document.createTextNode(text);
            range.insertNode(textNode);
            
            range.setStartAfter(textNode);
            range.setEndAfter(textNode);
            sel.removeAllRanges();
            sel.addRange(range);
            
            savedRange = range.cloneRange();
            
            syncHelpers(art);
            scheduleSave(art);
            updateBreadcrumb();
        }
    });

    document.addEventListener('keydown', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (!art) return;

        /* ----- Enter: solo insertLineBreak, sin crear bloques nativos ----- */
        if (e.key === 'Enter') {
            e.preventDefault();
            document.execCommand('insertLineBreak');
            updateBreadcrumb();
            return;
        }

        /* ----- Backspace: bloquear fusión de bloques, permitir borrado seguro dentro ----- */
        if (e.key === 'Backspace') {
            // Con selección activa: el browser borra el texto seleccionado → seguro
            var sel = window.getSelection();
            if (sel && !sel.isCollapsed) return;

            if (sel && sel.rangeCount > 0) {
                var range = sel.getRangeAt(0);
                var node  = range.startContainer;
                var offset = range.startOffset;

                // En nodo de texto con offset > 0 → hay char antes → borrado normal
                if (node.nodeType === 3 && offset > 0) return;

                // Determinar qué nodo quedaría eliminado por Backspace
                var nodeToDelete = null;

                if (node.nodeType === 3 && offset === 0) {
                    // Retroceder por hermanos, saltando helpers y zwsp puros
                    var prev = node.previousSibling;
                    while (prev) {
                        if (prev.nodeType === 1 && prev.hasAttribute('data-editor-helper')) { prev = prev.previousSibling; continue; }
                        if (prev.nodeType === 3 && prev.textContent.replace(/\u200B/g, '') === '') { prev = prev.previousSibling; continue; }
                        nodeToDelete = prev;
                        break;
                    }
                } else if (node.nodeType === 1 && offset > 0) {
                    // Cursor en elemento: el hijo en posición offset-1 es lo que se borra
                    var child = node.childNodes[offset - 1];
                    if (child && !(child.nodeType === 1 && child.hasAttribute('data-editor-helper'))) {
                        nodeToDelete = child;
                    }
                }
                // offset === 0 en elemento: nada antes → nodeToDelete queda null → bloquear

                if (!nodeToDelete) {
                    // Nada borrable antes → Backspace cruzaría un límite de bloque
                    e.preventDefault();
                    return;
                }

                // Si el nodo a borrar es un elemento de bloque → bloquear fusión
                if (nodeToDelete.nodeType === 1 && BLOCK_TAGS_SET[nodeToDelete.tagName.toLowerCase()]) {
                    e.preventDefault();
                    return;
                }
                // Es <br> o elemento inline → dejar pasar (borrado seguro dentro del mismo bloque)
                return;
            }
            return;
        }

        /* ----- Delete: bloquear fusión de bloques en dirección contraria ----- */
        if (e.key === 'Delete') {
            var sel = window.getSelection();
            if (sel && !sel.isCollapsed) return;

            if (sel && sel.rangeCount > 0) {
                var range = sel.getRangeAt(0);
                var node  = range.startContainer;
                var offset = range.startOffset;

                if (node.nodeType === 3) {
                    var textLen = node.textContent.replace(/\u200B/g, '').length;
                    if (offset < textLen) return; // chars por delante → borrado normal

                    // Al final del nodo de texto: buscar el siguiente nodo con contenido
                    var nodeToDelete = null;
                    var next = node.nextSibling;
                    while (next) {
                        if (next.nodeType === 1 && next.hasAttribute('data-editor-helper')) { next = next.nextSibling; continue; }
                        if (next.nodeType === 3 && next.textContent.replace(/\u200B/g, '') === '') { next = next.nextSibling; continue; }
                        nodeToDelete = next;
                        break;
                    }

                    if (!nodeToDelete) { e.preventDefault(); return; }
                    if (nodeToDelete.nodeType === 1 && BLOCK_TAGS_SET[nodeToDelete.tagName.toLowerCase()]) {
                        e.preventDefault(); return;
                    }
                    return; // <br> o inline → dejar pasar
                }

                if (node.nodeType === 1) {
                    if (offset >= node.childNodes.length) { e.preventDefault(); return; }
                    var child = node.childNodes[offset];
                    if (child && child.nodeType === 1 && BLOCK_TAGS_SET[child.tagName.toLowerCase()]) {
                        e.preventDefault(); return;
                    }
                }
            }
            return;
        }
    });

    /* -----------------------------------------------------------------
       DRAG & DROP: bloquear arrastrar y soltar en editables
       (evita que el browser mueva o inserte contenido no solicitado)
    ----------------------------------------------------------------- */
    document.addEventListener('dragstart', function (e) {
        if (e.target.closest('div[contenteditable]')) {
            e.preventDefault();
        }
    });

    document.addEventListener('drop', function (e) {
        if (e.target.closest('div[contenteditable]')) {
            e.preventDefault();
        }
    });

    document.addEventListener('input', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            cleanSpuriousBr(art);
            syncHelpers(art);
            scheduleSave(art);
        }
    });

    document.addEventListener('keyup', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) syncHelpers(art);
    });

    document.addEventListener('focusout', function (e) {
        var art = e.target.closest('div[contenteditable]');
        if (art) {
            var idu = art.getAttribute('data-idu');
            if (idu && (debounceTimers[idu] || isDirty[idu])) {
                saveArticle(art);
            }
        }
    });

    /* -----------------------------------------------------------------
       INIT
    ----------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        // Desactivar inyección de CSS nativo del navegador (evitar spans con style=...)
        try {
            document.execCommand('styleWithCSS', false, false);
            document.execCommand('insertBrOnReturn', false, false);
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch (e) {}

        var arts = document.querySelectorAll(SEL_ARTICLE);
        arts.forEach(function (art) {
            syncHelpers(art);
        });
        if (arts.length > 0) activeArticle = arts[0];

        // Cargar preferencia de guías visuales globales
        try {
            var toggleBtn = document.querySelector('[data-toggle="guias"]');
            if (localStorage.getItem('editor-show-all-outlines') === '1') {
                var mainEl = document.querySelector('main');
                if (mainEl) mainEl.classList.add('show-all-outlines');
                if (toggleBtn) toggleBtn.classList.add('active');
            } else {
                if (toggleBtn) toggleBtn.classList.remove('active');
            }
        } catch (e) {}

        // Abrir el primer grupo de pinceles por defecto
        var primerGrupo = document.querySelector('.pinceles-grupo');
        if (primerGrupo) {
            primerGrupo.classList.add('active');
        }
    });

    /* -----------------------------------------------------------------
       GUARDADO INMEDIATO DE CAMBIOS PENDIENTES
    ----------------------------------------------------------------- */
    window.flushPendingSaves = function () {
        var promises = [];
        
        // 1. Promesas de guardado en vuelo (ej: iniciadas por focusout)
        Object.keys(savePromises).forEach(function (idu) {
            if (savePromises[idu]) {
                promises.push(savePromises[idu]);
            }
        });

        // 2. Artículos con temporizador o marcados como sucios (isDirty)
        var articles = document.querySelectorAll(SEL_ARTICLE);
        articles.forEach(function (art) {
            var idu = art.getAttribute('data-idu');
            if (idu && (debounceTimers[idu] || isDirty[idu])) {
                promises.push(saveArticle(art));
            }
        });

        return Promise.all(promises);
    };

    // Interceptar el mousedown en el botón de edición para forzar guardado antes del focusout
    document.addEventListener('mousedown', function (e) {
        var editFormBtn = e.target.closest('.button-floating[href*="formulario"], a[href*="formulario_regla"]');
        if (editFormBtn) {
            window.flushPendingSaves();
        }
    });

    document.addEventListener('click', function(e) {
        var saveBtn = e.target.closest('button[data-action="save-page"]');
        if (saveBtn) {
            window.flushPendingSaves();
        }
    });

})(window, document);

