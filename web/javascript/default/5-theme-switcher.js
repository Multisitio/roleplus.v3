Kumbia.utils.on('click', 'a.theme_switcher', function(eve) {
    eve.preventDefault();
    var theme = Kumbia.utils.getData(this, 'theme');
    var url = this.getAttribute('href');
    document.querySelector('html').setAttribute('data-theme', theme);
    var ajaxNode = document.querySelector('.ajax.hide');
    if (ajaxNode && url) {
        Kumbia.utils.fetch(url).then(function(html) {
            ajaxNode.innerHTML = html;
        });
    }
});

Kumbia.utils.on('click', '.theme_switcher a', function(eve) {
    eve.preventDefault();
    var color = Kumbia.utils.getData(this, 'color');
    var url = this.getAttribute('href');
    document.querySelector('html').setAttribute('data-color', color);
    var ajaxNode = document.querySelector('.ajax.hide');
    if (ajaxNode && url) {
        Kumbia.utils.fetch(url).then(function(html) {
            ajaxNode.innerHTML = html;
        });
    }
});