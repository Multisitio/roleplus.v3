$(function() {
    var pages = $('main article').length;
    $('aside .pages h4 span').text(pages);
});

$('body').on('keyup', '[name="pages_from"], [name="pages_to"]', function() {
    var pages_from = parseInt($('aside .pages [name="pages_from"]').val());
    var pages_to = parseInt($('aside .pages [name="pages_to"]').val());

    $('main>div').each(function(index) {
        if (index < pages_from || index > pages_to) {
            $(this).hide();
        } else {
            $(this).show();
        }
    });

    var pages = $('main article:visible').length;
    $('aside .pages h4 span').text(pages);
});

$('body').on('click', '.add-bleed', function() {
    if ($('main').hasClass('bleed')) {
        $('main').removeClass('bleed')
        $('.resultado>article:first-child').show();
    } else {
        $('main').addClass('bleed')
        $('.resultado>article:first-child').hide();
    }
});

$('body').on('click', '.print', function() {
    window.print();
});