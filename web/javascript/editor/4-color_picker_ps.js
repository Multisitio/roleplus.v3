/**
 * Photoshop-like color picker.
 *
 * Usage:
 * 1. Explicit target: <button class="web-color-picker" data-target="#input_hex"></button>
 * 2. Relative target: <button class="web-color-picker"></button>
 * 3. Manual target: psColorPickerTarget = myElement;
 */
const PS_COLOR_HISTORY_LIMIT = 20;
const PS_COLOR_HISTORY_KEY = 'roleplus_color_history';
const PS_COLOR_MAP_SIZE = 256;

let psColorPickerTarget = null;

document.addEventListener('DOMContentLoaded', initPSColorPicker);

function initPSColorPicker() {
    const picker = document.getElementById('ps-color-picker');
    if (!picker) return;

    const elements = {
        map: picker.querySelector('.map'),
        mapCanvas: picker.querySelector('.map canvas'),
        mapMarker: picker.querySelector('.map .marker'),
        hue: picker.querySelector('.hue'),
        hueMarker: picker.querySelector('.hue .marker'),
        currentPreview: picker.querySelector('.preview .curr'),
        nextPreview: picker.querySelector('.preview .next'),
        hexInput: picker.querySelector('.hex input'),
        webSafeInput: picker.querySelector('.web-safe input'),
        history: picker.querySelector('.history'),
        okButton: picker.querySelector('.actions .ok'),
        cancelButton: picker.querySelector('.actions .cancel'),
        valueInputs: picker.querySelectorAll('input[data-type]')
    };

    if (!elements.mapCanvas || !elements.map || !elements.hue) return;

    const valueInputs = Array.from(elements.valueInputs).reduce((inputs, input) => {
        inputs[input.dataset.type] = input;
        return inputs;
    }, {});
    const mapContext = elements.mapCanvas.getContext('2d', { alpha: false });
    let color = { h: 0, s: 100, b: 100 };
    let activeTrigger = null;
    let dragging = null;
    let history = readHistory();
    let lastRenderedHue = null;
    let lastRenderedWebSafe = null;

    function clamp(value, min, max) {
        const number = Number(value);
        if (!Number.isFinite(number)) return min;
        return Math.min(max, Math.max(min, number));
    }

    function clampChannel(value) {
        return Math.round(clamp(value, 0, 255));
    }

    function normalizeHex(value) {
        const hex = String(value || '').trim().replace(/^#/, '').toLowerCase();
        if (/^[0-9a-f]{3}$/.test(hex)) {
            return `#${hex.split('').map((char) => char + char).join('')}`;
        }
        if (/^[0-9a-f]{6}$/.test(hex)) {
            return `#${hex}`;
        }
        return null;
    }

    function readHistory() {
        try {
            const savedHistory = JSON.parse(localStorage.getItem(PS_COLOR_HISTORY_KEY) || '[]');
            if (!Array.isArray(savedHistory)) return [];

            return savedHistory
                .map(normalizeHex)
                .filter(Boolean)
                .slice(0, PS_COLOR_HISTORY_LIMIT);
        } catch (error) {
            return [];
        }
    }

    function saveHistory(hex) {
        history = [
            hex,
            ...history.filter((savedHex) => savedHex !== hex)
        ].slice(0, PS_COLOR_HISTORY_LIMIT);

        localStorage.setItem(PS_COLOR_HISTORY_KEY, JSON.stringify(history));
    }

    function hsbToRgb(h, s, brightness) {
        const safeHue = clamp(h, 0, 360);
        const hue = safeHue === 360 ? 0 : safeHue;
        const saturation = clamp(s, 0, 100) / 100;
        const value = clamp(brightness, 0, 100) / 100;
        const c = value * saturation;
        const x = c * (1 - Math.abs((hue / 60) % 2 - 1));
        const m = value - c;
        let r = 0;
        let g = 0;
        let b = 0;

        if (hue < 60) {
            r = c;
            g = x;
        } else if (hue < 120) {
            r = x;
            g = c;
        } else if (hue < 180) {
            g = c;
            b = x;
        } else if (hue < 240) {
            g = x;
            b = c;
        } else if (hue < 300) {
            r = x;
            b = c;
        } else {
            r = c;
            b = x;
        }

        return {
            r: clampChannel((r + m) * 255),
            g: clampChannel((g + m) * 255),
            b: clampChannel((b + m) * 255)
        };
    }

    function rgbToHsb(r, g, b) {
        const red = clampChannel(r) / 255;
        const green = clampChannel(g) / 255;
        const blue = clampChannel(b) / 255;
        const max = Math.max(red, green, blue);
        const min = Math.min(red, green, blue);
        const delta = max - min;
        let h = 0;

        if (delta !== 0) {
            if (max === red) {
                h = ((green - blue) / delta) % 6;
            } else if (max === green) {
                h = (blue - red) / delta + 2;
            } else {
                h = (red - green) / delta + 4;
            }
        }

        return {
            h: (h * 60 + 360) % 360,
            s: max === 0 ? 0 : (delta / max) * 100,
            b: max * 100
        };
    }

    function rgbToHex(r, g, b) {
        return `#${[r, g, b].map((channel) => clampChannel(channel).toString(16).padStart(2, '0')).join('')}`;
    }

    function hexToRgb(hex) {
        const normalizedHex = normalizeHex(hex);
        if (!normalizedHex) return null;

        return {
            r: parseInt(normalizedHex.slice(1, 3), 16),
            g: parseInt(normalizedHex.slice(3, 5), 16),
            b: parseInt(normalizedHex.slice(5, 7), 16)
        };
    }

    function snapToWeb(value) {
        return Math.round(clampChannel(value) / 51) * 51;
    }

    function webSafeEnabled() {
        return Boolean(elements.webSafeInput?.checked);
    }

    function getSelectedRgb() {
        const rgb = hsbToRgb(color.h, color.s, color.b);
        if (!webSafeEnabled()) return rgb;

        return {
            r: snapToWeb(rgb.r),
            g: snapToWeb(rgb.g),
            b: snapToWeb(rgb.b)
        };
    }

    function getSelectedHex() {
        const rgb = getSelectedRgb();
        return rgbToHex(rgb.r, rgb.g, rgb.b);
    }

    function setColorFromRgb(rgb) {
        const hsb = rgbToHsb(rgb.r, rgb.g, rgb.b);
        color = {
            h: hsb.h,
            s: hsb.s,
            b: hsb.b
        };
    }

    function setColorFromHex(hex) {
        const rgb = hexToRgb(hex);
        if (!rgb) return false;

        setColorFromRgb(rgb);
        return true;
    }

    function drawMap() {
        const isWebSafe = webSafeEnabled();
        if (color.h === lastRenderedHue && isWebSafe === lastRenderedWebSafe) return;

        lastRenderedHue = color.h;
        lastRenderedWebSafe = isWebSafe;

        const imageData = mapContext.createImageData(PS_COLOR_MAP_SIZE, PS_COLOR_MAP_SIZE);
        const data = imageData.data;

        for (let y = 0; y < PS_COLOR_MAP_SIZE; y++) {
            const brightness = 100 - (y / (PS_COLOR_MAP_SIZE - 1)) * 100;

            for (let x = 0; x < PS_COLOR_MAP_SIZE; x++) {
                const saturation = (x / (PS_COLOR_MAP_SIZE - 1)) * 100;
                const rgb = hsbToRgb(color.h, saturation, brightness);
                const index = (y * PS_COLOR_MAP_SIZE + x) * 4;

                data[index] = isWebSafe ? snapToWeb(rgb.r) : rgb.r;
                data[index + 1] = isWebSafe ? snapToWeb(rgb.g) : rgb.g;
                data[index + 2] = isWebSafe ? snapToWeb(rgb.b) : rgb.b;
                data[index + 3] = 255;
            }
        }

        mapContext.putImageData(imageData, 0, 0);
    }

    function renderHistory() {
        elements.history.replaceChildren();

        history.forEach((hex) => {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.title = hex;
            swatch.setAttribute('aria-label', hex);
            swatch.style.backgroundColor = hex;
            swatch.addEventListener('click', () => {
                setColorFromHex(hex);
                updateUI();
            });
            elements.history.appendChild(swatch);
        });
    }

    function updateValueInputs() {
        const rgb = getSelectedRgb();

        valueInputs.h.value = Math.round(color.h);
        valueInputs.s.value = Math.round(color.s);
        valueInputs.b.value = Math.round(color.b);
        valueInputs.r.value = rgb.r;
        valueInputs.g.value = rgb.g;
        valueInputs.b_rgb.value = rgb.b;
        elements.hexInput.value = getSelectedHex().replace('#', '');
    }

    function updateUI(options = {}) {
        const preserveInputs = Boolean(options.preserveInputs);
        const hex = getSelectedHex();

        drawMap();
        elements.mapMarker.style.left = `${color.s}%`;
        elements.mapMarker.style.top = `${100 - color.b}%`;
        elements.hueMarker.style.top = `${(color.h / 360) * 100}%`;
        elements.nextPreview.style.backgroundColor = hex;

        if (!preserveInputs) {
            updateValueInputs();
        }

        renderHistory();
    }

    function handleMap(event) {
        const rect = elements.map.getBoundingClientRect();
        color.s = clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100);
        color.b = clamp(100 - ((event.clientY - rect.top) / rect.height) * 100, 0, 100);
        updateUI();
    }

    function handleHue(event) {
        const rect = elements.hue.getBoundingClientRect();
        color.h = clamp(((event.clientY - rect.top) / rect.height) * 360, 0, 360);
        updateUI();
    }

    function resolveTarget(trigger) {
        if (trigger.dataset.target) {
            try {
                return document.querySelector(trigger.dataset.target);
            } catch (error) {
                return null;
            }
        }

        return trigger.closest('section')?.querySelector('input[type="text"]') || trigger;
    }

    function cssColorToHex(cssColor) {
        if (!cssColor || cssColor === 'transparent') return null;

        const normalizedHex = normalizeHex(cssColor);
        if (normalizedHex) return normalizedHex;

        const probe = document.createElement('div');
        probe.style.color = cssColor;
        document.body.appendChild(probe);
        const computedColor = window.getComputedStyle(probe).color;
        document.body.removeChild(probe);

        const channels = computedColor.match(/[\d.]+/g);
        if (!channels || channels.length < 3 || Number(channels[3]) === 0) return null;

        return rgbToHex(channels[0], channels[1], channels[2]);
    }

    function getInitialHex(trigger, target) {
        if (target?.tagName === 'INPUT') {
            const targetHex = normalizeHex(target.value);
            if (targetHex) return targetHex;
        }

        return cssColorToHex(trigger.style.backgroundColor)
            || cssColorToHex(window.getComputedStyle(trigger).backgroundColor)
            || '#ffffff';
    }

    function openPicker(trigger) {
        activeTrigger = trigger;
        psColorPickerTarget = resolveTarget(trigger);

        const initialHex = getInitialHex(trigger, psColorPickerTarget);
        elements.currentPreview.style.backgroundColor = initialHex;
        setColorFromHex(initialHex);
        picker.style.display = 'block';
        updateUI();
    }

    function saveSelectedColor() {
        const hex = getSelectedHex();
        const target = psColorPickerTarget || activeTrigger;

        if (target?.tagName === 'INPUT') {
            target.value = hex;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (activeTrigger?.classList.contains('web-color-picker')) {
            activeTrigger.style.backgroundColor = hex;
        }

        saveHistory(hex);

        if (target) {
            target.dispatchEvent(new CustomEvent('ps-color-saved', {
                detail: { hex, target },
                bubbles: true
            }));
        }

        picker.style.display = 'none';
    }

    picker.addEventListener('mousedown', (event) => {
        if (event.target.closest('.map')) {
            dragging = 'map';
            handleMap(event);
        } else if (event.target.closest('.hue')) {
            dragging = 'hue';
            handleHue(event);
        }
    });

    document.addEventListener('mousemove', (event) => {
        if (dragging === 'map') {
            handleMap(event);
        } else if (dragging === 'hue') {
            handleHue(event);
        }
    });

    document.addEventListener('mouseup', () => {
        dragging = null;
    });

    elements.valueInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const type = input.dataset.type;

            if (type === 'h') {
                color.h = clamp(input.value, 0, 360);
            } else if (type === 's') {
                color.s = clamp(input.value, 0, 100);
            } else if (type === 'b') {
                color.b = clamp(input.value, 0, 100);
            } else {
                setColorFromRgb({
                    r: valueInputs.r.value,
                    g: valueInputs.g.value,
                    b: valueInputs.b_rgb.value
                });
            }

            updateUI({ preserveInputs: true });
        });

        input.addEventListener('change', () => updateUI());
    });

    elements.hexInput.addEventListener('input', () => {
        if (setColorFromHex(elements.hexInput.value)) {
            updateUI({ preserveInputs: true });
        }
    });

    elements.hexInput.addEventListener('change', () => updateUI());
    elements.webSafeInput.addEventListener('change', () => updateUI());

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.web-color-picker');
        if (!trigger) return;

        openPicker(trigger);
        event.stopPropagation();
    });

    elements.okButton.addEventListener('click', saveSelectedColor);
    elements.cancelButton.addEventListener('click', () => {
        picker.style.display = 'none';
    });

    updateUI();
}
