// /javascript/masonry-loader.js — carga idempotente de Salvattore
(function () {
    'use strict';

    if (window.__rpMasonryInit) return;
    window.__rpMasonryInit = true;

    function shouldEnable() {
        return window.innerWidth > 972;
    }

    function addSalvattore() {
        if (window.__rpSalvattoreScriptInjected) return;
        if (document.querySelector('script[src="/javascript/salvattore.min.js"]')) {
            window.__rpSalvattoreScriptInjected = true;
            return;
        }
        var s = document.createElement('script');
        s.type = 'text/javascript';
        s.src = '/javascript/salvattore.min.js';
        s.async = true;
        document.body.appendChild(s);
        window.__rpSalvattoreScriptInjected = true;
    }

    function maybeEnable() {
        if (shouldEnable() && !window.__rpSalvattoreEnabled) {
            window.__rpSalvattoreEnabled = true;
            addSalvattore();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', maybeEnable, { once: true });
    } else {
        maybeEnable();
    }

    var resizeTO;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTO);
        resizeTO = setTimeout(function () {
            if (shouldEnable() && !window.__rpSalvattoreEnabled) {
                window.__rpSalvattoreEnabled = true;
                addSalvattore();
            }
        }, 150);
    });
})();
