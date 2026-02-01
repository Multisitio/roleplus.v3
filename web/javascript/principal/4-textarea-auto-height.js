function textarea_auto_height() {
    var els = document.querySelectorAll('textarea');
    els.forEach(function(el) {
        var height = el.scrollHeight;
        height = (height < 55) ? 55 : height;
        el.style.height = height + 'px';
    });
}
textarea_auto_height();

$('body').on('click keyup', 'textarea', function() {
    textarea_auto_height();
});