function buscador(on) {
    if (window.matchMedia('(max-width:992px)').matches) {
        if (on == 1) {
            hideElements('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador');
            showElements('.buscador');
        } else {
            hideElements('.buscador, .enlaces');
            showElements('.configurar, .filtros, .logo, .mostrar-buscador');
        }
    } else {
        if (on == 1) {
            hideElements('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador');
            showElements('.buscador');
        } else {
            hideElements('.buscador');
            showElements('.configurar, .enlaces, .filtros, .logo, .mostrar-buscador');
        }
    }
}

function hideElements(selector) {
    var els = document.querySelectorAll(selector);
    for(var i=0; i<els.length; i++) {
        if(Kumbia && Kumbia.fx) Kumbia.fx.hide(els[i]);
        else els[i].style.display = 'none';
    }
}
function showElements(selector) {
    var els = document.querySelectorAll(selector);
    for(var i=0; i<els.length; i++) {
        if(Kumbia && Kumbia.fx) Kumbia.fx.show(els[i]);
        else els[i].style.display = '';
    }
}

function copiarAlPortapapeles(link) {
    var aux = document.createElement("input");
    aux.setAttribute("value", link);
    document.body.appendChild(aux);
    aux.select();
    document.execCommand("copy");
    document.body.removeChild(aux);
}

var prevScrollpos = window.pageYOffset;
window.onscroll = function() {
    var currentScrollPos = window.pageYOffset;
    if (prevScrollpos > currentScrollPos) {
        showElements(".scroll-down-hide, nav");
    } else {
        hideElements(".scroll-down-hide, aside.left, nav, .overlay");
    }
    prevScrollpos = currentScrollPos;
}

window.addEventListener('resize', function() {
    var b = document.querySelector('.buscador');
    (b && b.style.display == 'none') ? buscador(0): buscador(1);
});

document.addEventListener("DOMContentLoaded", function() {
    buscador(0);
}); 