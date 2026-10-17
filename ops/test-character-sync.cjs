const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
(async () => {
    const browser = await chromium.launch({headless:true,channel:'chrome'});
    let state={idu:'same-id',revision:1,datos:{vida:'10',equipo:'espada',notas:'Primera línea\r\nSegunda línea'},editable:true}, requests=[],fail=false,failSync=false;
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
            if(failSync) { await route.fulfill({status:503,body:''}); return; }
            await route.fulfill({contentType:'application/json',body:JSON.stringify({...state,success:true})});
        } else {
            const initial=JSON.stringify(state).replaceAll('"','&quot;');
            await route.fulfill({contentType:'text/html',body:`<main><form data-autosave="salvar"><input name="vida" type="text" value="${state.datos.vida}"><input name="equipo" type="text" value="${state.datos.equipo}"><textarea name="notas">${state.datos.notas}</textarea><input name="idu" type="hidden" value="same-id"><input name="fichas_idu" type="hidden" value="sheet"><input name="estado_inicial" type="hidden" value="${initial}"><div class="fixed-bottom-right"><span data-save-status></span><button type="submit" name="action" value="salvar">Salvar</button></div></form></main>`});
        }
    });
    const a=await context.newPage(),b=await context.newPage();
    for(const page of [a,b]) {
        await page.goto('https://character.test/montar/sheet/same-id');
        if (!process.argv[2]) await page.addScriptTag({path:path.resolve('web/javascript/fichas/2-kumbia.js')});
        await page.addScriptTag({path:path.resolve(process.argv[2] || 'web/javascript/fichas/4-autosave.js')});
    }
    await b.evaluate(()=>{ window.remoteStates=[]; new MutationObserver(()=>{const e=document.querySelector('[data-save-status]');window.remoteStates.push({state:e.dataset.saveState,value:document.querySelector('[name=vida]').value});}).observe(document.querySelector('[data-save-status]'),{attributes:true}); });
    const saved=page=>page.waitForFunction(()=>document.querySelector('[data-save-status]').getAttribute('aria-label')==='Cambios guardados');
    await a.fill('[name=vida]','9'); assert.equal(await a.locator('[data-save-status]').getAttribute('data-save-state'),'pending'); await a.click('[value=salvar]'); await saved(a);
    assert.equal(await a.locator('[data-save-status]').getAttribute('data-save-state'),'saved');
    // HTML normalizes textarea CRLF to LF; acknowledgements must do the same.
    assert.equal(await a.locator('[data-save-status]').getAttribute('aria-label'),'Cambios guardados');
    await new Promise(resolve=>setTimeout(resolve,3500));
    assert.equal(requests.length,1,'A saved textarea must not trigger repeated saves');
    assert.equal(await b.locator('[data-save-status]').getAttribute('aria-label'),'Datos actualizados','Remote multiline data left phantom pending changes');
    await b.waitForFunction(()=>document.querySelector('[name=vida]').value==='9');
    assert.ok(await b.evaluate(()=>window.remoteStates.some(e=>e.state==='pending' && e.value==='10')),'Remote update was applied before yellow indicator');
    assert.equal(await b.locator('[data-save-status]').getAttribute('data-save-state'),'saved');
    failSync=true;
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').dataset.saveState==='error');
    failSync=false;
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').dataset.saveState==='saved');
    await b.fill('[name=equipo]','arco');
    await a.fill('[name=vida]','8'); await a.click('[value=salvar]'); await saved(a);
    await saved(b);
    assert.equal(state.datos.vida,'8'); assert.equal(state.datos.equipo,'arco');
    assert.ok(requests.every(names=>names.length===1),'Full snapshots sent');
    await a.fill('[name=vida]','7'); await a.click('[value=salvar]'); await saved(a);
    await b.fill('[name=vida]','6'); await b.click('[value=salvar]'); await saved(b);
    await a.waitForFunction(()=>document.querySelector('[name=vida]').value==='6');
    assert.equal(state.datos.vida,'6');
    fail=true;
    await b.fill('[name=vida]','5'); await b.click('[value=salvar]');
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').getAttribute('aria-label').startsWith('Error al guardar'));
    assert.equal(await b.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}),true);
    assert.equal(state.datos.vida,'6');
    assert.equal(await b.locator('[data-save-status]').getAttribute('data-save-state'),'error');
    fail=false; await b.click('[value=salvar]'); await saved(b); assert.equal(state.datos.vida,'5');
    // A stalled request must release the saving flag, preserving changes for retry.
    await b.clock.install();
    await b.evaluate(()=>{
        window.originalCharacterFetch=window.fetch;
        window.fetch=(url,options)=>options?.method==='POST'
            ? new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new DOMException('Timed out','AbortError'))))
            : window.originalCharacterFetch(url,options);
    });
    await b.fill('[name=vida]','4'); await b.click('[value=salvar]');
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').getAttribute('aria-label')==='Guardando…');
    await b.clock.fastForward(31000);
    await b.waitForFunction(()=>document.querySelector('[data-save-status]').getAttribute('aria-label').startsWith('El guardado ha tardado demasiado'));
    await b.evaluate(()=>{window.fetch=window.originalCharacterFetch;});
    await b.click('[value=salvar]'); await saved(b); assert.equal(state.datos.vida,'4');
    await browser.close();
    console.log('PASS: two open sheets, bidirectional updates, field merge, last save, failed save, exit warning, retry');
})().catch(e=>{console.error(e);process.exit(1);});
