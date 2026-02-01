$('body').on('change', '.submit', function() {
    $(this).parents('form').submit();
});