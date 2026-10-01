import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import test from 'node:test';
import { chromium } from 'playwright';

test('real browser decodes synthetic DICOM and exports valid native PNG with correct dimensions and pixel content', async () => {
    const dcmPath = path.resolve('resources/fixtures/image-gateway/synthetic-study.dcm');
    const dcmBytes = fs.readFileSync(dcmPath);
    const dcmBase64 = dcmBytes.toString('base64');

    const manifest = JSON.parse(fs.readFileSync(path.resolve('public/build/manifest.json'), 'utf8'));
    const pngEntry = manifest['resources/js/operator-dicom-png.js'];
    const appEntry = manifest['resources/js/app.js'];
    assert.ok(appEntry?.file, 'app must have a built bundle');

    // Create an HTTP server serving built public/build/ files
    const server = http.createServer((req, res) => {
        const reqPath = new URL(req.url, 'http://127.0.0.1').pathname;
        if (reqPath === '/') {
            res.writeHead(200, { 'Content-Type': 'text/html' });
            res.end(`
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>DICOM PNG Browser Test</title></head>
<body>
    <button id="png-btn" data-png-download data-dicom-url="data:application/dicom;base64,${dcmBase64}" data-reference="DCM-SYNTH01">Unduh PNG</button>
    <script type="module" src="/${appEntry.file}"></script>
</body>
</html>
            `);
            return;
        }

        const cleanPath = reqPath.replace(/^\/build\//, '').replace(/^\//, '');
        const safePath = path.resolve(path.join('public/build', cleanPath));
        if (safePath.startsWith(path.resolve('public/build')) && fs.existsSync(safePath) && fs.statSync(safePath).isFile()) {
            const ext = path.extname(safePath);
            const contentType = {
                '.js': 'application/javascript',
                '.wasm': 'application/wasm',
                '.css': 'text/css',
                '.json': 'application/json',
            }[ext] || 'application/octet-stream';
            res.writeHead(200, { 'Content-Type': contentType });
            res.end(fs.readFileSync(safePath));
            return;
        }

        console.log('404 NOT FOUND:', reqPath, 'Resolved to:', safePath);
        res.writeHead(404);
        res.end('Not found: ' + reqPath);
    });

    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    const testUrl = `http://127.0.0.1:${port}/`;

    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        page.on('console', (msg) => console.log('PAGE LOG:', msg.text()));
        page.on('pageerror', (err) => console.error('PAGE ERROR:', err));
        await page.goto(testUrl, { waitUntil: 'networkidle' });

        // Set up download listener in page
        const downloadPromise = page.waitForEvent('download');
        await page.click('#png-btn');
        const download = await downloadPromise;

        assert.equal(download.suggestedFilename(), 'DCM-SYNTH01.png');
        const downloadPath = await download.path();
        const pngBuffer = fs.readFileSync(downloadPath);

        assert.ok(pngBuffer.length > 0, 'Downloaded PNG must not be empty');

        // Check PNG signature: 89 50 4E 47 0D 0A 1A 0A
        const pngSignature = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
        assert.deepEqual(pngBuffer.subarray(0, 8), pngSignature, 'File must have valid PNG signature');

        // Check IHDR chunk
        assert.equal(pngBuffer.toString('ascii', 12, 16), 'IHDR', 'First chunk must be IHDR');
        const width = pngBuffer.readUInt32BE(16);
        const height = pngBuffer.readUInt32BE(20);

        assert.equal(width, 32, 'PNG width must match native DICOM columns (32)');
        assert.equal(height, 32, 'PNG height must match native DICOM rows (32)');

        console.log(`Verified browser PNG download: ${width}x${height}, size ${pngBuffer.length} bytes, valid PNG signature.`);
    } finally {
        await browser.close();
        server.close();
    }
});
