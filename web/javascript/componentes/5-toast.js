$('body').on('click', '[data-remove]', function(eve) {
    eve.preventDefault();
    var to = $(this).data('remove');
    if (to == 'parent') {
        $(this).parent().remove();
    } else {
        $(to).remove();
    }
});