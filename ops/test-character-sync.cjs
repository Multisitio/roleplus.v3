const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
(async () => {
    const browser = await chromium.launch({headless:true,channel:'chrome'});
    let state={idu:'same-id',revision:1,datos:{vida:'10',equipo:'espada'},editable:true}, requests=[],fail=false;
    const context=await browser.newContext();
    await context.route('https://character.test/**',async route => {
        const req=route.request();
        if(req.method()==='POST') {
            if(fail) {await route.fulfill({status:401,contentType:'application/json',body:'{"success":false}'}); return;}
            const fd=await new Response(req.postDataBuffer(),{headers:{'Content-Type':req.headers()['content-type']}}).formData();
            const names=JSON.parse(fd.get('campos')); requests.push(names);
            names.forEach(name => {state.datos[name]=fd.get(name) || '';}); state.revision++;
            await route.fulfill({contentType:'application/json',body:JSON.stringify({...state,success:true,url:'/montar/sheet/same-id'})});
        } else if(req.url().includes('/estado/')) {
            await route.fulfill({contentType:'application/json',body:JSON.stringify({...state,success:true})});
        } else {
            const initial=JSON.stringify(state).replaceAll('"','&quot;');
            await route.fulfill({contentType:'text/html',body:`<main><form data-autosave="salvar"><input name="vida" type="text" value="${state.datos.vida}"><input name="equipo" type="text" value="${state.datos.equipo}"><input name="idu" type="hidden" value="same-id"><input name="fichas_idu" type="hidden" value="sheet"><input name="estado_inicial" type="hidden" value="${initial}"><div class="fixed-bottom-right"><span data-save-status></span><button type="submit" name="action" value="salvar">Salvar</button></div></form></main>`});
        }
    });
    const a=await context.newPage(),b=await context.newPage();
    for(const page of [a,b]) {await page.goto('https://character.test/montar/sheet/same-id'); await page.addScriptTag({path:path.resolve(process.argv[2] || 'web/javascript/fichas/4-autosave.js')});}
    const saved=page=>page.waitForFunction(()=>document.querySelector('[data-save-status]').textContent==='Cambios guardados');
    await a.fill('[name=vida]','9'); await a.click('[value=salvar]'); await saved(a);
    await b.waitForFunction(()=>document.querySelector('[name=vida]').value==='9');
    await b.fill('[name=equipo]','arco');
    await a.fill('[name=vida]','8'); await a.click('[value=salvar]'); await saved(a);
    await saved(b);
    assert.deepEqual(state.datos,{vida:'8',equipo:'arco'});
    assert.ok(requests.every(names=>names.length===1),'Full snapshots sent');
    await a.fill('[name=vida]','7'); await a.click('[value=salvar]'); await saved(a);
    await b.fill('[name=vida]','6'); await b.click('[value=salvar]'); await saved(b);
    await a.waitForFunction(()=>document.querySelector('[name=vida]').value==='6');
    assert.equal(state.datos.vida,'6');
    fail=true;
    await b.fill('[name=vida]','5'); await b.click('[value=salvar]');
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').textContent.startsWith('Error al guardar'));
    assert.equal(await b.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}),true);
    assert.equal(state.datos.vida,'6');
    fail=false; await b.click('[value=salvar]'); await saved(b); assert.equal(state.datos.vida,'5');
    await browser.close();
    console.log('PASS: two open sheets, bidirectional updates, field merge, last save, failed save, exit warning, retry');
})().catch(e=>{console.error(e);process.exit(1);});
