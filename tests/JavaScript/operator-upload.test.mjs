import test from 'node:test';
import assert from 'node:assert/strict';
import {
    formatBytes,
    formatDuration,
    formatSpeed,
    uploadTelemetry,
} from '../../resources/js/operator-upload.js';

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
