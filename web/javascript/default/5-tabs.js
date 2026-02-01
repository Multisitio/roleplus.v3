$(function() {
    const hash = location.hash.split('#')[1];
    $('[data-hash="' + hash + '"]').click();
});

$('body').on('click', '[data-hash]', function(eve) {
    var tab = $(this).data('hash');
    location.hash = tab;
    $('[data-container]').hide();
    $('[data-container="' + tab + '"]').show();
    $('[data-hash]').removeClass('selected');
    $(this).addClass('selected');
});