Kumbia.utils.on('change', 'select[data-action]', function() {
    var form = this.closest('form');
    if(!form) return;
    var url = this.value;

    if (url === '/registrados/ia/preguntar') {
        form.setAttribute('data-ajax', '.persiana');
    } else {
        form.removeAttribute('data-ajax');
    }

    form.setAttribute('action', url);
});