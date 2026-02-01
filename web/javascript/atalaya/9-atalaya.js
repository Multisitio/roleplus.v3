function updatePrismContainer(selector, code) {
    let container = document.querySelector(selector);
    container.innerText = code;
    Prism.highlightElement(container);
}

let primary_color = storage('read', 'primary_color');
primary_color = (!primary_color) ? 'red' : primary_color;
$('html').attr('data-primary_color', primary_color);

let theme = storage('read', 'theme');
theme = (!theme) ? 'day' : theme;
$('html').attr('data-theme', theme);
if (theme == 'moon') {
    $('.theme-picker input').prop('checked', true);
}