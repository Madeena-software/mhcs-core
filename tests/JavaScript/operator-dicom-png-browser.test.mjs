import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { chromium } from 'playwright';
import { build } from 'vite';

// Entire harness is synthetic and serves only loopback; no application login or external service.
function dicom({ mono = 'MONOCHROME2', pixels = [-1, 0, 1, 0, -1, 1], slope = 1, intercept = 0, color = false, encapsulated = false, spacing = '1\\2' } = {}) {
    const tags = [];
    const tag = (group, element, vr, value) => {
        let bytes = Buffer.isBuffer(value) ? value : Buffer.from(String(value));
        if (bytes.length % 2) bytes = Buffer.concat([bytes, Buffer.from([vr === 'UI' ? 0 : 32])]);
        const long = ['OW', 'OB'].includes(vr);
        const header = Buffer.alloc(long ? 12 : 8);
        header.writeUInt16LE(group); header.writeUInt16LE(element, 2); header.write(vr, 4);
        if (long) header.writeUInt32LE(bytes.length, 8); else header.writeUInt16LE(bytes.length, 6);
        tags.push(header, bytes);
    };
    const us = (element, value) => { const b = Buffer.alloc(2); b.writeUInt16LE(value); tag(0x28, element, 'US', b); };
    tag(2, 0x10, 'UI', encapsulated ? '1.2.840.10008.1.2.5' : '1.2.840.10008.1.2.1');
    tag(8, 0x16, 'UI', '1.2.840.10008.5.1.4.1.1.1');
    tag(8, 0x18, 'UI', '1.2.3.4');
    us(2, color ? 3 : 1); tag(0x28, 4, 'CS', color ? 'RGB' : mono);
    if (color) us(6, 0);
    us(0x10, 2); us(0x11, 3); tag(0x28, 0x30, 'DS', spacing);
    us(0x100, color ? 8 : 16); us(0x101, color ? 8 : 16); us(0x102, color ? 7 : 15); us(0x103, color ? 0 : 1);
    tag(0x28, 0x1050, 'DS', 0); tag(0x28, 0x1051, 'DS', 2);
    tag(0x28, 0x1052, 'DS', intercept); tag(0x28, 0x1053, 'DS', slope);
    const data = color ? Buffer.from(pixels) : Buffer.alloc(pixels.length * 2);
    if (!color) pixels.forEach((v, i) => data.writeInt16LE(v, i * 2));
    if (encapsulated) {
        const rle = Buffer.alloc(78); rle.writeUInt32LE(2); rle.writeUInt32LE(64, 4); rle.writeUInt32LE(71, 8);
        rle.set([5,255,0,0,0,255,0, 5,255,0,1,0,255,1], 64);
        const header = Buffer.from([0xe0,0x7f,0x10,0,'O'.charCodeAt(0),'B'.charCodeAt(0),0,0,255,255,255,255]);
        const item = bytes => { const h = Buffer.alloc(8); h.writeUInt16LE(0xfffe); h.writeUInt16LE(0xe000,2); h.writeUInt32LE(bytes.length,4); return Buffer.concat([h,bytes]); };
        tags.push(header, item(Buffer.alloc(0)), item(rle.subarray(0,50)), item(rle.subarray(50)), Buffer.from([254,255,221,224,0,0,0,0]));
    } else tag(0x7fe0, 0x10, color ? 'OB' : 'OW', data);
    return Buffer.concat([Buffer.alloc(128), Buffer.from('DICM'), ...tags]);
}

