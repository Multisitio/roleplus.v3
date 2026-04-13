var currentScrollPos, prevScrollpos = window.pageYOffset,
    stop = 0;
window.onscroll = function () {
    if (window.pageYOffset == 0) {
        $('nav, .scroll-down-hide').fadeIn();
    } else if (stop == 0) {
        stop = 1;
        currentScrollPos = window.pageYOffset;
        if (prevScrollpos > currentScrollPos) {
            $('nav, .scroll-down-hide').fadeIn(function () {
                stop = 0;
            });
        } else {
            $('nav, .scroll-down-hide').fadeOut(function () {
                stop = 0;
            });
        }
        prevScrollpos = currentScrollPos;
    }
}

$('body').on('click', '.scroll-top', function () {
    window.scrollTo(0, 0)
});

/* Scroll to body: On */
$('body').on('click', '[data-ajax]:not([data-style]), [data-hide*="overlay"], .overlay', function (eve) {
    $('body').css('overflow', 'auto');
});

/* Scroll to body: Off 
    In button or link set the attr: data-style="body, overflow:hidden"
*/