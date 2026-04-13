$(function () {
    var pages = $('main article').length;
    $('aside .pages h4 span').text(pages);
});

$('body').on('keyup', '[name="pages_from"], [name="pages_to"]', function () {
    var pages_from = parseInt($('aside .pages [name="pages_from"]').val());
    var pages_to = parseInt($('aside .pages [name="pages_to"]').val());

    $('main>div').each(function (index) {
        if (index < pages_from || index > pages_to) {
            $(this).hide();
        } else {
            $(this).show();
        }
    });

    var pages = $('main article:visible').length;
    $('aside .pages h4 span').text(pages);
});

/*$('body').on('click', '.add-bleed', function () {
    if ($('main').hasClass('bleed')) {
        $('main').removeClass('bleed')
        $('.resultado>article:first-child').show();
    } else {
        $('main').addClass('bleed')
        $('.resultado>article:first-child').hide();
    }
});*/

$('body').on('click', '.print', function () {
    window.print();
});

$('body').on('click', '.booklet-toggle', function () {
    var $btn = $(this);
    var isBooklet = document.body.classList.toggle('booklet-mode');
    $('#page-style').html(isBooklet ? '@page { size: A4 landscape; margin: 0; }' : '@page { size: A5; margin: 0; }');

    var $container = $('.resultado');
    if (!$container.length) return;

    if (isBooklet) {
        // ═══════════════════════════════════════════════════════════
        // ACTIVAR IMPOSICIÓN SADDLE-STITCH (Cosido a caballete)
        // ═══════════════════════════════════════════════════════════
        var $pages = $container.children();

        // Guardar orden original del DOM para poder restaurarlo
        $pages.each(function (i) {
            if (!$(this).attr('data-original-index')) {
                $(this).attr('data-original-index', i);
            }
        });

        var N = $pages.length;

        // Avisar si no es múltiplo de 4 (el usuario gestiona las páginas)
        if (N % 4 !== 0) {
            console.warn('[Libreto] El documento tiene ' + N + ' páginas. Para un libreto perfecto necesitas un múltiplo de 4 (' + (N + (4 - N % 4) % 4) + ').');
        }

        // Algoritmo Saddle-Stitch:
        // Empareja páginas del principio y final alternando frente/dorso.
        // Ejemplo N=8: [7,0, 1,6, 5,2, 3,4]
        //   Hoja 1 Frente: [7, 0]  Hoja 1 Dorso: [1, 6]
        //   Hoja 2 Frente: [5, 2]  Hoja 2 Dorso: [3, 4] ← grapa
        // Lectura al doblar: 0→1|2→3|4→5|6→7 ✓
        var order = [];
        for (var i = 0; i < N / 2; i++) {
            if (i % 2 === 0) {
                order.push(N - 1 - i); // izquierda
                order.push(i);         // derecha
            } else {
                order.push(i);         // izquierda
                order.push(N - 1 - i); // derecha
            }
        }

        // Reordenar el DOM
        $.each(order, function (_, index) {
            $container.append($pages.eq(index));
        });

        $btn.addClass('active');

    } else {
        // ═══════════════════════════════════════════════════════════
        // DESACTIVAR IMPOSICIÓN — restaurar orden original
        // ═══════════════════════════════════════════════════════════
        var $allPages = $container.children().get();
        $allPages.sort(function (a, b) {
            return parseInt($(a).attr('data-original-index')) - parseInt($(b).attr('data-original-index'));
        });
        $.each($allPages, function (_, p) {
            $container.append(p);
        });

        $btn.removeClass('active');
    }
});