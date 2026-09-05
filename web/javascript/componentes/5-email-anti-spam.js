document.addEventListener("DOMContentLoaded", function() {
    var els = document.querySelectorAll('.email-anti-spam');
    for(var i=0; i<els.length; i++) {
        var el = els[i];
        var text = (el.textContent || el.innerText || "").trim();
        if(text) {
            var addr = text.replace(/ at /, '@').replace(/ dot /g, '.');
            el.innerHTML = '';
            var a = document.createElement('a');
            a.href = 'mailto:' + addr;
            a.textContent = addr;
            el.appendChild(a);
        }
    }
});