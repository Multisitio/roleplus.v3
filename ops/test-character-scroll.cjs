// Inspect the published character with the new bundle. No real data is submitted.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {chromium} = require('playwright');
(async () => {
    const browser = await chromium.launch({headless:true,channel:'chrome'});
    const page = await browser.newPage({viewport:{width:1100,height:750}});
    const errors=[];
    page.on('pageerror',err=>errors.push(err.message));
    if(process.argv[2]!=='--published') await page.route('**/javascript/fichas.min.js*',route=>route.fulfill({contentType:'application/javascript',body:fs.readFileSync('web/javascript/fichas.min.js','utf8')}));
    await page.route('**/ev/personajes/**',route=>route.request().method()==='POST' ? route.abort() : route.continue());
    await page.goto('https://roleplus.app/ev/personajes/montar/588bc2f2187e/7694c290dc7a',{waitUntil:'domcontentloaded'});
    await page.waitForFunction(()=>!!window.Kumbia);
    const bar=page.locator('.fixed-bottom-right');
    assert.equal(await bar.getAttribute('style'),null);
    await page.evaluate(()=>window.scrollTo(0,800));
    await page.waitForFunction(()=>document.querySelector('.fixed-bottom-right').hidden);
    await page.evaluate(()=>window.scrollTo(0,400));
    await page.waitForFunction(()=>!document.querySelector('.fixed-bottom-right').hidden);
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.waitForFunction(()=>!document.querySelector('.fixed-bottom-right').hidden);
    assert.equal(await bar.getAttribute('style'),null);
    assert.equal(await page.evaluate(()=>typeof window.jQuery),'undefined');
    assert.deepEqual(errors,[]);
    await browser.close();
    console.log('PASS: published character, native scrolling, visible controls on scroll up, no script errors or new inline styles');
})().catch(err=>{console.error(err);process.exit(1);});
