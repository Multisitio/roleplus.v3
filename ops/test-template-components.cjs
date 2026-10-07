const fs = require('fs'), vm = require('vm'), assert = require('assert');
const listeners = {};
const status = { textContent: '' };
const content = { dataset: { componentesUrl: '/components/manual' }, innerHTML: '' };
const panel = { open: true, matches: () => true, querySelector: s => s.includes('content') ? content : status };
const button = { disabled: false };
const form = { matches: () => true, action: '/components/manual', querySelector: () => button, closest: () => panel };
const stylesheet = { href: '/before.css' };
let response, requests = [];
const context = {
    document: { addEventListener: (type, fn) => listeners[type] = fn, getElementById: () => stylesheet },
    FormData: class { constructor(form) { this.form = form; } },
    fetch: async (url, options) => { requests.push({ url, options }); return response; }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('web/javascript/editor/9-template-components.js', 'utf8'), context);
(async () => {
    response = { ok: true, text: async () => '<html><body>Login</body></html>' };
    await listeners.toggle({ target: panel });
    assert.equal(content.innerHTML, '');
    assert(!content.dataset.loaded);
    response = { ok: true, text: async () => '<details>reglas</details>' };
    await listeners.toggle({ target: panel });
    assert.equal(content.innerHTML, '<details>reglas</details>');
    assert.equal(requests[1].options.headers['X-Requested-With'], 'XMLHttpRequest');
    await listeners.toggle({ target: panel });
    assert.equal(requests.length, 2);
    let prevented = false;
    response = { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ success: true, css_url: '/after.css' }) };
    await listeners.submit({ target: form, preventDefault: () => prevented = true });
    assert(prevented);
    assert.equal(stylesheet.href, '/after.css');
    assert.equal(content.innerHTML, '<details>reglas</details>');
    assert.equal(button.disabled, false);
    response = { ok: false, headers: { get: () => 'application/json' }, json: async () => ({ success: false, error: 'CSS inválido' }) };
    await listeners.submit({ target: form, preventDefault() {} });
    assert.equal(stylesheet.href, '/after.css');
    assert.equal(status.textContent, 'CSS inválido');
    assert.equal(button.disabled, false);
    console.log('PASS: fragment-only loading, retry, one-time loading, AJAX save, stylesheet refresh, rejected CSS');
})().catch(error => { console.error(error); process.exitCode = 1; });
