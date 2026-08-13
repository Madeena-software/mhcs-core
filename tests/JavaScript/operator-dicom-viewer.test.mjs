import assert from 'node:assert/strict';
import test from 'node:test';

const {
    bindMonitorWindow,
    openMonitorWindow,
    resizeRenderingEngine,
    setViewerState,
} = await import('../../resources/js/operator-dicom-viewer.js');

function viewerRoot() {
    const status = { textContent: '' };
    const popupStatus = { hidden: true, textContent: '' };
    const error = { hidden: true, textContent: '' };
    const button = {
        dataset: { monitorUrl: '/operator/studies/synthetic-study' },
        getAttribute(name) {
            return name === 'data-monitor-url' ? this.dataset.monitorUrl : null;
        },
        addEventListener(_, callback) { this.callback = callback; },
    };

    return {
        dataset: {
            unavailableMessage: 'Studi DICOM tidak tersedia.',
            displayErrorMessage: 'Studi DICOM tidak dapat ditampilkan.',
            popupBlockedMessage: 'Browser memblokir jendela pop-up. Lanjutkan pada tab ini atau izinkan pop-up.',
        },
        status,
        popupStatus,
        error,
        button,
        querySelector(selector) {
            return {
                '#dicom-viewer-status': status,
                '#dicom-popup-status': popupStatus,
                '#dicom-viewer-error': error,
                '[data-open-monitor]': button,
            }[selector] ?? null;
        },
    };
}

test('requests and focuses the existing protected study in one stable monitor window', () => {
    const root = viewerRoot();
    const calls = [];
    let focused = false;
    globalThis.window = {
        open(...args) {
            calls.push(args);
            return { closed: false, focus() { focused = true; } };
        },
    };

    openMonitorWindow(root);

    assert.deepEqual(calls, [[
        '/operator/studies/synthetic-study',
        'mhcs-dicom-monitor',
        'width=640,height=960,resizable=yes,scrollbars=yes',
    ]]);
    assert.equal(focused, true);
    assert.equal(root.popupStatus.hidden, true);
});

test('keeps the current-tab fallback when the monitor popup is blocked or focus throws', () => {
    const root = viewerRoot();
    globalThis.window = { open: () => null };
    openMonitorWindow(root);
    assert.equal(root.popupStatus.hidden, false);
    assert.equal(root.popupStatus.textContent, root.dataset.popupBlockedMessage);

    root.popupStatus.hidden = true;
    globalThis.window = { open: () => ({ closed: false, focus() { throw new Error('blocked'); } }) };
    openMonitorWindow(root);
    assert.equal(root.popupStatus.hidden, false);
    assert.equal(root.popupStatus.textContent, root.dataset.popupBlockedMessage);
});

test('renders a safe Indonesian error state instead of a loader diagnostic', () => {
    const root = viewerRoot();
    setViewerState(root, 'loading', 'Memuat DICOM…');
    setViewerState(root, 'error', 'secret HTTP body and storage key');

    assert.equal(root.dataset.viewerState, 'error');
    assert.equal(root.status.textContent, 'Studi DICOM tidak tersedia.');
    assert.equal(root.error.hidden, false);
    assert.equal(root.error.textContent, 'Studi DICOM tidak dapat ditampilkan.');
    assert.equal(root.error.textContent.includes('secret'), false);
});

test('enters compact monitor mode and tolerates a stale engine during resize', () => {
    const root = viewerRoot();
    const classes = new Set();
    globalThis.window = { name: 'mhcs-dicom-monitor' };
    globalThis.document = { body: { classList: { add: (name) => classes.add(name) } } };
    bindMonitorWindow(root);
    assert.equal(classes.has('is-monitor-popup'), true);

    let resized = false;
    resizeRenderingEngine({ resize() { resized = true; } });
    resizeRenderingEngine({ resize() { throw new Error('unmounted'); } });
    assert.equal(resized, true);
});
