const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
(async()=>{
    const browser=await chromium.launch({headless:true,channel:'chrome'});
    const page=await browser.newPage();
    let actions=[],dialogs=0;
    const view=fs.readFileSync('private/views/ev/personajes/montar.phtml','utf8');
    const buttons=view.match(/<button\b[^>]*>[\s\S]*?<\/button>/g).join('');
    await page.route('https://character.test/**',async route=>{
        if(route.request().method()==='POST'){
            const fd=await new Response(route.request().postDataBuffer(),{headers:{'Content-Type':route.request().headers()['content-type']}}).formData();
            actions.push(fd.get('action'));
            const data={success:true,idu:'same-id',revision:2,datos:{nombre:'Personaje'},editable:true,url:'/montar/sheet/same-id'};
            await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
        }else{
            const initial=JSON.stringify({idu:'same-id',revision:1,datos:{nombre:'Personaje'},editable:true,sincronizar:false}).replaceAll('"','&quot;');
            await route.fulfill({contentType:'text/html',body:`<main><form method="post" data-autosave="salvar"><input type="text" name="nombre" value="Personaje"><input type="hidden" name="idu" value="same-id"><input type="hidden" name="fichas_idu" value="sheet"><input type="hidden" name="estado_inicial" value="${initial}"><div class="fixed-bottom-right"><span data-save-status></span>${buttons}</div></form></main>`});
        }
    });
    page.on('dialog',async dialog=>{dialogs++; await dialog.dismiss();});
    await page.goto('https://character.test/montar/sheet/same-id');
    await page.addScriptTag({path:path.resolve(process.argv[2] || 'web/javascript/fichas.min.js')});
    await page.locator('[name=nombre]').fill('Cambio');
    await page.locator('[name=nombre]').press('Enter');
    await page.waitForFunction(()=>document.querySelector('[data-save-status]').getAttribute('aria-label')==='Cambios guardados');
    assert.deepEqual(actions,['salvar'],'Enter must save, never delete or duplicate');
    await page.locator('[data-eliminar-personaje]').click();
    assert.equal(dialogs,1,'Deletion must use Kumbia confirmation');
    assert.deepEqual(actions,['salvar'],'Cancelled deletion submitted a request');
    await browser.close(); console.log('PASS: actual template button order, Enter saves, deletion requires confirmation');
})().catch(e=>{console.error(e);process.exit(1);});
