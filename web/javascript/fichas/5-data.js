$('body').on('change', '[data-change]', function(eve) {
    eve.preventDefault();
    var hide = $(this).data('change');
    var show = $(this).val();
    $(hide).hide();
    $('.' + show).show();
});

$('body').on('change', '[data-change_load]', function(eve) {
    eve.preventDefault();
    var to = $(this).data('change_load');
    var url = $(this).val();
    $(to).load(url);
});

$('body').on('click', '[data-remove]', function() {
    var to = $(this).data('remove');
    console.log(to);
    if (to == 'parent') {
        $(this).parent().remove();
    } else {
        $(to).remove();
    }
});