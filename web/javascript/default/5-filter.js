/* INPUT LIVE FILTER ACCENTS */
window.replaceAccents = function(q) {
    q = q.replace(/[eéèêëEÉÈÊË]/gi, '[E]');
    q = q.replace(/[aàâäAÀÁÂÃÄÅÆ]/gi, '[A]');
    q = q.replace(/[cçC]/gi, '[C]');
    q = q.replace(/[iïîIÌÍÎÏ]/gi, '[I]');
    q = q.replace(/[oôöÒÓÔÕÖ]/gi, '[O]');
    q = q.replace(/[uüûUÜÛÙÚ]/gi, '[U]');
    q = q.replace(/[yYÿÝ]/gi, '[Y]');
    return q;
};


/* INPUT LIVE FILTER */
Kumbia.utils.on('keyup', '[data-filter]', function() {
    var itemSel = Kumbia.utils.getData(this, 'filter');
    var search = window.replaceAccents(this.value).toUpperCase();
    var items = document.querySelectorAll(itemSel);
    
    for(var i=0; i<items.length; i++){
        var q = window.replaceAccents(items[i].textContent || items[i].innerText).toUpperCase();
        if(q.indexOf(search) >= 0) Kumbia.fx.show(items[i]);
        else Kumbia.fx.hide(items[i]);
    }
});