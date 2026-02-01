$('body').on('click', '[data-show_pass]', function(eve) {
    eve.preventDefault();
    var input_password = $(this).data('show_pass');
    var img = $(this).parent().find('[src*="eye"]');
    if ($(input_password).attr('type') == 'text') {
        $(img).attr('src', '/img/icons/eye-s.svg');
        $(input_password).attr('type', 'password');
    } else {
        $(img).attr('src', '/img/icons/eye-off-s.svg');
        $(input_password).attr('type', 'text');
    }
});