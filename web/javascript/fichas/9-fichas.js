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