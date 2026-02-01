function copiarAlPortapapeles(link) {
    // Crea un campo de texto "oculto"
    var aux = document.createElement("input");

    // Asigna el contenido del elemento especificado al valor del campo
    aux.setAttribute("value", link);

    // Añade el campo a la página
    document.body.appendChild(aux);

    // Selecciona el contenido del campo
    aux.select();

    // Copia el texto seleccionado
    document.execCommand("copy");

    // Elimina el campo de la página
    document.body.removeChild(aux);
}

$('body').on('click', '[data-remove]', function(eve) {
    eve.preventDefault();
    var to = $(this).data('remove');
    console.log(to);
    if (to == 'parent') {
        $(this).parent().remove();
    } else {
        $(to).remove();
    }
});

$('body').on('click', '.share', function(eve) {
    eve.preventDefault();
    var url = $(this).attr('href');
    if (navigator.share) {
        navigator.share({
            title: '',
            text: '',
            url: url,
        });
    } else {
        copiarAlPortapapeles(url);
    }
});

$('body').on('click', '[data-toast]', function() {
    var toast = $(this).data('toast');
    $('.ajax.show').load('/index/toast', { 'toast': toast });
});