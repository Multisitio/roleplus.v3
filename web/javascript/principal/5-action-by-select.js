$('body').on('change', 'select[data-action]', function() {
    var form = $(this).parents('form');
    var url = $(this).val();

    if (url === '/registrados/ia/preguntar') {
        $(form).attr('data-ajax', '.persiana');
    } else {
        $(form).removeAttr('data-ajax');
    }

    $(form).attr('action', url);
});