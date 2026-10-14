const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require(require.resolve('playwright', {paths: [
    'C:/Users/Lobo Blanco/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
]}));

(async () => {
    const manual = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
    const before = process.argv.includes('--before');
    const height = (process.argv.find(arg => arg.startsWith('--height=')) || '--height=1.0').split('=')[1];
    assert(['1.0', '1.5', '2.0'].includes(height));
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        const page = await browser.newPage({viewport: {width: 1600, height: 1000}});
        const url = 'https://roleplus.app/ev/manuales/editor/es/' + manual.idu;
        // Renderizar los articulos originales leidos de produccion, sin acceso ni escrituras de sesion.
        await page.route(url, route => route.fulfill({contentType: 'text/html', body:
            '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/css/editor.min.css">' +
            '<link rel="stylesheet" href="/css/plantillas/' + manual.plantilla_idu + '.min.css">' +
            '<link rel="stylesheet" href="/codex-small-line-height.css"></head><body>' +
            '<main class="' + manual.formato + (manual.orientacion === 'h' ? ' landscape' : ' portrait') + '">' +
            manual.paginas.map(html => '<div>' + html + '</div>').join('') + '</main></body></html>'
        }));
        await page.route('**/codex-small-line-height.css', route => route.fulfill({contentType: 'text/css', body:
            'main {--small-line-height: ' + height + ';}'
        }));
        if (process.argv.includes('--candidate')) {
            await page.route('**/css/editor.min.css', route => route.fulfill({contentType: 'text/css', body: fs.readFileSync('web/css/editor.min.css', 'utf8')}));
        }
        await page.goto(url, {waitUntil: 'networkidle'});
        await page.evaluate(() => document.fonts.ready);
        const target = page.locator('main small').filter({hasText: 'Anota solo si hubo testigos vivos'});
        assert.equal(await target.count(), 1);
        const measure = () => target.evaluate(el => {
            const style = getComputedStyle(el), parent = getComputedStyle(el.parentElement);
            const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            const tops = [];
            let text;
            while ((text = walker.nextNode())) {
                for (let i = 0; i < text.length; i++) {
                    const range = document.createRange();
                    range.setStart(text, i); range.setEnd(text, i + 1);
                    const rect = range.getBoundingClientRect();
                    if (rect.width && !tops.some(top => Math.abs(top - rect.top) < 0.5)) tops.push(rect.top);
                }
            }
            tops.sort((a, b) => a - b);
            return {display: style.display, lineHeight: parseFloat(style.lineHeight), fontSize: parseFloat(style.fontSize),
                parentLineHeight: parent.lineHeight, spacing: tops.slice(1).map((top, i) => top - tops[i]), parent: el.parentElement.tagName};
        });
        const value = await measure();
        console.log(JSON.stringify(value));
        assert(value.spacing.length, 'La nota debe ocupar varias lineas');
        if (!before) {
            assert.equal(value.display, 'block');
            for (const gap of value.spacing) assert(Math.abs(gap - value.lineHeight) < 0.6, 'Interlineado real distinto del elegido');
            const paragraphs = await page.locator('main p').evaluateAll(els => els.map(el => ({font: getComputedStyle(el).fontSize, height: getComputedStyle(el).lineHeight})));
            assert(paragraphs.length);
            await page.emulateMedia({media: 'print'});
            const printed = await measure();
            for (const gap of printed.spacing) assert(Math.abs(gap - printed.lineHeight) < 0.6);
            console.log('PASS: nota original del manual en pantalla e impresion; interlineado medido');
        }
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
