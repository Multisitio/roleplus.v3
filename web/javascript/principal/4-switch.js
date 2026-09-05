Kumbia.utils.on('change', '.submit', function() {
    var form = this.closest('form');
    if(form) {
        var btn = form.querySelector('[type="submit"]');
        if(btn) btn.click();
        else form.submit();
    }
});