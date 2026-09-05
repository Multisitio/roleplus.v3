function copiarAlPortapapeles(link) {
    var aux = document.createElement("input");
    aux.setAttribute("value", link);
    document.body.appendChild(aux);
    aux.select();
    document.execCommand("copy");
    document.body.removeChild(aux);
}

Kumbia.utils.on('click', '.share', function(eve) {
    eve.preventDefault();
    var url = this.getAttribute('href');
    if (navigator.share) {
        navigator.share({
            title: '',
            text: '',
            url: url,
        });
    } else {
        copiarAlPortapapeles(url);
    }
});

Kumbia.utils.on('click', '[data-toast]', function() {
    var toast = Kumbia.utils.getData(this, 'toast');
    var ajaxDiv = document.querySelector('.ajax.show');
    if(ajaxDiv) {
        var fd = new FormData();
        fd.append('toast', toast);
        Kumbia.utils.fetch('/index/toast', { method: 'POST', body: fd }).then(function(html) {
            ajaxDiv.innerHTML = html;
        });
    }
});