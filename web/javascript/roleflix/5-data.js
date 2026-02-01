$(function() {
    /* COPIA EL VALOR DEL ATRIBUTO A UN CONTENEDOR */
    $('body').on('click', '[data-add]', function() {
        var data = $(this).data('add');
        var to = $(this).data('add_to');
        var val = $(to).val();
        $(to).val(val + data);
    });

    /* MUESTRA UN MENSAJE W3CSS */
    $('body').on('click', '[data-alert]', function() {
        var alert = $(this).data('alert');
        $('<div class="alert n0 w3-panel w3-blue-grey w3-round"><h3>INFO</h3><p>' + alert + '</p></div>').appendTo('.alert-container');
        setTimeout("$('.alert.n0').fadeOut('slow', function(){ $(this).remove() })", 8000);
    });

    /* ALTERNA MARCADO */
    $('body').on('click', '[data-checked]', function() {
        var checked = $(this).data('checked');
        $(this).toggleClass(checked);
    });

    /* CLICK */
    $('body').on('click', '[data-click]', function() {
        var to = $(this).data('click');
        $(to).click();
    });

    /* CLON */
    $('body').on('click', '[data-clone]', function() {
        var el = $(this).data('clone');
        var to = $(this).data('to');
        $(el).clone().appendTo(to);
    });

    /* CONFIRMAR ANTES DESDE EL NAVEGADOR, SOLO PARA AJAX */
    $('body').on('click', '[data-confirm]', function(e) {
        var mensaje = $(this).data('confirm');
        if (!confirm(mensaje)) {
            e.stopImmediatePropagation();
            return false;
        }
    });

    /* SE DA OPACIDAD AL ELEMENTO ACTIVO */
    $('body').on('click', '[data-disabled]', function() {
        var disabled = $(this).data('disabled');
        var enabled = $(this).data('enabled');
        $(enabled).css('opacity', '1');
        $(disabled).css('opacity', '.15');
    });

    /* OCUTA ALGO CON EFECTO FADE OUT */
    $('body').on('click', '[data-fade_out]', function() {
        var to = $(this).data('fade_out');
        $(to).fadeOut();
    });

    /* EFECTO CHINCHETA */
    $('body').on('click', '[data-fixed]', function() {
        var to = $(this).data('fixed');
        $(to).toggleClass('fixed').find('.fixed-icon').toggle();
    });

    /* FOCUS */
    $('body').on('click', '[data-focus]', function() {
        var to = $(this).data('focus');
        $(to).focus();
    });

    /* CAMBIA EL HASH */
    $('body').on('click', '[data-hash]', function() {
        var hash = $(this).data('hash');
        location.hash = hash;
    });

    /* OCULTA ALGO */
    $('body').on('click', '[data-hide]', function() {
        var to = $(this).data('hide');
        $(to).hide();
    });

    /* COPIA UN HTML A UN ELEMENTO DE FORMULARIO */
    $('body').on('click', '[data-html2val]', function() {
        var from = $(this).data('html2val');
        var to = $(this).data('html2val_to');
        $(to).val($(from).html());
    });

    /* LOOP */
    $('body').on('click', '[data-loop]', function() {
        var div = $(this).data('loop');
        var img = $(this).data('next');
        var val = $(this).data('keep');
        var src;

        $(div).css('display', 'none');

        if ($(div + img).length == 0) {
            img = 0;
        }

        $(div + img).css('display', 'block');
        src = $(div + img).attr('src');
        $(val).val(src);

        $(this).data('next', ++img);
    });

    /* MUEVE UNA CLASE */
    $('body').on('click', '[data-move_class]', function() {
        var item = $(this).data('move_class');
        var from = $(this).data('move_class_from');
        var to = $(this).data('move_class_to');
        $(from).removeClass(item);
        $(to).addClass(item);
    });

    /* ELIMINA ALGO */
    $('body').on('click', '[data-remove]', function() {
        var to = $(this).data('remove');
        if (to == 'parent') {
            $(this).parent().remove();
        } else {
            $(to).remove();
        }
    });

    /* BORRA EL VALOR DE UN ELEMENTO */
    $('body').on('click', '[data-reset]', function() {
        var to = $(this).data('reset');
        $(to).text('').val('');
    });

    /* HACE SCROLL HASTA EL ELEMENTO ACTIVO INDICADO */
    $('[data-scroll]').each(function() {
        var to = $(this).data('scroll');
        if ($(to).length) $(this).animate({ scrollTop: $(to).offset().top }, 500);
    });

    /* SCROLL HACIA ABAJO AL PULSAR UN BOTON */
    $('body').on('click', '[data-scroll_bottom]', function() {
        var to = $(this).data('scroll_bottom');
        var div = document.querySelector(to);
        div.scrollTo(0, div.scrollHeight);
    });

    /* ALTERNA AL SELECCIONAR */
    $('body').on('change', '[data-change_toggle]', function() {
        var to = $(this).data('change_toggle');
        var if_val = $(this).data('change_if_value');
        var val = $(this).val();
        console.log([if_val, val]);
        if (if_val == val) {
            $(to).toggle();
        }
    });

    /* MUESTRA ALGO */
    $('body').on('click', '[data-show]', function() {
        var to = $(this).data('show');
        $(to).show();
    });

    /* OCUTA ALGO CON EFECTO SLIDE DOWN */
    $('body').on('click', '[data-slide_down]', function() {
        var to = $(this).data('slide_down');
        console.log(to);
        $(to).slideDown();
    });

    /* MUESTRA Y OCULTA ALGO */
    $('body').on('click', '[data-toggle]', function() {
        var to = $(this).data('toggle');
        $(to).toggle();
        /*if (window.M) {
            M.textareaAutoResize($('textarea'))
        }*/
    });

    /* MUESTRA Y OCULTA ALGO CON DOBLE CLICK */
    $('body').on('dblclick', '[data-toggle2]', function() {
        var to = $(this).data('toggle2');
        $(to).toggle();
        $(this).find('input, textarea').focus();
    });

    /* MUESTRA Y OCULTA ALGO */
    $('body').on('click', '[data-toggle_class]', function() {
        var to = $(this).data('toggle_class');
        var _class = $(this).data('class');
        $(to).toggleClass(_class);
    });

    /* ABRE LA VENTANA DE USUARIOS PARA BUSCAR UNO */
    $('body').on('click', '[data-usuarios]', function() {
        var to = $(this).data('usuarios');
        var load = $(this).data('load');
        $('.ajax.show').load('/usuarios/ventana', { 'to': to, 'load': load });
    });
});