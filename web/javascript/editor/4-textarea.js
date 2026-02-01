function textarea_auto_height() {
    var els = document.querySelectorAll('textarea');
    els.forEach(function(el) {
        var height = el.scrollTop + el.scrollHeight;
        height = (height < 99) ? 99 : height;
        el.style.height = height + 'px';
    });
}
textarea_auto_height();

$('body').on('click keyup', 'textarea', function() {
    textarea_auto_height();
});

/* INSERTA UN TAB EN UN TEXTAREA */
$('body').on('keydown', 'textarea', function(event) {
    if (event.keyCode === 9) {
        var v = this.value,
            s = this.selectionStart,
            e = this.selectionEnd;
        this.value = v.substring(0, s) + '\t' + v.substring(e);
        this.selectionStart = this.selectionEnd = s + 1;
        return false;
    }
});