Kumbia.utils.on('click', '[data-show_pass]', function(eve) {
    eve.preventDefault();
    var input_password = document.querySelector(Kumbia.utils.getData(this, 'show_pass'));
    var img = this.parentElement ? this.parentElement.querySelector('[src*="eye"]') : null;
    if(input_password && img) {
        if (input_password.getAttribute('type') == 'text') {
            img.setAttribute('src', '/img/icons/eye-s.svg');
            input_password.setAttribute('type', 'password');
        } else {
            img.setAttribute('src', '/img/icons/eye-off-s.svg');
            input_password.setAttribute('type', 'text');
        }
    }
});