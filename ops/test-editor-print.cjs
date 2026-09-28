// Run with Playwright available on NODE_PATH and Chrome installed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 560, height: 794 } });
        const css = fs.readFileSync('web/css/editor.min.css', 'utf8');
        const image = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="148" height="210"><rect width="148" height="210" fill="black"/></svg>');
        await page.setContent(`<style>${css}</style><style>
            main > div { background: #333; }
            main > div:nth-child(even) { background: #abc; }
            #cover img { width: 148mm; }
            #content { padding: 12px; }
            #empty { line-height: 21px; }
        </style><main class="a5 portrait">
            <div><article id="cover"><a href="#content"><img src="${image}"></a></article></div>
            <div contenteditable="true"><article id="content">
                <header id="empty" data-empty-helper="true"><span class="editor-badge" data-editor-helper="badge">header</span><br data-editor-helper="caret"></header>
                <aside>Contenido del manual</aside>
            </article></div>
        </main><aside>Controles del editor</aside><div class="ajax show">Modal</div>`);
        const measure = () => page.evaluate(() => {
            const selectors = ['#cover img', '#content', '#empty', '#content aside', 'main > div:nth-child(even)'];
            return selectors.map(selector => {
                const element = document.querySelector(selector);
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return { selector, width: rect.width, height: rect.height, display: style.display, padding: style.padding, background: style.backgroundColor };
            });
        });
        const screen = await measure();
        await page.emulateMedia({ media: 'print' });
        assert.deepEqual(await measure(), screen, 'Print must preserve content geometry, linked images, empty blocks and even-page colors');
        for (const selector of ['body > aside', '.ajax', '.editor-badge']) {
            assert.equal(await page.locator(selector).evaluate(element => getComputedStyle(element).display), 'none');
        }
        console.log('PASS: print matches editor dimensions, content, empty blocks and page colors; controls are hidden');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
