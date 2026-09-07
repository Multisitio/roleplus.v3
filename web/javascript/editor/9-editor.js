(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var pagesCount = document.querySelectorAll('main article').length;
        var span = document.querySelector('aside .pages h4 span');
        if (span) span.textContent = pagesCount;

        if (typeof window.initPSColorPicker === 'function') {
            window.initPSColorPicker();
        }

        // Inicializar drag and drop para ordenar reglas
        var elList = document.getElementById('menu-reglas-sortable');
        if (elList && typeof Sortable !== 'undefined') {
            Sortable.create(elList, {
                animation: 150,
                onEnd: function (evt) {
                    var order = [];
                    elList.querySelectorAll('li').forEach(function (li, idx) {
                        var idu = li.getAttribute('data-idu');
                        if (idu) {
                            order.push(idu);
                            // Actualizar visualmente el peso en el sup
                            var sup = li.querySelector('.regla-peso');
                            if (sup) {
                                sup.textContent = idx;
                            }
                        }
                    });

                    // Enviar orden al servidor por AJAX
                    var fd = new FormData();
                    order.forEach(function (idu, idx) {
                        fd.append('orden[' + idx + ']', idu);
                    });

                    fetch('/ev/manuales/ordenar_reglas/', {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data.success) {
                                console.error('Error al ordenar reglas:', data.error);
                            }
                        })
                        .catch(function (err) {
                            console.error('Error de red al ordenar reglas:', err);
                        });

                    // Reordenar dinámicamente los divs de la plantilla principal
                    var main = document.querySelector('main.plantilla');
                    if (main) {
                        order.forEach(function (idu) {
                            var link = main.querySelector('a[href$="/' + idu + '"]');
                            if (link) {
                                var div = link.parentNode;
                                if (div && div.parentNode === main) {
                                    main.appendChild(div);
                                }
                            }
                        });
                    }
                }
            });
        }
    });

    document.body.addEventListener('input', function (e) {
        if (e.target.matches('[name="pages_filter"]')) {
            var val = e.target.value;
            var divs = document.querySelectorAll('main > div');

            // Asegurar que tengan el índice original antes de filtrar
            divs.forEach(function (div, i) {
                if (!div.getAttribute('data-original-index')) {
                    div.setAttribute('data-original-index', i);
                }
            });

            var set = null;
            var cleanVal = val.trim();
            if (cleanVal) {
                set = new Set();
                var parts = cleanVal.split(',');
                parts.forEach(function (part) {
                    part = part.trim();
                    if (part.indexOf('-') !== -1) {
                        var range = part.split('-');
                        var start = parseInt(range[0], 10);
                        var end = parseInt(range[1], 10);
                        if (!isNaN(start) && !isNaN(end)) {
                            var min = Math.min(start, end);
                            var max = Math.max(start, end);
                            for (var p = min; p <= max; p++) {
                                set.add(p - 1);
                            }
                        }
                    } else {
                        var p = parseInt(part, 10);
                        if (!isNaN(p)) {
                            set.add(p - 1);
                        }
                    }
                });
            }

            divs.forEach(function (div) {
                var originalIndex = parseInt(div.getAttribute('data-original-index'), 10);
                if (set === null || set.has(originalIndex)) {
                    div.style.display = '';
                } else {
                    div.style.display = 'none';
                }
            });

            var span = document.querySelector('aside .pages h4 span');
            if (span) {
                var visibleCount = 0;
                document.querySelectorAll('main article').forEach(function (art) {
                    if (art.offsetParent !== null) visibleCount++;
                });
                span.textContent = visibleCount;
            }
        } else if (e.target.matches('[name="start_page"]')) {
            var input = e.target;
            var startPage = parseInt(input.value, 10);
            var manualId = input.getAttribute('data-manual-id');
            var styleId = 'custom-pagination-style';
            var styleEl = document.getElementById(styleId);

            if (isNaN(startPage) || startPage < 2) {
                if (styleEl) styleEl.remove();
                if (manualId) localStorage.removeItem('manual_start_page_' + manualId);
                return;
            }

            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = styleId;
                document.head.appendChild(styleEl);
            }

            var hideCount = startPage - 1;
            styleEl.innerHTML = 
                'body { counter-reset: page -' + hideCount + ' !important; }\n' +
                'main > div:nth-child(-n+' + hideCount + ') footer::after { content: none !important; }';

            if (manualId) localStorage.setItem('manual_start_page_' + manualId, startPage);
            
            if (document.body.classList.contains('booklet-mode')) {
                var bookletBtn = document.querySelector('.booklet-toggle');
                if (bookletBtn) {
                    bookletBtn.click();
                    setTimeout(function(){ bookletBtn.click(); }, 10);
                }
            }
        }
    });

    (function initStartPage() {
        var input = document.querySelector('[name="start_page"]');
        if (input) {
            var manualId = input.getAttribute('data-manual-id');
            if (manualId) {
                var saved = localStorage.getItem('manual_start_page_' + manualId);
                if (saved) {
                    input.value = saved;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        }
    })();

    document.body.addEventListener('click', function (e) {
        var t = e.target;

        var printBtn = t.closest('.print');
        if (printBtn) { window.print(); return; }

        var bookletBtn = t.closest('.booklet-toggle');
        if (bookletBtn) {
            var isBooklet = document.body.classList.toggle('booklet-mode');
            var styleEl = document.getElementById('page-style');
            var printFmt = bookletBtn.getAttribute('data-print') || 'A5';
            var bookletFmt = bookletBtn.getAttribute('data-booklet') || 'A4 landscape';
            if (styleEl) { styleEl.innerHTML = isBooklet ? '@page { size: ' + bookletFmt + '; margin: 0; }' : '@page { size: ' + printFmt + '; margin: 0; }'; }

            var container = document.querySelector('.plantilla');
            if (!container) return;

            if (isBooklet) {
                var baseOffset = 0;
                var startPageInput = document.querySelector('[name="start_page"]');
                if (startPageInput) {
                    var val = parseInt(startPageInput.value, 10);
                    if (!isNaN(val) && val >= 1) baseOffset = val - 1;
                }

                var pages = Array.from(container.children);
                pages.forEach(function (page, i) {
                    if (!page.getAttribute('data-original-index')) {
                        page.setAttribute('data-original-index', i);
                    }
                    // Forzar el número de página original mediante counter-reset
                    var orig = parseInt(page.getAttribute('data-original-index'), 10);
                    page.style.setProperty('counter-reset', 'page ' + (orig - baseOffset));
                });

                var N = pages.length;
                var order = [];
                for (var i = 0; i < N / 2; i++) {
                    if (i % 2 === 0) {
                        order.push(N - 1 - i);
                        order.push(i);
                    } else {
                        order.push(i);
                        order.push(N - 1 - i);
                    }
                }
                order.forEach(function (index) {
                    if (pages[index]) {
                        container.appendChild(pages[index]);
                    }
                });
                bookletBtn.classList.add('active');
            } else {
                var allPages = Array.from(container.children);
                allPages.sort(function (a, b) {
                    return parseInt(a.getAttribute('data-original-index') || 0) - parseInt(b.getAttribute('data-original-index') || 0);
                });
                allPages.forEach(function (p) {
                    p.style.removeProperty('counter-reset');
                    container.appendChild(p);
                });
                bookletBtn.classList.remove('active');
            }
        }

        var kdpBtn = t.closest('.kdp-toggle');
        if (kdpBtn) {
            var isKdp = document.body.classList.toggle('kdp-mode');
            var styleEl = document.getElementById('page-style');
            var printFmt = kdpBtn.getAttribute('data-print') || 'A5';

            if (styleEl) {
                if (isKdp) {
                    styleEl.removeAttribute('media');
                    styleEl.innerHTML = '@page { size: ' + printFmt + ' !important; margin: 0; } * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } main > div:not(.cover) > article { transform: scale(0.86) !important; transform-origin: center center !important; } main > div.cover { transform: scale(1) !important; }';
                    kdpBtn.classList.add('active');
                } else {
                    styleEl.setAttribute('media', 'print');
                    styleEl.innerHTML = '@page { size: ' + printFmt + '; margin: 0; }';
                    kdpBtn.classList.remove('active');
                }
            }
        }

    });

    // Gestión de bloqueo de scroll para Aside, Modales y Overlays
    (function () {
        function updateScrollLock() {
            var asideOpen = false;
            var modalOpen = false;

            // Aside del editor
            var aside = document.querySelector('aside.template');
            if (aside && !aside.classList.contains('hide')) {
                asideOpen = true;
            }

            // Cualquier .modal o .overlay visible
            var candidates = document.querySelectorAll('.modal, .overlay');
            for (var i = 0; i < candidates.length; i++) {
                var el = candidates[i];
                var cs = getComputedStyle(el);
                if (cs.display !== 'none' && cs.visibility !== 'hidden' && !el.classList.contains('hide')) {
                    modalOpen = true;
                    break;
                }
            }

            // Color picker
            var psp = document.getElementById('ps-color-picker');
            if (psp && psp.style.display === 'block') {
                modalOpen = true;
            }

            document.body.classList.toggle('aside-open', asideOpen);
            document.body.classList.toggle('modal-open', modalOpen);
        }

        // Observar todo el documento para cambios de atributos class/style
        // y de añadido/eliminado de nodos (modales AJAX)
        var scrollObserver = new MutationObserver(updateScrollLock);
        scrollObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'style'],
            subtree: true,
            childList: true
        });

        updateScrollLock();
    })();


    /**
     * Función genérica para guardar ajustes de plantilla vía AJAX
     */
    window.guardarPlantillaAJAX = function (form, data_extra, callback) {
        if (!form) form = document.getElementById('form-manual-estilos');
        if (!form) return;

        var fd = new FormData(form);
        if (data_extra) {
            for (var key in data_extra) {
                fd.set(key, data_extra[key]);
            }
        }

        fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!r.ok) throw new Error('Error ' + r.status);
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Respuesta no válida del servidor'); }
            });
        }).then(function (data) {
            var link = document.getElementById('estilos-personalizados');
            if (link && data.css_url) {
                link.onload = function () {
                    var f = document.getElementById('live-footer-fix'); if (f) f.remove();
                    var t = document.getElementById('live-typography-fix'); if (t) t.remove();
                };
                link.href = data.css_url;
            }
            var inlineCss = document.getElementById('estilos-personalizados-inline');
            if (inlineCss && data.css_inline) {
                inlineCss.textContent = data.css_inline;
            }

            if (data.fuentes) {
                for (var niv in data.fuentes) {
                    var f = data.fuentes[niv];
                    var section = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
                    if (!section) continue;

                    var linkMenu = section.querySelector('a');
                    if (linkMenu) {
                        if (f.fuente) linkMenu.style.fontFamily = "'" + f.fuente + "'";
                        linkMenu.style.fontSize = f.size || '';
                        linkMenu.style.fontWeight = f.weight || 'normal';
                        linkMenu.style.fontStyle = f.style || 'normal';
                        linkMenu.style.textAlign = f.align || 'left';
                        linkMenu.style.textTransform = f.transform === 'small-caps' ? 'none' : (f.transform || 'none');
                        linkMenu.style.fontVariant = f.transform === 'small-caps' ? 'small-caps' : 'normal';
                        linkMenu.style.textDecoration = f.decoration && f.decoration !== '0' ? 'underline' : 'none';
                        if (f.color) linkMenu.style.color = f.color;
                        linkMenu.textContent = niv.toUpperCase() + ': ' + (f.fuente || 'Sin seleccionar');
                    }

                    var fontInput = section.querySelector('input[name="fuente_' + niv + '"]');
                    if (fontInput) fontInput.value = f.fuente;

                    var sizeSelect = section.querySelector('select[name="size_' + niv + '"]');
                    if (sizeSelect) sizeSelect.value = f.size;

                    var variantSelect = section.querySelector('select[name="variant_' + niv + '"]');
                    if (variantSelect) {
                        var isBold = f.weight === 'bold';
                        var isItalic = f.style === 'italic';
                        variantSelect.value = isBold && isItalic ? 'bold-italic' : (isBold ? 'bold' : (isItalic ? 'italic' : ''));
                    }

                    var alignSelect = section.querySelector('select[name="align_' + niv + '"]');
                    if (alignSelect) alignSelect.value = f.align || 'left';

                    var transformSelect = section.querySelector('select[name="transform_' + niv + '"]');
                    if (transformSelect) transformSelect.value = f.transform || 'none';

                    var decorationSelect = section.querySelector('select[name="decoration_' + niv + '"]');
                    if (decorationSelect) decorationSelect.value = f.decoration || '0';

                    var colorInput = section.querySelector('input[name="color_' + niv + '"]');
                    if (colorInput) colorInput.value = f.color;

                    var colorPicker = section.querySelector('.web-color-picker');
                    if (colorPicker) colorPicker.style.backgroundColor = f.color;
                }
            }

            if (data.toast && typeof window.spawnToasts === 'function') {
                window.spawnToasts(data.toast);
            }

            if (typeof callback === 'function') callback(data);
        }).catch(function (err) {
            console.error('Error AJAX:', err);
            if (typeof window.editorToast === 'function') {
                window.editorToast('Error al guardar ajustes: ' + err.message, 'error');
            }
        });
    };

    /**
     * Listener para el nuevo Color Picker Universal
     */
    document.addEventListener('ps-color-saved', function (e) {
        var hex = e.detail.hex;
        var target = e.detail.target;
        var section = target.closest('section');
        if (section && section.dataset.nivel) {
            var niv = section.dataset.nivel;

            // Actualización al vuelo
            var preview = document.querySelector('main.plantilla');
            if (preview) {
                preview.style.setProperty('--' + niv + '-color', hex);
            }
            var linkMenu = section.querySelector('a');
            if (linkMenu) {
                linkMenu.style.color = hex;
            }
            var picker = section.querySelector('.web-color-picker');
            if (picker) {
                picker.style.backgroundColor = hex;
            }

            var colorInput = section.querySelector('input[name="color_' + niv + '"]');
            if (colorInput) {
                colorInput.value = hex;
            }

            var dataExtra = {};
            dataExtra['color_' + niv] = hex;
            window.guardarPlantillaAJAX(section.closest('form'), dataExtra);
        }
    });

    document.addEventListener('toggle', function (e) {
        var details = e.target.closest ? e.target.closest('aside.template details.template-panel') : null;
        if (!details || !details.open) return;

        var form = details.closest('form');
        if (form) {
            var all = form.querySelectorAll('details.template-panel[open]');
            for (var i = 0; i < all.length; i++) {
                if (all[i] !== details) all[i].open = false;
            }
        }
    }, true);


    var debounceTimer;
    ['change', 'input'].forEach(function (evt) {
        Kumbia.utils.on(evt, 'aside.template select[data-change-ajax], aside.template input[data-change-ajax]', function (e) {
            var $el = this, name = $el.name, val = $el.value;

            // 1. Actualización visual inmediata (sin esperar a BD)
            var preview = document.querySelector('main.plantilla');
            if (!preview) return;

            if (name.indexOf('footer_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                preview.style.setProperty(prop, val + (isNaN(val) ? '' : 'px'));
            } else if (name.indexOf('margin_') === 0 || name.indexOf('padding_') === 0) {
                var parts = name.split('_');
                var prop = '--' + parts[1] + '-' + parts[0] + '-' + parts[2];
                preview.style.setProperty(prop, val + 'px');
            } else if (name === 'bullet_ul') {
                preview.style.setProperty('--list-style-bullet', "'" + val + " '");
            } else if (name === 'bullet_ul_ul') {
                preview.style.setProperty('--list-style-sub-bullet', "'" + val + " '");
            } else if (name === 'bullet_checkbox') {
                preview.style.setProperty('--list-style-checkbox', "'" + val + " '");
            } else if (name === 'table_zebra_opacity') {
                var opacity = parseInt(val, 10) || 0;
                var colorEl = document.getElementById('table_zebra_color');
                var color = colorEl ? colorEl.value : 'transparent';
                if (opacity > 0 && color && color !== 'transparent') {
                    preview.style.setProperty('--table-zebra-bg', 'color-mix(in srgb, ' + color + ' ' + opacity + '%, transparent)');
                } else {
                    preview.style.setProperty('--table-zebra-bg', 'transparent');
                }
            } else if (name === 'table_zebra_color') {
                var opacityEl = document.getElementsByName('table_zebra_opacity')[0];
                var opacity = opacityEl ? (parseInt(opacityEl.value, 10) || 0) : 0;
                if (opacity > 0 && val && val !== 'transparent') {
                    preview.style.setProperty('--table-zebra-bg', 'color-mix(in srgb, ' + val + ' ' + opacity + '%, transparent)');
                } else {
                    preview.style.setProperty('--table-zebra-bg', 'transparent');
                }
            } else if (name.indexOf('table_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                var suffix = '';
                if (name.endsWith('_width') || name.endsWith('_padding') || name.endsWith('_x') || name.endsWith('_y') || name.endsWith('_gap')) {
                    suffix = isNaN(val) || val === '' ? '' : 'px';
                }
                preview.style.setProperty(prop, val + suffix);
            } else if (name === 'p_gap') {
                preview.style.setProperty('--p-gap', val + 'px');
            } else if (name.indexOf('blockquote_') === 0) {
                var prop = '--' + name.replace(/_/g, '-');
                var suffix = '';
                if (name.endsWith('_width')) {
                    suffix = isNaN(val) || val === '' ? '' : 'px';
                }
                preview.style.setProperty(prop, val + suffix);
            } else if (name.indexOf('_h') !== -1 || name.indexOf('_body') !== -1 || name.indexOf('_small') !== -1 || name.indexOf('_dropcap') !== -1) {
                // Tipografía: size_h1, variant_h1, align_h1, transform_h1, color_h1, size_dropcap, color_dropcap, etc.
                var parts = name.split('_');
                var propType = parts[0];
                var level = parts[1];

                // Actualizar el documento
                if (propType === 'size') preview.style.setProperty('--' + level + '-size', val);
                if (propType === 'color') preview.style.setProperty('--' + level + '-color', val);
                if (propType === 'align') preview.style.setProperty('--' + level + '-align', val);
                if (propType === 'variant') {
                    preview.style.setProperty('--' + level + '-weight', val.indexOf('bold') !== -1 ? 'bold' : 'normal');
                    preview.style.setProperty('--' + level + '-style', val.indexOf('italic') !== -1 ? 'italic' : 'normal');
                }
                if (propType === 'transform') {
                    preview.style.setProperty('--' + level + '-transform', val === 'small-caps' ? 'none' : val);
                    preview.style.setProperty('--' + level + '-variant', val === 'small-caps' ? 'small-caps' : 'normal');
                }
                if (propType === 'decoration') {
                    preview.style.setProperty('--' + level + '-decoration', val);
                }

                // Actualizar la previsualización en el propio MENÚ
                var section = $el.closest('section[data-nivel]');
                if (section) {
                    var linkMenu = section.querySelector('a');
                    if (linkMenu) {
                        if (propType === 'size') linkMenu.style.fontSize = val;
                        if (propType === 'color') linkMenu.style.color = val;
                        if (propType === 'align') linkMenu.style.textAlign = val;
                        if (propType === 'variant') {
                            linkMenu.style.fontWeight = val.indexOf('bold') !== -1 ? 'bold' : 'normal';
                            linkMenu.style.fontStyle = val.indexOf('italic') !== -1 ? 'italic' : 'normal';
                        }
                        if (propType === 'transform') {
                            linkMenu.style.textTransform = val === 'small-caps' ? 'none' : val;
                            linkMenu.style.fontVariant = val === 'small-caps' ? 'small-caps' : 'normal';
                        }
                        if (propType === 'decoration') {
                            linkMenu.style.textDecoration = val === '0' ? 'none' : 'underline';
                        }
                    }
                }
            }

            // 2. Debounce de dos segundos para el guardado real en BD
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var dataExtra = {};
                dataExtra[name] = val;
                window.guardarPlantillaAJAX($el.closest('form'), dataExtra);
            }, 2000);
        });
    });

    Kumbia.utils.on('change', 'aside.template .dropimage [type="file"]', function (e) {
        var form = this.closest('form'), drop = this.closest('.dropimage'), name = this.name;
        window.guardarPlantillaAJAX(form, null, function (data) {
            var url = name === 'footer_imagen' ? (data.url_footer || '') : (name === 'fondo_pergamino_even' ? (data.url_even || '') : (data.url_imagen || data.url || ''));
            if (url && drop) {
                // 1. Forzamos fondo en el contenedor
                drop.style.setProperty('background-image', 'url(' + url + '?t=' + Date.now() + ')', 'important');
                drop.style.setProperty('background-repeat', 'no-repeat', 'important');
                drop.style.setProperty('background-size', 'cover', 'important');
                drop.style.setProperty('background-position', 'center center', 'important');

                // 2. Si hay etiquetas <img> de previsualización (que no sean del botón de borrar), las actualizamos
                var imgs = drop.querySelectorAll('img:not(button img)');
                if (imgs.length > 0) {
                    imgs.forEach(function (img) {
                        img.src = url + '?t=' + Date.now();
                        img.style.setProperty('width', '100%', 'important');
                        img.style.setProperty('height', '100%', 'important');
                        img.style.setProperty('object-fit', 'cover', 'important');
                        img.style.setProperty('object-position', 'center', 'important');
                    });
                } else {
                    // Si no hay img, creamos una para previsualización
                    var newImg = document.createElement('img');
                    newImg.src = url + '?t=' + Date.now();
                    newImg.style.setProperty('width', '100%', 'important');
                    newImg.style.setProperty('height', '100%', 'important');
                    newImg.style.setProperty('object-fit', 'cover', 'important');
                    drop.appendChild(newImg);
                }

                drop.classList.add('dropnocontent');

                // Actualizar documento al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    var prop = name === 'footer_imagen' ? '--footer-imagen' : (name === 'fondo_pergamino_even' ? '--background-image-even' : '--background-image');
                    preview.style.setProperty(prop, "url('" + url + "')");
                }
            }
            var act = form.querySelector('[name="' + name + '_actual"]');
            if (act) act.value = url;
        });
    });

    Kumbia.utils.on('click', 'aside.template .dropimage button', function (e) {
        var form = this.closest('form'), drop = this.closest('.dropimage');
        if (drop) {
            var input = drop.querySelector('[type="file"]'), name = input ? input.name : '';
            drop.removeAttribute('style'); drop.classList.remove('dropnocontent'); drop.querySelectorAll(':scope > img').forEach(function (i) { i.remove(); });
            var act = name ? form.querySelector('[name="' + name + '_actual"]') : null;
            var extra = {};
            if (name) {
                extra[name] = '';
                extra[name + '_actual'] = act ? act.value : '';
            }
            window.guardarPlantillaAJAX(form, extra, function (data) { var act = form.querySelector('[name="' + name + '_actual"]'); if (act) act.value = ''; });
        }
    });

    document.addEventListener('click', function (e) {
        var it = e.target.closest('.font-item');
        if (it) {
            var niv = it.closest('.font-list-container').dataset.nivel;
            var sec = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
            if (sec) {
                var f = it.dataset.font;
                var inp = sec.querySelector('input[name="fuente_' + niv + '"]');
                if (inp) inp.value = f;

                // Actualización al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    preview.style.setProperty('--' + niv + '-family', "'" + f + "'");
                }
                var linkMenu = sec.querySelector('a');
                if (linkMenu) {
                    linkMenu.style.fontFamily = "'" + f + "'";
                    linkMenu.textContent = (niv === 'dropcap' ? 'CAPITULAR' : niv.toUpperCase()) + ': ' + f;
                }

                var m = it.closest('.modal');
                var o = document.querySelector('.overlay');
                if (m) m.style.display = 'none';
                if (o) o.style.display = 'none';
                var dataExtra = {};
                dataExtra['fuente_' + niv] = f;

                window.guardarPlantillaAJAX(sec.closest('form'), dataExtra);
            }
        }
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest('#btn-import-google-font');
        if (b) {
            var i = document.getElementById('google-font-name');
            var f = i ? i.value.trim() : '';
            if (!f) return;
            var niv = b.closest('.modal').querySelector('.font-list-container').dataset.nivel;
            var sec = document.querySelector('aside.template section[data-nivel="' + niv + '"]');
            if (sec) {
                var h = sec.querySelector('input[name="fuente_' + niv + '"]');
                if (h) h.value = f;

                // Actualización al vuelo
                var preview = document.querySelector('main.plantilla');
                if (preview) {
                    preview.style.setProperty('--' + niv + '-family', "'" + f + "'");
                }
                var linkMenu = sec.querySelector('a');
                if (linkMenu) {
                    linkMenu.style.fontFamily = "'" + f + "'";
                    linkMenu.textContent = (niv === 'dropcap' ? 'CAPITULAR' : niv.toUpperCase()) + ': ' + f;
                }

                var m = b.closest('.modal');
                var o = document.querySelector('.overlay');
                if (m) m.style.display = 'none';
                if (o) o.style.display = 'none';
                var dataExtra = {};
                dataExtra['fuente_' + niv] = f;
                dataExtra['forzar_descarga_fuente'] = '1';  

                window.guardarPlantillaAJAX(sec.closest('form'), dataExtra);
            }
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#btn-ia-generar');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var promptInput = document.getElementById('ai-prompt');
            var promptVal = promptInput ? promptInput.value.trim() : '';
            if (!promptVal) {
                if (typeof window.editorToast === 'function') window.editorToast('PROMPT VACIO', 'error');
                return;
            }

            var form = btn.closest('form');
            var textarea = form ? form.querySelector('textarea[name="descripcion"]') : null;
            var manualesIduInput = form ? form.querySelector('[name="manuales_idu"]') : null;
            var manualesIdu = manualesIduInput ? manualesIduInput.value : '';

            if (!manualesIdu) {
                if (typeof window.editorToast === 'function') window.editorToast('Error: no se encontró manuales_idu', 'error');
                return;
            }

            var currentContent = textarea ? textarea.value : '';

            var fd = new FormData();
            fd.append('prompt', promptVal);
            fd.append('manuales_idu', manualesIdu);
            fd.append('current_content', currentContent);

            if (typeof window.editorToast === 'function') window.editorToast('PROMPT ENVIADO', 'info');

            btn.disabled = true;

            fetch('/ev/manuales/ia_html', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) {
                    if (!r.ok) {
                        return r.text().then(function(errText) {
                            throw new Error(errText || ('Error en la petición: ' + r.status));
                        });
                    }
                    return r.text();
                })
                .then(function (text) {
                    if (typeof window.editorToast === 'function') window.editorToast('RESPUESTA INCORPORADA', 'info');
                    if (textarea) {
                        textarea.value = text;
                        if (typeof textarea_auto_height === 'function') {
                            textarea_auto_height(textarea);
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof window.editorToast === 'function') window.editorToast('Error al generar con IA: ' + err.message, 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        }
    });

})(window, document);


