// Ejecutar con Playwright y pdf-lib disponibles en NODE_PATH y Chrome instalado.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { PDFDocument } = require('pdf-lib');

(async () => {
    const css = fs.readFileSync(process.argv[2] || 'web/css/editor.min.css', 'utf8');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        for (const booklet of [false, true]) {
            const page = await browser.newPage({ viewport: { width: 1123, height: 794 } });
            await page.route('https://editor.test/editor.css', route => route.fulfill({ contentType: 'text/css', body: css }));
            await page.route('https://editor.test/formato.css', route => route.fulfill({
                contentType: 'text/css', body: '@page { size: ' + (booklet ? 'A4 landscape' : 'A5') + '; margin: 0; }'
            }));
            await page.route('https://editor.test/manual', route => route.fulfill({ contentType: 'text/html', body:
                '<!doctype html><html><head><link rel="stylesheet" href="/editor.css"><link rel="stylesheet" href="/formato.css"></head>' +
                '<body class="' + (booklet ? 'booklet-mode' : '') + '"><main class="a5 portrait">' +
                [1, 2, 3, 4].map(n => '<div><article><h1>Pagina ' + n + '</h1></article></div>').join('') + '</main></body></html>'
            }));
            await page.goto('https://editor.test/manual');
            await page.emulateMedia({ media: 'print' });
            const pdf = await PDFDocument.load(await page.pdf({ preferCSSPageSize: true, printBackground: true }));
            assert.equal(pdf.getPageCount(), booklet ? 2 : 4);
            const size = pdf.getPage(0).getSize();
            assert.ok(Math.abs(size.width - (booklet ? 841.89 : 419.53)) < 2);
            assert.ok(Math.abs(size.height - (booklet ? 595.28 : 595.28)) < 2);
            if (booklet) {
                const boxes = await page.locator('main > div').evaluateAll(elements => elements.map(e => {
                    const r = e.getBoundingClientRect();
                    return { x: r.x, y: r.y, width: r.width, height: r.height };
                }));
                assert.equal(boxes[0].y, boxes[1].y);
                assert.ok(boxes[1].x >= boxes[0].x + boxes[0].width);
                assert.ok(Math.abs(boxes[0].width - 148 * 96 / 25.4) < 1);
                assert.ok(Math.abs(boxes[0].height - 210 * 96 / 25.4) < 1);
            }
            await page.close();
        }
        console.log('Correcto: cuatro A5 producen cuatro hojas normales o dos A4 apaisados en libreto.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
