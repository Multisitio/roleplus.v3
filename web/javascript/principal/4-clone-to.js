Kumbia.utils.on('click', '[data-clone]', function() {
    var elSel = Kumbia.utils.getData(this, 'clone');
    var toSel = Kumbia.utils.getData(this, 'to');
    var el = document.querySelector(elSel);
    var to = document.querySelector(toSel);
    if(el && to) {
        var clone = el.cloneNode(true);
        to.appendChild(clone);
        Kumbia.fx.show(clone);
    }
});