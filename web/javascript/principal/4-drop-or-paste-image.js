Kumbia.utils.on('paste', 'textarea', function(eve) {
    const clipboard = (eve.clipboardData || window.clipboardData);
    if (!clipboard || !clipboard.files.length) return;
    
    const form = this.closest('form');
    if(!form) return;
    const img_boxes = form.querySelectorAll('.dropimage');
    if(!img_boxes.length) return;
    const img_box = img_boxes[img_boxes.length-1];
    const label = img_box.parentElement;
    const box_container = label ? label.parentElement : null;

    const input = img_box.querySelector('[type="file"]');
    if(input) input.files = clipboard.files;

    const data = clipboard.items[0].getAsFile();
    const reader = new FileReader();
    reader.onloadend = function() {
        img_box.style.backgroundImage = 'url(' + reader.result + ')';
        img_box.style.backgroundRepeat = 'no-repeat';
        img_box.style.backgroundSize = 'cover';
    };
    reader.readAsDataURL(data);

    if (label && label.classList.contains('multiple')) {
        var images = document.querySelectorAll('.modal .dropimage').length;
        if (images < 4) {
            var other = label.cloneNode(true);
            var otherDrop = other.querySelector('.dropimage');
            if(otherDrop) otherDrop.classList.remove('dropimagehover');
            var otherInput = other.querySelector('[type="file"]');
            if(otherInput) otherInput.value = '';
            if(box_container) box_container.appendChild(other);
        }
    }

    img_box.classList.add('dropnocontent');
});

Kumbia.utils.on('change', '.dropimage [type="file"]', function(eve) {
    var img_box = this.parentElement;
    var label = img_box ? img_box.parentElement : null;
    var box_container = label ? label.parentElement : null;
    var reader = new FileReader();

    reader.onloadend = function() {
        img_box.style.backgroundImage = 'url(' + reader.result + ')';
        img_box.style.backgroundRepeat = 'no-repeat';
        img_box.style.backgroundSize = 'cover';
    };
    if(eve.target.files && eve.target.files[0]) {
        reader.readAsDataURL(eve.target.files[0]);
    }

    if (label && label.classList.contains('multiple')) {
        var images = document.querySelectorAll('.modal .dropimage').length;
        if (images < 4) {
             var other = label.cloneNode(true);
             var otherDrop = other.querySelector('.dropimage');
             if(otherDrop) otherDrop.classList.remove('dropimagehover');
             var otherInput = other.querySelector('[type="file"]');
             if(otherInput) otherInput.value = '';
             if(box_container) box_container.appendChild(other);
        }
    }

    img_box.classList.add('dropnocontent');
});

Kumbia.utils.on('click', '.dropimage button', function(eve) {
    eve.preventDefault();
    var img_box = this.parentElement;
    var label = img_box ? img_box.parentElement : null;
    var box_container = label ? label.parentElement : null;
    var clean = 0;

    if (label && label.classList.contains('multiple')) {
        var images = document.querySelectorAll('.modal .dropimage').length;
        if(images > 1) {
             label.remove();
        } else {
             clean = 1;
        }

        var empty_box = document.querySelectorAll('.modal .dropimage:not(.dropnocontent)').length;
        if (empty_box < 1 && images < 5 && box_container) {
            var new_box = label.cloneNode(true);
            box_container.appendChild(new_box);
            img_box = new_box.querySelector('.dropimage');
            clean = 1;
        }
    } else {
        clean = 1;
    }

    if (clean == 1 && img_box) {
        img_box.removeAttribute('style');
        img_box.classList.remove('dropimagehover');
        img_box.classList.remove('dropnocontent');
        var imgs = img_box.querySelectorAll('>img');
        for(var i=0; i<imgs.length; i++) imgs[i].remove();
        var inp = img_box.querySelector('input');
        if(inp) inp.value = '';
    }
});

Kumbia.utils.on('dragenter', '.dropimage', function(eve) {
    eve.preventDefault();
    this.classList.add('dropimagehover');
});
Kumbia.utils.on('dragover', '.dropimage', function(eve) {
    eve.preventDefault();
    this.classList.add('dropimagehover');
});

Kumbia.utils.on('dragleave', '.dropimage', function() {
    this.classList.remove('dropimagehover');
});
Kumbia.utils.on('drop', '.dropimage', function(eve) {
    console.log('Drop event fired on:', eve.target);
    eve.preventDefault();
    eve.stopPropagation();
    this.classList.remove('dropimagehover');
    
    var files = eve.dataTransfer && eve.dataTransfer.files;
    console.log('Files detected:', files ? files.length : 0);
    if (!files || !files.length) return;

    var input = this.querySelector('[type="file"]');
    if (input) {
        try { 
            input.files = files; 
            console.log('Input.files updated');
        } catch(e) { 
            console.warn('Could not set input.files:', e);
        }
    }

    var img_box = this;
    var label = img_box.parentElement;
    var box_container = label ? label.parentElement : null;
    var reader = new FileReader();
    reader.onloadend = function() {
        console.log('File read completed');
        var imgs = img_box.querySelectorAll('img');
        for (var i = 0; i < imgs.length; i++) imgs[i].remove();
        img_box.style.backgroundImage = 'url(' + reader.result + ')';
        img_box.style.backgroundRepeat = 'no-repeat';
        img_box.style.backgroundSize = 'cover';
    };
    reader.readAsDataURL(files[0]);

    if (label && label.classList.contains('multiple')) {
        var images = document.querySelectorAll('.modal .dropimage').length;
        if (images < 4) {
            var other = label.cloneNode(true);
            var otherDrop = other.querySelector('.dropimage');
            if (otherDrop) otherDrop.classList.remove('dropimagehover');
            var otherInput = other.querySelector('[type="file"]');
            if (otherInput) otherInput.value = '';
            if (box_container) box_container.appendChild(other);
        }
    }

    img_box.classList.add('dropnocontent');
});