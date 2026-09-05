document.addEventListener("DOMContentLoaded", function() {
    const hashParts = location.hash.split('#');
    if(hashParts.length > 1) {
        const hash = hashParts[1];
        var el = document.querySelector('[data-hash="' + hash + '"]');
        if(el) el.click();
    }
});

Kumbia.utils.on('click', '[data-hash]', function(eve) {
    var tab = Kumbia.utils.getData(this, 'hash');
    location.hash = tab;
    var containers = document.querySelectorAll('[data-container]');
    for(var i=0; i<containers.length; i++) Kumbia.fx.hide(containers[i]);
    
    var activeContainers = document.querySelectorAll('[data-container="' + tab + '"]');
    for(var j=0; j<activeContainers.length; j++) Kumbia.fx.show(activeContainers[j]);
    
    var hashes = document.querySelectorAll('[data-hash]');
    for(var k=0; k<hashes.length; k++) hashes[k].classList.remove('selected');
    
    this.classList.add('selected');
});