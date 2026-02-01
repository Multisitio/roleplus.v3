$('body').on('keypress', '.enviar-con-enter', function(eve) {
    if (eve.which == 13) {
        $(this).parents('form').find('button').click();
    }
});