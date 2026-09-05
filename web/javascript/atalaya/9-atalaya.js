function updatePrismContainer(selector, code) {
    let container = document.querySelector(selector);
    container.innerText = code;
    Prism.highlightElement(container);
}

let primary_color = storage('read', 'primary_color');
primary_color = (!primary_color) ? 'red' : primary_color;
document.querySelector('html').setAttribute('data-primary_color', primary_color);

let theme = storage('read', 'theme');
theme = (!theme) ? 'day' : theme;
document.querySelector('html').setAttribute('data-theme', theme);
if (theme == 'moon') {
    var i = document.querySelector('.theme-picker input');
    if(i) i.checked = true;
} 