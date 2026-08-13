import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const buildRoot = path.resolve('public/build');
const manifest = JSON.parse(fs.readFileSync(path.join(buildRoot, 'manifest.json'), 'utf8'));
const app = manifest['resources/js/app.js'];
const viewer = manifest['resources/js/operator-dicom-viewer.js'];

assert.ok(app?.dynamicImports?.includes('resources/js/operator-dicom-viewer.js'), 'app must defer the DICOM viewer import');
assert.ok(viewer?.file, 'the DICOM viewer must have a production chunk');

const appCode = fs.readFileSync(path.join(buildRoot, app.file), 'utf8');
const viewerCode = fs.readFileSync(path.join(buildRoot, viewer.file), 'utf8');

assert.match(appCode, /operator-dicom-viewer-[^"']+\.js/, 'app must reference the deferred viewer chunk');
assert.match(viewerCode, /EventEmitter/, 'the browser events compatibility module must be bundled');
assert.match(viewerCode, /removeListener/, 'the browser events compatibility module must expose the loader API');
assert.doesNotMatch(viewerCode, /browser-external[\s\S]*\bevents\b/i, 'events must not be externalized for the browser');

console.log('DICOM browser bundle check passed.');
