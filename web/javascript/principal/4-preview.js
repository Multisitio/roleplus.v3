Kumbia.utils.on('paste', '[data-preview]', function(eve) {
    var pastedData = (eve.clipboardData || window.clipboardData).getData('text');
    preview(pastedData, this);
});

function preview(url, el) {
    if (typeof strstr === "function" ? strstr(url, 'http') : url.indexOf('http') !== -1) {
        var prev = el.parentElement ? el.parentElement.querySelector('.preview') : null;
        if(prev) {
            prev.innerHTML = '<progress class="indeterminate"></progress>';
            var enc = typeof base64_encode === "function" ? base64_encode(url) : btoa(url);
            Kumbia.utils.fetch('/index/preview?u=' + enc).then(html => prev.innerHTML = html);
        }
    }
}

function removeHideElements(el) {
    var hidden = el.querySelectorAll('[style="display:none"]');
    for(var i=0; i<hidden.length; i++) hidden[i].remove();
}