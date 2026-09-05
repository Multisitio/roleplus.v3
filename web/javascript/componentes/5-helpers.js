var currentScrollPos;
var prevScrollpos = window.pageYOffset;
var stop = 0;
window.onscroll = function () {
    if (window.pageYOffset == 0) {
        showElements('nav, .scroll-down-hide', true);
    } else if (stop == 0) {
        stop = 1;
        currentScrollPos = window.pageYOffset;
        if (prevScrollpos > currentScrollPos) {
            showElements('nav, .scroll-down-hide', true);
            stop = 0;
        } else {
            showElements('aside.left, nav, .scroll-down-hide', false);
            stop = 0;
        }
        prevScrollpos = currentScrollPos;
    }
}
function showElements(selector, show) {
   var els = document.querySelectorAll(selector);
   for(var i=0; i<els.length; i++) {
       if (show) Kumbia.fx.slideDown(els[i]);
       else Kumbia.fx.fadeOut(els[i]);
   }
}