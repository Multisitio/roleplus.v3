/* 4-drop-or-paste-image.js - Vanilla JS version */

document.body.addEventListener('paste', e => {
	const el = e.target;
	if (el.tagName !== 'TEXTAREA') return;
	const clipboard = e.clipboardData || e.originalEvent.clipboardData;
	const form = el.closest('form');
	const imgBox = form.querySelector('.dropimage:last-of-type');
	if (!imgBox) return;
	const label = imgBox.parentElement;
	const boxContainer = label.parentElement;

	const input = imgBox.querySelector('[type="file"]');
	if (!clipboard.files.length) return;
	input.files = clipboard.files;

	const data = clipboard.items[0].getAsFile();
	const reader = new FileReader();
	reader.onloadend = () => {
		imgBox.style.backgroundImage = 'url(' + reader.result + ')';
		imgBox.style.backgroundRepeat = 'no-repeat';
		imgBox.style.backgroundSize = 'cover';
	};
	reader.readAsDataURL(data);

	if (label.classList.contains('multiple')) {
		const images = document.querySelectorAll('.modal .dropimage').length;
		if (images < 4) {
			const other = label.cloneNode(true);
			boxContainer.appendChild(other);
			const newImgBox = other.querySelector('.dropimage');
			newImgBox.classList.remove('dropimagehover');
			other.querySelector('[type="file"]').value = '';
		}
	}

	imgBox.classList.add('dropnocontent');
});

document.body.addEventListener('change', e => {
	const el = e.target;
	if (el.type !== 'file' || !el.parentElement.classList.contains('dropimage')) return;
	const imgBox = el.parentElement;
	const label = imgBox.parentElement;
	const boxContainer = label.parentElement;
	const reader = new FileReader();

	reader.onloadend = () => {
		imgBox.style.backgroundImage = 'url(' + reader.result + ')';
		imgBox.style.backgroundRepeat = 'no-repeat';
		imgBox.style.backgroundSize = 'cover';
	};
	reader.readAsDataURL(e.target.files[0]);

	if (label.classList.contains('multiple')) {
		const images = document.querySelectorAll('.modal .dropimage').length;
		if (images < 4) {
			const other = label.cloneNode(true);
			boxContainer.appendChild(other);
			const newImgBox = other.querySelector('.dropimage');
			newImgBox.classList.remove('dropimagehover');
			other.querySelector('[type="file"]').value = '';
		}
	}

	imgBox.classList.add('dropnocontent');
});

document.body.addEventListener('click', e => {
	const el = e.target;
	if (el.tagName !== 'BUTTON' || !el.parentElement.classList.contains('dropimage')) return;
	e.preventDefault();
	const imgBox = el.parentElement;
	const label = imgBox.parentElement;
	const boxContainer = label.parentElement;
	let clean = 0;

	if (label.classList.contains('multiple')) {
		const images = document.querySelectorAll('.modal .dropimage').length;
		if (images > 1) {
			label.remove();
		} else {
			clean = 1;
		}
		const emptyBox = document.querySelectorAll('.modal .dropimage:not(.dropnocontent)').length;
		if (emptyBox < 1 && images < 5) {
			const newBox = label.cloneNode(true);
			boxContainer.appendChild(newBox);
			const newImgBox = newBox.querySelector('.dropimage');
			newImgBox.classList.remove('dropimagehover');
			newBox.querySelector('[type="file"]').value = '';
		}
	} else {
		clean = 1;
	}

	if (clean === 1) {
		imgBox.style = '';
		imgBox.classList.remove('dropimagehover', 'dropnocontent');
		imgBox.querySelectorAll('img').forEach(img => img.remove());
		imgBox.querySelector('input').value = '';
	}
});

document.addEventListener('dragenter', e => {
	const el = e.target.closest('.dropimage');
	if (el) {
		e.preventDefault();
		el.classList.add('dropimagehover');
	}
});

document.addEventListener('dragover', e => {
	const el = e.target.closest('.dropimage');
	if (el) {
		e.preventDefault();
		el.classList.add('dropimagehover');
	}
});

document.addEventListener('dragleave', e => {
	const el = e.target.closest('.dropimage');
	if (el) el.classList.remove('dropimagehover');
});
