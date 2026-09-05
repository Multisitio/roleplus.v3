var currentScrollPos, prevScrollpos = window.pageYOffset,
    stop = 0;
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
            showElements('nav, .scroll-down-hide', false);
            stop = 0;
        }
        prevScrollpos = currentScrollPos;
    }
}
function showElements(selector, show) {
   var els = document.querySelectorAll(selector);
   for(var i=0; i<els.length; i++) {
       if (show) Kumbia.fx.show(els[i]);
       else Kumbia.fx.hide(els[i]);
   }
}

Kumbia.utils.on('click', '.scroll-top', function () {
    window.scrollTo(0, 0)
});

/* Scroll to body: On */
Kumbia.utils.on('click', '[data-ajax]:not([data-style]), [data-hide*="overlay"], .overlay', function (eve) {
    document.body.style.overflow = 'auto';
});

/* Scroll to body: Off 
    In button or link set the attr: data-style="body, overflow:hidden"
*/