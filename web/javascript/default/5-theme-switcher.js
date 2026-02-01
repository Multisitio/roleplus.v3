// Interruptor para cambiar de tema en general
$('body').on('click', 'a.theme_switcher', function(eve) {
    eve.preventDefault();
    var theme = $(this).data('theme');
    var url = $(this).attr('href');
    $('html').attr('data-theme', theme);
    $('.ajax.hide').load(url);
});
// Interruptor para cambiar del color del tema
$('body').on('click', '.theme_switcher a', function(eve) {
    eve.preventDefault();
    var color = $(this).data('color');
    var url = $(this).attr('href');
    $('html').attr('data-color', color);
    $('.ajax.hide').load(url);
});