/* Estas son las cosas que se ocultan y aparecen cuando se desliza la pagina */
var currentScrollPos, prevScrollpos = window.pageYOffset,
    stop = 0;
window.onscroll = function() {
    if (window.pageYOffset == 0) {
        $('nav, .scroll-down-hide').fadeIn();
    } else if (stop == 0) {
        stop = 1;
        currentScrollPos = window.pageYOffset;
        if (prevScrollpos > currentScrollPos) {
            $('nav, .scroll-down-hide').fadeIn(function() {
                stop = 0;
            });
        } else {
            $('aside.left, nav, .scroll-down-hide').fadeOut(function() {
                stop = 0;
            });
        }
        prevScrollpos = currentScrollPos;
    }
}

$('body').on('change', '.file:not(.basic)', function(eve) {
    let files = eve.target.files || eve.dataTransfer.files;
    var contenedor = $(this).data('upload_file_to');
    Array.from(files).forEach(file => {
        var reader = new FileReader();
        reader.onload = function() {
            $('<img src="' + reader.result + '">').appendTo(contenedor);
        }
        reader.readAsDataURL(file);
    });
    var este = $(this);
    var parent = $(this).parent(':first');
    $(este).clone().prependTo(parent).find('[type="file"]').val('');
    $(este).hide();
});

$('body').on('click', 'nav .contador', function(eve) {
    $('nav .contador').load('/registrados/notificaciones/contar');
});

$('body').on('click', '.images-container [src*="arrow-left"]', function(eve) {
    eve.preventDefault();
    var parent = $(this).parents('.images-container');
    //$(parent).css('height', $(parent).height());
    var img = $(parent).find('[data-img]:visible');
    var current = $(img).data('img');
    $(img).hide();
    --current;
    $(parent).find('[data-img="' + current + '"]').show();
    $(parent).find('button [src*="arrow-right"]').parent().show();
    if (!$(parent).find('[data-img="' + (current - 1) + '"]').length) {
        $(this).parent().hide();
    }
});

$('body').on('click', '.images-container [src*="arrow-right"]', function(eve) {
    eve.preventDefault();
    var parent = $(this).parents('.images-container');
    //$(parent).css('height', $(parent).height());
    var img = $(parent).find('[data-img]:visible');
    var current = $(img).data('img');
    $(img).hide();
    ++current;
    $(parent).find('[data-img="' + current + '"]').show();
    $(parent).find('button [src*="arrow-left"]').parent().show();
    if (!$(parent).find('[data-img="' + (current + 1) + '"]').length) {
        $(this).parent().hide();
    }
});

$('body').on('click', '.inserta-apodo', function() {
    var pub = $(this).data('publicacion');
    var textarea = $('.pub-' + pub + ' [name="comentario"]');
    var apodo = '{@' + $(this).data('apodo') + '} ';
    var val = $(textarea).val();
    $(textarea).val(val + apodo);
});