const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || 'web/javascript/editor/5-pinceles.js', 'utf8');
const code = source.slice(source.indexOf('    function scheduleSave('), source.indexOf("    document.addEventListener('paste'"));
function setup(fetcher) {
    const timers = new Map();
    let next = 0;
    const article = {
        html: '<article>' + 'x'.repeat(100000) + '</article>',
        getAttribute: () => 'page',
        cloneNode() {
            const html = this.html;
            return { querySelector: s => s === 'article' ? {outerHTML: html} : null, querySelectorAll: () => [] };
        }
    };
    const c = {
        Promise, FormData, AbortController, fetch: fetcher,
        window: {location: {pathname: '/editor'}},
        document: {querySelector: () => null},
        isDirty: {}, savePromises: {}, saveRevisions: {}, retryCounts: {}, debounceTimers: {}, countdownTimers: {},
        setTimeout: (fn, ms) => { const id = ++next; timers.set(id, {fn, ms}); return id; },
        clearTimeout: id => timers.delete(id), setInterval: () => ++next, clearInterval: () => {},
        setSaveStatus: (status, msg) => { c.status = status; c.message = msg; }
    };
    vm.runInNewContext(code, c);
    return {c, article, timers};
}
const ok = () => ({ok: true, status: 200, json: async () => ({success: true})});
(async () => {
    let requests = [];
    let t = setup(async (url, options) => { requests.push(options); return ok(); });
    t.c.scheduleSave(t.article);
    const debounce = t.timers.get(t.c.debounceTimers.page);
    assert.equal(debounce.ms, 8000);
    debounce.fn();
    await t.c.savePromises.page;
    assert.equal(requests.length, 1);
    assert.ok(requests[0].body.get('descripcion').length > 65536);
    assert.notEqual(requests[0].keepalive, true);
    assert.equal(t.c.isDirty.page, undefined);

    let resolveFirst;
    requests = [];
    t = setup((url, options) => {
        requests.push(options.body.get('descripcion'));
        return requests.length === 1 ? new Promise(resolve => {resolveFirst = resolve;}) : Promise.resolve(ok());
    });
    t.c.scheduleSave(t.article);
    const saving = t.c.saveArticle(t.article);
    t.article.html = '<article>last edit during request</article>';
    t.c.scheduleSave(t.article);
    resolveFirst(ok());
    await saving;
    assert.equal(requests.length, 2);
    assert.equal(requests[1], t.article.html);
    assert.equal(t.c.isDirty.page, undefined);

    for (const response of [
        {ok: true, status: 200, json: async () => ({success: false})},
        {ok: true, status: 200, json: async () => {throw new SyntaxError('HTML');}},
        {ok: false, status: 401}, {ok: false, status: 403},
        {ok: true, status: 200, redirected: true}
    ]) {
        t = setup(async () => response);
        assert.equal(await t.c.saveArticle(t.article), false);
        assert.equal(t.c.isDirty.page, true);
        assert.equal(t.c.status, 'error');
        assert.equal(t.c.debounceTimers.page, undefined);
    }
    t = setup(async () => {throw new TypeError('Network failed');});
    await t.c.saveArticle(t.article);
    assert.equal(t.c.isDirty.page, true);
    for (const delay of [5000, 10000, 20000]) {
        const timer = t.timers.get(t.c.debounceTimers.page);
        assert.equal(timer.ms, delay);
        timer.fn();
        await t.c.savePromises.page;
    }
    assert.equal(t.c.retryCounts.page, 3);
    assert.equal(t.c.debounceTimers.page, undefined);
    assert.equal(t.c.isDirty.page, true);
    t.c.fetch = async () => ok();
    assert.equal(await t.c.saveArticle(t.article), true);
    assert.equal(t.c.isDirty.page, undefined);
    console.log('PASS: debounce, large page, concurrent edits, JSON validation, expired session, bounded retries and manual recovery');
})().catch(error => {console.error(error); process.exitCode = 1;});
