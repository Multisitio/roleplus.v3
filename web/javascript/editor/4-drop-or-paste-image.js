(function () {
    const renderPreview = (img_box, result) => {
        const imgs = img_box.querySelectorAll(':scope > img');
        for (let i = 0; i < imgs.length; i++) imgs[i].remove();
        img_box.style.backgroundImage = 'none';
        const preview = document.createElement('img');
        preview.alt = '';
        preview.src = result;
        img_box.appendChild(preview);
        img_box.classList.add('dropnocontent');
    };

    Kumbia.utils.renderDropImagePreview = renderPreview;

    const cloneIfNeeded = (container) => {
        if (!container || !container.classList.contains('multiple')) return;
        const box_container = container.parentElement;
        const images = document.querySelectorAll('.modal .dropimage').length;
        if (images < 4) {
            const other = container.cloneNode(true);
            const otherDrop = other.querySelector('.dropimage') || (other.classList.contains('dropimage') ? other : null);
            if (otherDrop) {
                otherDrop.classList.remove('dropimagehover', 'dropnocontent');
                otherDrop.removeAttribute('style');
                otherDrop.querySelectorAll(':scope > img').forEach(img => img.remove());
                const otherInput = otherDrop.querySelector('[type="file"]');
                if (otherInput) otherInput.value = '';
            }
            if (box_container) box_container.appendChild(other);
        }
    };

    Kumbia.utils.on('paste', 'textarea', function (eve) {
        const clipboard = (eve.clipboardData || window.clipboardData);
        if (!clipboard || !clipboard.files || !clipboard.files.length) return;

        const form = this.closest('form');
        if (!form) return;
        const img_boxes = form.querySelectorAll('.dropimage');
        if (!img_boxes.length) return;

        const img_box = img_boxes[img_boxes.length - 1];
        const input = img_box.querySelector('[type="file"]');
        if (input) {
            input.files = clipboard.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(img_box, reader.result);
        reader.readAsDataURL(clipboard.files[0]);
        cloneIfNeeded(img_box.parentElement);
    });

    Kumbia.utils.on('change', '.dropimage [type="file"]', function () {
        const img_box = this.closest('.dropimage');
        if (!img_box || !this.files || !this.files[0]) return;

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(img_box, reader.result);
        reader.readAsDataURL(this.files[0]);
        cloneIfNeeded(img_box.parentElement);
    });

    Kumbia.utils.on('click', '.dropimage button, .dropimage .quitar', function (eve) {
        eve.preventDefault();
        eve.stopPropagation();

        const img_box = this.closest('.dropimage');
        const container = img_box ? img_box.parentElement : null;
        let clean = 0;

        if (container && container.classList.contains('multiple')) {
            const images = document.querySelectorAll('.modal .dropimage').length;
            if (images > 1) {
                container.remove();
            } else {
                clean = 1;
            }

            const empty_box = document.querySelectorAll('.modal .dropimage:not(.dropnocontent)').length;
            if (empty_box < 1 && images < 5) {
                const new_box = container.cloneNode(true);
                if (container.parentElement) container.parentElement.appendChild(new_box);
                const new_img_box = new_box.querySelector('.dropimage') || (new_box.classList.contains('dropimage') ? new_box : null);
                if (new_img_box) {
                    new_img_box.removeAttribute('style');
                    new_img_box.querySelectorAll(':scope > img').forEach(img => img.remove());
                    new_img_box.classList.remove('dropimagehover', 'dropnocontent');
                    const inp = new_img_box.querySelector('input');
                    if (inp) inp.value = '';
                }
            }
        } else {
            clean = 1;
        }

        if (clean === 1 && img_box) {
            img_box.removeAttribute('style');
            img_box.classList.remove('dropimagehover', 'dropnocontent');
            const imgs = img_box.querySelectorAll(':scope > img');
            for (let i = 0; i < imgs.length; i++) imgs[i].remove();
            const inp = img_box.querySelector('input');
            if (inp) inp.value = '';
        }
    });

    Kumbia.utils.on('dragenter dragover', '.dropimage', function (eve) {
        eve.preventDefault();
        this.classList.add('dropimagehover');
    });

    Kumbia.utils.on('dragleave', '.dropimage', function () {
        this.classList.remove('dropimagehover');
    });

    Kumbia.utils.on('drop', '.dropimage', function (eve) {
        eve.preventDefault();
        eve.stopPropagation();
        this.classList.remove('dropimagehover');

        const files = eve.dataTransfer && eve.dataTransfer.files;
        if (!files || !files.length) return;

        const input = this.querySelector('[type="file"]');
        if (input) {
            try {
                input.files = files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                console.warn('[dropimage] Could not set input.files:', e);
            }
        }

        const reader = new FileReader();
        reader.onloadend = () => renderPreview(this, reader.result);
        reader.readAsDataURL(files[0]);
        cloneIfNeeded(this.parentElement);
    });
})();
