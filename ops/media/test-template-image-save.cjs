const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('web/javascript/editor/9-editor.js','utf8');
const start=source.indexOf('    window.guardarPlantillaAJAX = ');const end=source.indexOf('\n    /**',start);
let sent;class FD {constructor(){this.values=new Map([['fondo_pergamino','stale-odd'],['fondo_pergamino_even','stale-even'],['footer_imagen','stale-footer'],['size_h1','24']]);}delete(k){this.values.delete(k);}set(k,v){this.values.set(k,v);}}
const context={window:{},document:{getElementById:()=>null},FormData:FD,fetch:(url,opts)=>{sent=opts.body.values;return Promise.resolve({ok:true,text:()=>Promise.resolve('{}')});},console};vm.createContext(context);vm.runInContext(source.slice(start,end),context);
const form={action:'/test'};const file={name:'footer.png'};const input={name:'footer_imagen',files:[file],value:'selected'};
(async()=>{
context.window.guardarPlantillaAJAX(form,null,null,input);assert.equal(sent.get('footer_imagen'),file);assert(!sent.has('fondo_pergamino'));assert(!sent.has('fondo_pergamino_even'));await new Promise(setImmediate);assert.equal(input.value,'');
context.window.guardarPlantillaAJAX(form,{size_h1:'28'});assert(!sent.has('footer_imagen'));assert(!sent.has('fondo_pergamino_even'));assert.equal(sent.get('size_h1'),'28');
context.window.guardarPlantillaAJAX(form,{footer_imagen:''});assert.equal(sent.get('footer_imagen'),'');assert(!sent.has('fondo_pergamino'));
console.log('PASS: isolated footer upload, cleared file, unrelated style save, explicit removal');
})();
