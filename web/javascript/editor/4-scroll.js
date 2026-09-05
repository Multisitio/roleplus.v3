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
    for (var i = 0; i < els.length; i++) {
        if (show) Kumbia.fx.show(els[i]);
        else Kumbia.fx.hide(els[i]);
    }
}

document.addEventListener('click', function (eve) {
    var btn = eve.target && eve.target.closest ? eve.target.closest('.scroll-bottom, [href="#last-page"]') : null;
    if (btn) {
        eve.preventDefault();
        var pages = document.querySelectorAll('main article[id], main div[data-idu], main div[data-page]');
        if (pages.length > 0) {
            pages[pages.length - 1].scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
    }
});

/* Scroll to body: On */
document.addEventListener('click', function (eve) {
    var btn = eve.target && eve.target.closest ? eve.target.closest('[data-ajax]:not([data-style]), [data-hide*="overlay"], .overlay') : null;
    if (btn && document.body) {
        document.body.style.overflow = 'auto';
    }
});
