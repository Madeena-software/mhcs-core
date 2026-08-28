import test from 'node:test';
import assert from 'node:assert/strict';
import {
    formatBytes,
    formatDuration,
    formatSpeed,
    normalizeRadiographNpZ,
    prepareCaptureFormData,
    uploadTelemetry,
} from '../../resources/js/operator-upload.js';
import { unzipSync, zipSync } from 'fflate';

const archive = (entries) => zipSync(Object.fromEntries(
    entries.map(([name, data]) => [name, new TextEncoder().encode(data)]),
));

const names = (bytes) => Object.keys(unzipSync(bytes));

const duplicateFirstCentralEntry = (bytes) => {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let eocd = bytes.length - 22;
    while (eocd >= 0 && view.getUint32(eocd, true) !== 0x06054b50) eocd -= 1;
    const centralOffset = view.getUint32(eocd + 16, true);
    const centralSize = view.getUint32(eocd + 12, true);
    const entry = bytes.slice(centralOffset, centralOffset + 46 + view.getUint16(centralOffset + 28, true)
        + view.getUint16(centralOffset + 30, true) + view.getUint16(centralOffset + 32, true));
    const result = new Uint8Array(bytes.length + entry.length);
    result.set(bytes.slice(0, centralOffset + centralSize), 0);
    result.set(entry, centralOffset + centralSize);
    result.set(bytes.slice(centralOffset + centralSize), centralOffset + centralSize + entry.length);
    const resultView = new DataView(result.buffer);
    resultView.setUint16(result.length - 22 + 10, view.getUint16(eocd + 10, true) + 1, true);
    resultView.setUint16(result.length - 22 + 8, view.getUint16(eocd + 8, true) + 1, true);
    resultView.setUint32(result.length - 22 + 12, centralSize + entry.length, true);
    return result;
};

test('removes only the exact processedimage.npy member from a radiograph archive', () => {
    const source = archive([
        ['rawimage.npy', 'raw'],
        ['processedimage.npy', 'processed'],
        ['processedimage', 'keep'],
        ['gainid.npy', 'gain'],
    ]);
    const normalized = normalizeRadiographNpZ(source);

    assert.ok(normalized.length < source.length);
    assert.deepEqual(names(normalized), ['rawimage.npy', 'processedimage', 'gainid.npy']);
    assert.deepEqual(unzipSync(normalized)['rawimage.npy'], new TextEncoder().encode('raw'));
    assert.deepEqual(unzipSync(normalized)['processedimage'], new TextEncoder().encode('keep'));
});

test('passes an already-normalized archive through without rewriting it', () => {
    const source = archive([['rawimage.npy', 'raw']]);

    assert.strictEqual(normalizeRadiographNpZ(source), source);
});

test('fails closed for malformed and duplicate-target archives', () => {
    assert.throws(() => normalizeRadiographNpZ(new Uint8Array([1, 2, 3])), /Invalid NPZ archive/);

    const source = archive([['processedimage.npy', 'processed']]);
    assert.throws(() => normalizeRadiographNpZ(duplicateFirstCentralEntry(source)), /Invalid NPZ archive/);
});

test('puts normalized radiograph and unchanged gain into the submitted FormData', async () => {
    const NativeFormData = globalThis.FormData;
    const radiograph = new File([archive([
        ['rawimage.npy', 'raw'],
        ['processedimage.npy', 'processed'],
    ])], 'capture.npz', { type: 'application/octet-stream' });
    const gain = new File(['gain'], 'gain.npz', { type: 'application/octet-stream' });
    globalThis.FormData = class extends NativeFormData {
        constructor(form) {
            super();
            for (const [name, value] of form.fields) this.append(name, value);
        }
    };

    try {
        const body = await prepareCaptureFormData({ fields: [
            ['radiograph_npz', radiograph],
            ['gain_npz', gain],
        ] });
        const normalized = new Uint8Array(await body.get('radiograph_npz').arrayBuffer());

        assert.deepEqual(names(normalized), ['rawimage.npy']);
        assert.strictEqual(body.get('gain_npz'), gain);
        assert.equal(body.get('radiograph_npz').name, 'capture.npz');
    } finally {
        globalThis.FormData = NativeFormData;
    }
});

test('formats upload telemetry without rounding away useful operator detail', () => {
    assert.equal(formatBytes(6_082_560), '5.8 MB');
    assert.equal(formatSpeed(6_082_560), '5.8 MB/s');
    assert.equal(formatDuration(84), '1 menit 24 detik');
    assert.equal(formatDuration(0), '0 detik');
});

test('reports percentage, bytes, speed, and ETA from a monotonic sample', () => {
    assert.deepEqual(uploadTelemetry({ loaded: 0, total: 10_000, elapsedMs: 0, lengthComputable: true }), {
        percent: 0,
        loaded: '0 B',
        total: '9.8 KB',
        speed: null,
        eta: null,
    });
    assert.deepEqual(uploadTelemetry({ loaded: 5_000_000, total: 10_000_000, elapsedMs: 1_000, lengthComputable: true }), {
        percent: 50,
        loaded: '4.8 MB',
        total: '9.5 MB',
        speed: '4.8 MB/s',
        eta: '1 detik',
    });
});

test('keeps unknown and invalid progress finite and readable', () => {
    for (const sample of [
        { loaded: 100, total: 0, elapsedMs: 500, lengthComputable: true },
        { loaded: 100, total: 100, elapsedMs: 500, lengthComputable: false },
        { loaded: Number.NaN, total: 100, elapsedMs: 500, lengthComputable: true },
    ]) {
        const telemetry = uploadTelemetry(sample);
        assert.ok(Object.values(telemetry).every((value) => typeof value !== 'number' || Number.isFinite(value)));
        assert.equal(telemetry.speed, null);
        assert.equal(telemetry.eta, null);
    }
});
