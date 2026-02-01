/* Ajusta la altura de todos los textarea a su contenido al cargar la pagina */
var els = document.querySelectorAll('textarea');
var margin_height = 15;
els.forEach(function(el) {
    var height = el.scrollTop + el.scrollHeight || margin_height;
    el.style.height = (height + margin_height) + 'px';
});

$('body').on('keydown', 'textarea', function() {
    var height = this.scrollTop + this.scrollHeight;
    height = (height < 55) ? 55 : height;
    this.style.height = height + 'px';
});