const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(require.resolve('playwright', {paths: [
    'C:/Users/Lobo Blanco/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
]}));

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'chrome', args:
        process.env.ROLEPLUS_LOCAL_IP ? ['--host-resolver-rules=MAP roleplus.vh ' + process.env.ROLEPLUS_LOCAL_IP] : []
    });
    try {
        const page = await browser.newPage();
        if (process.argv[2] === '--fixture') {
            await page.setContent('<nav data-toolbar="contexto"><span></span></nav><nav data-toolbar="pinceles"><div class="pinceles-grupo" data-grupo="formato"><button class="grupo-trigger">Formato</button>' +
                ['left', 'center', 'right'].map(a => '<button data-tag="align_' + a + '">' + a + '</button>').join('') +
                '</div></nav><main><div contenteditable="true" data-idu="test"><article><p>Texto de prueba</p></article></div></main>');
            await page.addScriptTag({path: 'web/javascript/editor/5-pinceles.js'});
        } else {
            await page.goto(process.argv[2] || 'http://roleplus.vh/ev/manuales/editor/es/95eabe075659', {waitUntil: 'domcontentloaded'});
        }
        assert(await page.locator('main div[contenteditable] article p').count(), 'Manual editable no disponible');
        await page.evaluate(() => { window.alignmentRequests = []; });
        await page.route('**/ev/manuales/editor/**', async route => {
            if (route.request().method() !== 'POST') return route.continue();
            await route.fulfill({json: {success: true}});
        });
        // Interceptar exclusivamente la escritura de la prueba, conservando el manual real.
        await page.evaluate(() => {
            const fetchOriginal = window.fetch;
            window.fetch = function(url, options) {
                if (options && options.body instanceof FormData && options.body.get('action') === 'regla_actualizar_ajax') {
                    window.alignmentRequests.push(options.body.get('descripcion'));
                    return Promise.resolve({ok: true, status: 200, redirected: false, json: async () => ({success: true})});
                }
                return fetchOriginal.apply(this, arguments);
            };
        });
        const original = await page.evaluate(() => {
            const target = document.querySelector('main div[contenteditable] article p');
            const previous = target.getAttribute('style');
            target.dataset.alignmentTest = 'true';
            target.style.setProperty('color', 'rgb(11, 22, 33)');
            const range = document.createRange();
            range.selectNodeContents(target);
            range.collapse(true);
            target.closest('[contenteditable]').focus();
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.dispatchEvent(new Event('selectionchange'));
            return previous;
        });
        await page.locator('[data-grupo="formato"] .grupo-trigger').click();
        for (const alignment of ['center', 'right', 'left']) {
            await page.locator('[data-tag="align_' + alignment + '"]').click();
            const effective = await page.locator('[data-alignment-test]').evaluate(el => ({
                alignment: el.style.textAlign, effective: getComputedStyle(el).textAlign, color: el.style.color
            }));
            assert.equal(effective.alignment, alignment === 'left' ? '' : alignment);
            if (alignment !== 'left') assert.equal(effective.effective, alignment);
            assert.equal(effective.color, 'rgb(11, 22, 33)');
            await page.evaluate(() => window.flushPendingSaves());
            const saved = await page.evaluate(() => window.alignmentRequests.at(-1));
            assert(saved && saved.includes('data-alignment-test'));
            if (alignment !== 'left') assert(saved.includes('text-align: ' + alignment));
        }
        await page.locator('[data-alignment-test]').evaluate(el => el.setAttribute('style', 'text-align: right;'));
        await page.locator('[data-tag="align_left"]').click();
        assert.equal(await page.locator('[data-alignment-test]').getAttribute('style'), null);
        await page.locator('[data-alignment-test]').evaluate((el, previous) => {
            if (previous === null) el.removeAttribute('style'); else el.setAttribute('style', previous);
            delete el.dataset.alignmentTest;
        }, original);
        const icons = await page.locator('nav[data-toolbar="pinceles"] img').evaluateAll(els => els.map(el => el.getAttribute('src')));
        for (const icon of icons) assert(fs.existsSync(path.join('web', icon.replace(/^\//, ''))), icon);
        console.log('PASS: ' + (process.argv[2] === '--fixture' ? 'fixture Chromium' : 'manual real') + ', centro/derecha efectivos, izquierda limpia, otras propiedades, HTML de guardado e iconos');
    } finally {
        await browser.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
