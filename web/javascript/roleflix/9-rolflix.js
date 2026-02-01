/*function buscador(on) {
    if (window.matchMedia('(max-width:992px)').matches) {
        if (on == 1) {
            $('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador').hide();
            $('.buscador').show();
        } else {
            $('.buscador, .enlaces').hide();
            $('.configurar, .filtros, .logo, .mostrar-buscador').show();
        }
    } else {
        if (on == 1) {
            $('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador').hide();
            $('.buscador').show();
        } else {
            $('.buscador').hide();
            $('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador').show();
        }
    }
}*/

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

/* Cosas que se ocultan o muestras si se hace scroll hacia abajo o hacia arriba */
var prevScrollpos = window.pageYOffset;
window.onscroll = function() {
    var currentScrollPos = window.pageYOffset;
    if (prevScrollpos > currentScrollPos) {
        $(".scroll-down-hide, nav").fadeIn();
    } else {
        $(".scroll-down-hide, aside.left, nav, .overlay").fadeOut();
    }
    prevScrollpos = currentScrollPos;
}

$(window).resize(function() {
    ($('.buscador').css('display') == 'none') ? buscador(0): buscador(1);

    if (window.matchMedia('(max-width:600px)').matches) {} else if (window.matchMedia('(min-width:601px)').matches && window.matchMedia('(max-width:992px)').matches) {} else if (window.matchMedia('(min-width:993px)').matches && window.matchMedia('(max-width:1200px)').matches) {} else {}
});

$(function() {
    buscador(0);
});