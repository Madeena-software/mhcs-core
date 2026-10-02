import assert from 'node:assert/strict';
import test from 'node:test';
import * as app from '../../resources/js/app.js';

function fixture() {
    const listeners = {};
    const attributes = {};
    const button = {
        dataset: { pngMessages: JSON.stringify({ processing: 'Registry processing', error: 'Registry error' }) },
        textContent: 'Registry download', disabled: false,
        setAttribute(name, value) { attributes[name] = value; },
        removeAttribute(name) { delete attributes[name]; },
        addEventListener(name, fn) { listeners[name] = fn; },
    };
    return { button, listeners, attributes, querySelectorAll: () => [button] };
}

test('PNG bootstrap restores buttons and initializes once after a bounded import', async () => {
    const root = fixture();
    let initialize = 0;
    let release;
    const importing = new Promise(resolve => { release = resolve; });
    const pending = app.bootstrapPngDownloads(root, () => importing, 30);
    assert.equal(root.button.disabled, true);
    release({ initPngDownloadButtons(container) { assert.equal(container, root); initialize++; } });
    await pending;
    assert.equal(initialize, 1);
    assert.equal(root.button.disabled, false);
    assert.equal(root.button.textContent, 'Registry download');
    assert.equal(root.attributes['aria-busy'], undefined);
});

for (const kind of ['failure', 'timeout']) {
    test(`PNG import ${kind} exposes a localized useful button without raw diagnostics`, async () => {
        const root = fixture();
        const alerts = [];
        const oldAlert = globalThis.alert;
        globalThis.alert = message => alerts.push(message);
        try {
            await app.bootstrapPngDownloads(root, () => kind === 'failure'
                ? Promise.reject(new Error('private-url-and-diagnostics')) : new Promise(() => {}), 5);
            assert.equal(root.button.disabled, false);
            assert.equal(root.button.textContent, 'Registry download');
            assert.equal(root.attributes['aria-busy'], undefined);
            assert.equal(root.button.dataset.pngState, 'error');
            root.listeners.click({ preventDefault() {} });
            assert.deepEqual(alerts, ['Registry error']);
        } finally { globalThis.alert = oldAlert; }
    });
}

test('automatic list refresh waits for pending PNG export and resumes afterward', async () => {
    const { readFileSync } = await import('node:fs');
    const { runInNewContext } = await import('node:vm');
    const layout = readFileSync('resources/views/operator/layout.blade.php', 'utf8');
    const script = layout.match(/<script>\s*([\s\S]*?)<\/script>/)[1];
    let busy = true;
    let refresh;
    let reloads = 0;
    runInNewContext(script, {
        document: { querySelector: selector => selector === '[data-worklist-auto-refresh]' || busy },
        window: {
            setInterval(fn, ms) { assert.equal(ms, 5000); refresh = fn; },
            addEventListener() {}, clearInterval() {}, location: { reload() { reloads++; } },
        },
    });
    refresh();
    assert.equal(reloads, 0);
    busy = false;
    refresh();
    assert.equal(reloads, 1);
});
