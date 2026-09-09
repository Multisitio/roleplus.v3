const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require(require.resolve('playwright', { paths: [process.env.NODE_PATH || process.cwd()] }));
(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent('<form><label><div class="dropimage dropnocontent"><button type="button"><img alt="Quitar" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"></button><input type="file"><img class="preview"><input type="hidden" value="old.gif"></div></label></form>');
        await page.evaluate(() => {
            window.Kumbia = { utils: { on(event, selector, callback) {
                document.addEventListener(event, e => {
                    const target = e.target.closest(selector);
                    if (target) callback.call(target, e);
                });
            } } };
        });
        if (process.argv[3] === '--bundle') {
            const bundle = fs.readFileSync(path.resolve(__dirname, '../../web/javascript/principal.min.js'), 'utf8');
            const start = bundle.indexOf('Kumbia.utils.on("paste","textarea",');
            const end = bundle.indexOf('window.replaceAccents=', start);
            assert(start >= 0 && end > start);
            await page.addScriptTag({ content: bundle.slice(start, end).replace(/,$/, ';') });
        } else {
            await page.addScriptTag({ path: path.resolve(__dirname, '../../web/javascript/principal/4-drop-or-paste-image.js') });
        }
        const fixture = process.argv[2];
        await page.locator('input[type=file]').setInputFiles(fixture);
        await page.waitForFunction(() => document.querySelector('.dropimage').style.backgroundImage);
        assert.equal(await page.locator('.dropimage > img').count(), 0);
        assert.equal(await page.locator('button img').count(), 1);
        await page.evaluate(() => {
            const drop = document.querySelector('.dropimage');
            const transfer = new DataTransfer();
            transfer.items.add(document.querySelector('input[type=file]').files[0]);
            drop.dispatchEvent(new DragEvent('drop', { bubbles: true, dataTransfer: transfer }));
        });
        await page.waitForTimeout(200);
        assert.equal(await page.locator('button img').count(), 1);
        await page.locator('button').click();
        assert.equal(await page.locator('input[type=hidden]').inputValue(), '');
        assert.equal(await page.locator('input[type=file]').inputValue(), '');
        assert.equal(await page.locator('button img').count(), 1);
        assert.equal(await page.locator('.dropnocontent').count(), 0);
        assert.deepEqual(errors, []);
        console.log('PASS: picker, drop, X icon, removal and hidden profile reference');
    } finally {
        await browser.close();
    }
})();