test('downloaded PNG has canonical native asymmetric pixels and failures leave no resources', async t => {
    const output = fs.mkdtempSync(path.join(os.tmpdir(), 'mhcs-png-'));
    t.after(() => fs.rmSync(output, { recursive: true, force: true }));
    const entry = path.join(process.cwd(), 'resources/js/operator-dicom-png.js');
    await build({
        configFile: false,
        logLevel: 'silent',
        plugins: [{
            name: 'synthetic-png-harness',
            resolveId: id => id === 'png-harness' ? '\0png-harness' : undefined,
            load: id => id === '\0png-harness' ? `
                import { downloadStudyAsPng, extractPixelData, initPngDownloadButtons } from ${JSON.stringify(entry)};
                import { metaData, getWebWorkerManager } from '@cornerstonejs/core';
                import dicomParser from 'dicom-parser';
                window.PNG = { downloadStudyAsPng, extractPixelData, initPngDownloadButtons };
                window.testMeta = metaData;
                window.workerManager = getWebWorkerManager;
                window.dicomParser = dicomParser;
            ` : undefined,
        }],
        resolve: { alias: { events: path.resolve('resources/js/browser-events.js') } },
        build: {
            outDir: output,
            emptyOutDir: true,
            minify: false,
            rollupOptions: { input: 'png-harness', output: { entryFileNames: 'png.js' } },
        },
    });
    const fixtures = {
        malformed: Buffer.from('invalid synthetic DICOM'), mono2: dicom(), rle: dicom({ encapsulated: true }), mono1: dicom({ mono: 'MONOCHROME1' }),
        rescale: dicom({ pixels: [0, 1, 2, 1, 0, 2], slope: 2, intercept: -1 }),
        rgb: dicom({ color: true, pixels: [255,0,0, 0,255,0, 0,0,255, 255,255,0, 0,255,255, 255,0,255] }),
    };
    const server = http.createServer((req, res) => {
        const name = new URL(req.url, 'http://127.0.0.1').pathname.slice(1);
        if (!name) { res.end('<script type="module" src="/png.js"></script>'); return; }
        if (name === 'denied') { res.writeHead(403); res.end('private detail'); return; }
        if (name === 'unavailable') { res.writeHead(404); res.end(); return; }
        if (name === 'slow') return;
        if (name === 'slowbody') { res.writeHead(200); res.write(Buffer.alloc(1)); return; }
        if (fixtures[name]) { res.end(fixtures[name]); return; }
        const file = path.resolve(output, name);
        if (!file.startsWith(output + path.sep) || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', name.endsWith('.js') ? 'application/javascript' : 'application/wasm');
        res.end(fs.readFileSync(file));
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const browser = await chromium.launch({ headless: true });
    try {
        for (const dpr of [1, 2]) {
            const page = await browser.newPage({ deviceScaleFactor: dpr });
            await page.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.abort());
            await page.goto(`http://127.0.0.1:${server.address().port}`);
            await page.waitForFunction(() => window.PNG);
            await page.evaluate(() => {
                window.providerCount = 0;
                const add = window.testMeta.addProvider, remove = window.testMeta.removeProvider;
                window.testMeta.addProvider = (...args) => { window.providerCount++; return add(...args); };
                window.testMeta.removeProvider = (...args) => { window.providerCount--; return remove(...args); };
            });
            for (const [name, expected] of Object.entries({ mono2: [0,255,255,255,0,255], rle: [0,255,255,255,0,255], mono1: [255,0,0,0,255,0], rescale: [0,255,255,255,0,255], rgb: fixtures.rgb && [255,0,0, 0,255,0, 0,0,255, 255,255,0, 0,255,255, 255,0,255] })) {
                const downloadPromise = page.waitForEvent('download'); downloadPromise.catch(() => {});
                try { await page.evaluate(async name => { await window.PNG.downloadStudyAsPng('/' + name, 'DCM-SYNTH.png'); }, name); } catch (error) { throw new Error(name + ': ' + error.message); }
                const download = await downloadPromise;
                const bytes = fs.readFileSync(await download.path());
                assert.equal(download.suggestedFilename(), 'DCM-SYNTH.png');
                assert.deepEqual([...bytes.subarray(0, 8)], [137,80,78,71,13,10,26,10]);
                const decoded = await page.evaluate(async base64 => {
                    const bitmap = await createImageBitmap(await (await fetch('data:image/png;base64,' + base64)).blob());
                    const c = document.createElement('canvas'); c.width = bitmap.width; c.height = bitmap.height;
                    c.getContext('2d').drawImage(bitmap, 0, 0);
                    return { width: c.width, height: c.height, rgba: [...c.getContext('2d').getImageData(0,0,c.width,c.height).data] };
                }, bytes.toString('base64'));
                assert.equal(decoded.width, 3); assert.equal(decoded.height, 2);
                const expectedRGBA = name === 'rgb' ? expected.flatMap((_, i) => i % 3 ? [] : [...expected.slice(i,i+3),255]) : expected.flatMap(v => [v,v,v,255]);
                assert.deepEqual(decoded.rgba, expectedRGBA, `${name} all six pixels including asymmetric edges, DPR ${dpr}`);
            }
            assert.equal(await page.evaluate(() => window.providerCount), 0, 'metadata providers removed after repeated exports');
            assert.deepEqual(await page.evaluate(base64 => {
                const b = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
                const padded = new Uint8Array(b.length + 24); padded.set(b, 24);
                return [...window.PNG.extractPixelData(window.dicomParser.parseDicom(padded.subarray(24)))];
            }, fixtures.mono2.toString('base64')), [255,255,0,0,1,0,0,0,255,255,1,0], 'nonzero byteArray offset uses the correct pixels');
            let failedDownloads = 0;
            const onFailureDownload = () => { failedDownloads++; };
            page.on('download', onFailureDownload);
            const failures = await page.evaluate(async () => {
                const created = [], revoked = [], canvases = [], logs = [];
                const nativeCreate = document.createElement.bind(document), nativeFetch = window.fetch;
                const createURL = URL.createObjectURL.bind(URL), revokeURL = URL.revokeObjectURL.bind(URL);
                const errorLog = console.error; console.error = (...args) => logs.push(args);
                URL.createObjectURL = blob => { const u = createURL(blob); created.push(u); return u; };
                URL.revokeObjectURL = u => { revoked.push(u); revokeURL(u); };
                document.createElement = (...args) => { const e = nativeCreate(...args); if (args[0] === 'canvas') canvases.push(e); return e; };
                const results = [];
                const run = async (name, setup, stageCallback = () => {}) => {
                    const signals = []; window.fetch = (...args) => { signals.push(args[1]?.signal); return nativeFetch(...args); };
                    setup?.();
                    try { await window.PNG.downloadStudyAsPng('/' + name, 'DCM-SYNTH.png', stageCallback, { timeoutMs: 40, loadTimeoutMs: 40 }); results.push('unexpected success'); }
                    catch (error) { results.push({ message: error.message, aborted: signals.every(s => s?.aborted), providers: window.providerCount }); }
                    document.createElement = (...args) => { const e = nativeCreate(...args); if (args[0] === 'canvas') canvases.push(e); return e; };
                };
                await run('malformed');
                await run('mono2', undefined, stage => { if (stage === 'decoding') window.workerManager().workerRegistry.dicomImageLoader.instances[0].decodeTask = () => new Promise(() => {}); });
                await run('denied'); await run('unavailable'); await run('slow'); await run('slowbody');
                await run('mono2', () => { document.createElement = (...args) => { const e = nativeCreate(...args); if (args[0] === 'canvas') { canvases.push(e); e.toBlob = () => {}; } return e; }; });
                await run('mono2', () => { document.createElement = (...args) => { const e = nativeCreate(...args); if (args[0] === 'canvas') { canvases.push(e); e.toBlob = cb => cb(null); } return e; }; });
                await run('mono2', () => { document.createElement = (...args) => { const e = nativeCreate(...args); if (args[0] === 'a') e.click = () => { throw new Error('private URL detail'); }; if (args[0] === 'canvas') canvases.push(e); return e; }; });
                window.fetch = nativeFetch; document.createElement = nativeCreate; URL.createObjectURL = createURL; URL.revokeObjectURL = revokeURL; console.error = errorLog;
                return { results, unreleased: created.filter(u => !revoked.includes(u)), canvases: canvases.map(c => [c.width,c.height]), links: document.querySelectorAll('a[download]').length, logs };
            });
            page.off('download', onFailureDownload);
            assert.equal(failedDownloads, 0, 'denials and failures produce no PNG download');
            assert.equal(failures.results.length, 9);
            for (const result of failures.results) assert.deepEqual(result, { message: 'PNG export failed.', aborted: true, providers: 0 });
            assert.deepEqual(failures.unreleased, []); assert.equal(failures.links, 0); assert.deepEqual(failures.logs, []);
            // Decoder-owned scratch canvases are not the exporter's responsibility; native export canvases are zeroed.
            assert.ok(failures.canvases.some(([w,h]) => w === 0 && h === 0));
            const buttonResult = await page.evaluate(async () => {
                const b = document.createElement('button');
                b.dataset.pngDownload = ''; b.dataset.dicomUrl = '/denied'; b.dataset.reference = 'DCM-SYNTH';
                b.dataset.pngMessages = JSON.stringify({ processing: 'TRANSLATED_PROCESSING', saving: 'TRANSLATED_SAVING', done: 'TRANSLATED_DONE', error: 'TRANSLATED_ERROR' });
                b.textContent = 'ORIGINAL'; document.body.append(b);
                const alerts = []; window.alert = value => alerts.push(value);
                window.PNG.initPngDownloadButtons(); window.PNG.initPngDownloadButtons();
                b.click(); b.click();
                const pending = [b.disabled, b.getAttribute('aria-busy'), b.textContent];
                await new Promise(resolve => setTimeout(resolve, 100));
                const finished = [b.disabled, b.getAttribute('aria-busy'), b.dataset.busy];
                b.click(); await new Promise(resolve => setTimeout(resolve, 100)); b.remove();
                return { pending, finished, alerts };
            });
            assert.deepEqual(buttonResult, { pending: [true, 'true', 'TRANSLATED_PROCESSING'], finished: [false, null, 'false'], alerts: ['TRANSLATED_ERROR', 'TRANSLATED_ERROR'] });
            await page.close();
        }
    } finally {
        await browser.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve));
        fs.rmSync(output, { recursive: true, force: true });
    }
});
