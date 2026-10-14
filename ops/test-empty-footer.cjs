const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require(require.resolve('playwright', {paths: [
    'C:/Users/Lobo Blanco/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
]}));

(async () => {
    const manual = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        const page = await browser.newPage({viewport: {width: 1600, height: 1000}});
        const url = 'https://roleplus.app/ev/manuales/editor/es/' + manual.idu;
        await page.route(url, route => route.fulfill({contentType: 'text/html', body:
            '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/css/editor.min.css">' +
            '<link rel="stylesheet" href="/css/plantillas/' + manual.plantilla_idu + '.min.css"></head><body>' +
            '<main class="' + manual.formato + (manual.orientacion === 'h' ? ' landscape' : ' portrait') + '">' +
            manual.paginas.map((html, i) => '<div contenteditable="true" data-idu="test-' + i + '">' + html + '</div>').join('') +
            '</main><script src="/javascript/editor.min.js"></script></body></html>'
        }));
        if (process.argv.includes('--candidate')) {
            await page.route('**/css/editor.min.css', route => route.fulfill({contentType: 'text/css', body: fs.readFileSync('web/css/editor.min.css', 'utf8')}));
        }
        await page.goto(url, {waitUntil: 'networkidle'});
        await page.evaluate(() => document.fonts.ready);
        const footer = page.locator('main footer[data-empty-helper="true"]').first();
        assert(await footer.count(), 'Pie vacio del manual original no disponible');
        const measure = () => footer.evaluate(el => {
            const style = getComputedStyle(el), image = getComputedStyle(el, '::before'), counter = getComputedStyle(el, '::after');
            return {position: style.position, height: style.height, imageDisplay: image.display,
                image: image.backgroundImage, counterDisplay: counter.display, counter: counter.content};
        });
        const empty = await measure();
        console.log(JSON.stringify(empty));
        if (!process.argv.includes('--before')) {
            assert.equal(empty.position, 'absolute');
            assert.notEqual(empty.imageDisplay, 'none');
            assert.notEqual(empty.counterDisplay, 'none');
            assert.notEqual(empty.image, 'none');
            assert(empty.counter.includes('counter'));
            await page.emulateMedia({media: 'print'});
            assert.deepEqual(await measure(), empty);
            // Comparar con contenido sin iniciar el guardado del manual.
            await footer.evaluate(el => { el.removeAttribute('data-empty-helper'); el.textContent = 'Pie de prueba'; });
            assert.deepEqual(await measure(), empty);
            console.log('PASS: pie original vacio y con texto, imagen y numero visibles, pantalla e impresion');
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
