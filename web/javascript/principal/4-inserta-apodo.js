$('body').on('click', '.inserta-apodo', function() {
    var pub = $(this).data('publicacion');
    var textarea = $('.pub-' + pub + ' [name="comentario"]');
    var apodo = '{@' + $(this).data('apodo') + '} ';
    var val = $(textarea).val();
    $(textarea).val(val + apodo);
});

// Backspace solo funciona con keyup
palabra = '';
$('body').on('keyup', '[name="comentario"], [name="contenido"]', function(eve) {
    if (eve.key !== 'Backspace') {
        return;
    }
    //console.log(eve.key);

    if (palabra !== "") {
        palabra = palabra.slice(0, -1);
        //console.log(palabra);
        if (palabra !== "") {
            url = '/usuarios/apodos/' + base64_encode(palabra);
            $('.apodos').data('trozo', palabra).load(url);
        }
    }
});

// Para el resto usamos keypress
llave = 0;
$('body').on('keypress', '[name="comentario"], [name="contenido"]', function(eve) {
    if (eve.key == 'AltGraph' || eve.key == 'Control' || eve.key == 'Dead' || eve.key == 'Shift') {
        return;
    }
    //console.log(eve.key);

    if (eve.key == '{') {
        //console.log('Llave');
        llave = 1;
        arroba = 0;
        palabra = '';
        return;
    }

    if (eve.key == 'Enter') {
        llave = 0;
    }

    if (llave == 1 && eve.key == '@') {
        //console.log('Arroba');
        arroba = 1;
        $('.apodos').remove();
        $(this).after('<div class="apodos"></div>');
        return;
    } else if (llave == 1 && arroba == 0) {
        //console.log('No arroba');
        llave = 0;
    }

    if (llave == 0) {
        //console.log('Sin llave');
        $('.apodos').remove();
        arroba = 0;
        palabra = '';
        url = '';
        return;
    }

    if (arroba == 1) {
        //console.log('Texto');
        palabra += eve.key;
        //console.log(palabra);
        url = '/usuarios/apodos/' + base64_encode(palabra);
        $('.apodos').data('trozo', palabra).load(url);
    }
});

$('body').on('click', '.tomar-apodo', function() {
    let parent = $(this).parent();
    let textarea = parent.prev('textarea');
    let { trozo } = parent.data();
    let apodo = '{@' + $(this).text() + '} ';

    let val = textarea.val();
    let cursorPosition = textarea.prop('selectionStart');
    let lastOpenBracket = val.lastIndexOf('{@' + trozo, cursorPosition);

    let newValue = val.substring(0, lastOpenBracket) + apodo;
    textarea.val(newValue).focus();

    parent.remove();
    llave = 0;
    arroba = 0;
    palabra = '';
    url = '';
});