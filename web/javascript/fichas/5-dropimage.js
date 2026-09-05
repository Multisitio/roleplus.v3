Kumbia.utils.on('change', '.dropimage [type="file"]', function (eve) {
    var img_box = this.parentElement;
    if (!img_box) return;
    var reader = new FileReader();

    reader.onloadend = function () {
        var imgs = img_box.querySelectorAll('img');
        for (var i = 0; i < imgs.length; i++) imgs[i].remove();
        img_box.style.setProperty('background-image', 'url(' + reader.result + ')', 'important');
        img_box.style.setProperty('background-repeat', 'no-repeat', 'important');
        img_box.style.setProperty('background-size', 'cover', 'important');
        img_box.style.setProperty('background-position', 'center center', 'important');
    };

    if (eve.target.files && eve.target.files[0]) reader.readAsDataURL(eve.target.files[0]);

    if (img_box.classList.contains('multiple')) {
        var other = img_box.cloneNode(true);
        other.classList.remove('dropimagehover');
        var parent = img_box.parentElement;
        if (parent) parent.appendChild(other);
        var input = other.querySelector('[type="file"]');
        if (input) input.value = '';
    }

    if (img_box) img_box.classList.add('dropnocontent');
});

Kumbia.utils.on('click', '.dropimage button', function (eve) {
    eve.preventDefault();
    var parent = this.closest('.dropimage');
    if (parent) {
        parent.removeAttribute('style');
        parent.classList.remove('dropimagehover');
        parent.classList.remove('dropnocontent');
        var input = parent.querySelector('input');
        if (input) input.value = '';
    }
});

Kumbia.utils.on('dragenter', '.dropimage', function (eve) {
    eve.preventDefault();
    this.classList.add('dropimagehover');
});
Kumbia.utils.on('dragover', '.dropimage', function (eve) {
    eve.preventDefault();
    this.classList.add('dropimagehover');
});
Kumbia.utils.on('dragleave', '.dropimage', function () {
    this.classList.remove('dropimagehover');
});
Kumbia.utils.on('drop', '.dropimage', function (eve) {
    console.log('Drop event fired (component) on:', eve.target);
    eve.preventDefault();
    eve.stopPropagation();
    this.classList.remove('dropimagehover');

    var files = eve.dataTransfer && eve.dataTransfer.files;
    console.log('Files detected (component):', files ? files.length : 0);
    if (!files || !files.length) return;

    var input = this.querySelector('[type="file"]');
    if (input) {
        try {
            input.files = files;
            console.log('Input.files updated (component)');
        } catch (e) {
            console.warn('Could not set input.files (component):', e);
        }
    }

    var img_box = this;
    var reader = new FileReader();
    reader.onloadend = function () {
        console.log('File read completed (component)');
        var imgs = img_box.querySelectorAll('img');
        for (var i = 0; i < imgs.length; i++) imgs[i].remove();
        img_box.style.setProperty('background-image', 'url(' + reader.result + ')', 'important');
        img_box.style.setProperty('background-repeat', 'no-repeat', 'important');
        img_box.style.setProperty('background-size', 'cover', 'important');
        img_box.style.setProperty('background-position', 'center center', 'important');
    };
    reader.readAsDataURL(files[0]);
    img_box.classList.add('dropnocontent');
});