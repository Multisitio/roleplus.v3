$(function() {
    $.get('/usuarios/conectado');
    console.log('Connected!');
    setInterval(function() {
        $.get('/usuarios/conectado');
        console.log('Connected!');
    }, 540000);

    /* HASH TAB TO */
    hash = location.hash.split('#')[1];
    $('.tabs [data-show*="' + hash + '"]').click();

    $('progress.indeterminate').indeterminate = true;
});

$('body').on('click', '[data-loop]', function(eve) {
    eve.preventDefault();
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

$('body').on('paste', '[name="comentario"], .ventana-publicacion-textarea', function(e) {
    var pastedData = e.originalEvent.clipboardData.getData('text');

    if (strstr(pastedData, 'http')) {
        $(this).after('<div class="mb15 preview relative s12"><button type="button" class="tr transparent" data-remove="parent"><img src="/img/icons/x-square.svg"></button><div class="content"></div></div>')

        $(this).parent().find('.preview:first .content').html('<progress class="indeterminate s12"></progress>').load('/index/preview?u=' + base64_encode(pastedData));
    }
});

/* Caja nav personalizada */
$('body').on('change', 'nav select', function() {
    var url = $(this).val();
    $('nav form').attr('action', url);
});

function textarea_auto_height() {
    var els = document.querySelectorAll('textarea');
    els.forEach(function(el) {
        var height = el.scrollTop + el.scrollHeight;
        height = (height < 55) ? 55 : height;
        el.style.height = height + 'px';
    });
}
textarea_auto_height();

$('body').on('click keyup', 'textarea', function() {
    textarea_auto_height();
});