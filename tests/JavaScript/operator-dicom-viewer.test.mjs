import assert from 'node:assert/strict';
import test from 'node:test';

const viewerModule = await import('../../resources/js/operator-dicom-viewer.js');
const {
    flipViewport,
    isPrimaryPointerDrag,
    panViewport,
    resizeRenderingEngine,
    resetViewport,
    rotateViewport,
    setViewerState,
    toggleFullscreen,
} = viewerModule;
const { bootstrapViewer } = await import('../../resources/js/app.js');
const {
    DICOM_LOAD_TIMEOUT_MS,
    VIEWER_TIMEOUT_MS,
    dicomLoadTimeout,
    withViewerTimeout,
} = await import('../../resources/js/operator-viewer-timeout.js');

function viewerRoot() {
    const status = { textContent: '' };
    const secondaryStatus = { textContent: '' };
    const error = { hidden: true, textContent: '' };

    return {
        dataset: {
            unavailableMessage: 'Studi DICOM tidak tersedia.',
            displayErrorMessage: 'Studi DICOM tidak dapat ditampilkan.',
            viewerTimeoutMs: '',
        },
        status,
        secondaryStatus,
        error,
        querySelector(selector) {
            return {
                '#dicom-viewer-status': status,
                '#dicom-viewer-error': error,
            }[selector] ?? null;
        },
        querySelectorAll(selector) {
            return selector === '[data-viewer-status]' ? [status, secondaryStatus] : [];
        },
    };
}

test('exposes only the supported viewer interactions', () => {
    assert.deepEqual(viewerModule.VIEWER_INTERACTIONS, ['zoom', 'pan', 'reset', 'rotate', 'flip', 'fullscreen']);
});

test('resets view-only transforms without changing the stored study', () => {
    const calls = [];
    const viewport = {
        resetCamera() { calls.push('resetCamera'); },
        setRotation(rotation) { calls.push(['rotation', rotation]); },
        setCamera(camera) { calls.push(['camera', camera]); },
        render() { calls.push('render'); },
    };

    resetViewport(viewport);

    assert.deepEqual(calls, [
        'resetCamera',
        ['rotation', 0],
        ['camera', { flipHorizontal: true, flipVertical: false }],
        'render',
    ]);
});

test('rotates and flips only the active viewport camera', () => {
    let rotation = 350;
    let camera = { flipHorizontal: false, flipVertical: true };
    let rendered = 0;
    const viewport = {
        getRotation() { return rotation; },
        setRotation(next) { rotation = next; },
        getCamera() { return camera; },
        setCamera(next) { camera = next; },
        render() { rendered += 1; },
    };

    rotateViewport(viewport, 90);
    flipViewport(viewport, 'horizontal');
    flipViewport(viewport, 'vertical');

    assert.equal(rotation, 80);
    assert.deepEqual(camera, { flipHorizontal: true, flipVertical: false });
    assert.equal(rendered, 3);
});

test('does not treat a plain click as a pan gesture', () => {
    assert.equal(isPrimaryPointerDrag({ buttons: 0 }, { x: 10, y: 10 }), false);
    assert.equal(isPrimaryPointerDrag({ buttons: 1, clientX: 11, clientY: 10 }, { x: 10, y: 10 }, { x: 10, y: 10 }), false);
    assert.equal(isPrimaryPointerDrag({ buttons: 1, clientX: 20, clientY: 10 }, { x: 10, y: 10 }, { x: 10, y: 10 }), true);
});

test('applies pan deltas using Cornerstone point arrays', () => {
    const calls = [];
    const viewport = {
        getPan() { return [100, 200]; },
        setPan(pan) { calls.push(pan); },
        render() { calls.push('render'); },
    };

    panViewport(viewport, 12, -8);

    assert.deepEqual(calls, [[112, 192], 'render']);
});

test('toggles native fullscreen on the image stage', async () => {
    const calls = [];
    const target = {
        async requestFullscreen() { calls.push('request'); },
    };
    const documentLike = {
        fullscreenElement: null,
        async exitFullscreen() { calls.push('exit'); },
    };
    const root = {
        querySelector(selector) {
            return selector === '[data-viewer-fullscreen-target]' ? target : null;
        },
    };

    await toggleFullscreen(root, documentLike);
    documentLike.fullscreenElement = target;
    await toggleFullscreen(root, documentLike);

    assert.deepEqual(calls, ['request', 'exit']);
});

test('moves the study to the safe Indonesian error state when viewer bootstrap fails', async () => {
    const root = viewerRoot();
    await bootstrapViewer(root, async () => { throw new Error('bundle diagnostic'); });

    assert.equal(root.dataset.viewerState, 'error');
    assert.equal(root.status.textContent, root.dataset.unavailableMessage);
    assert.equal(root.secondaryStatus.textContent, root.dataset.unavailableMessage);
    assert.equal(root.error.hidden, false);
    assert.equal(root.error.textContent, root.dataset.displayErrorMessage);
    assert.equal(root.error.textContent.includes('bundle'), false);
});

test('keeps a slow render in loading after the short bootstrap boundary', async () => {
    const root = viewerRoot();
    root.dataset.viewerTimeoutMs = '5';
    const bootstrap = bootstrapViewer(root, async () => ({
        renderStudy: async () => {
            root.dataset.viewerState = 'loading';
            await new Promise((resolve) => setTimeout(resolve, 30));
            root.dataset.viewerState = 'ready';
        },
    }));

    await new Promise((resolve) => setTimeout(resolve, 10));

    assert.equal(root.dataset.viewerState, 'loading');
    await bootstrap;
    assert.equal(root.dataset.viewerState, 'ready');
});

test('keeps viewer and DICOM load timeouts distinct', () => {
    assert.equal(VIEWER_TIMEOUT_MS, 45000);
    assert.equal(DICOM_LOAD_TIMEOUT_MS, 300000);
    assert.equal(dicomLoadTimeout(viewerRoot()), 300000);
    assert.equal(dicomLoadTimeout({ dataset: { dicomLoadTimeoutMs: '7' } }), 7);
});

test('bounds dynamic viewer-module import with the short timeout', async () => {
    const root = viewerRoot();
    root.dataset.viewerTimeoutMs = '5';

    await bootstrapViewer(root, () => new Promise(() => {}));

    assert.equal(root.dataset.viewerState, 'error');
});

test('bounds an unsettled viewer promise', async () => {
    await assert.rejects(
        withViewerTimeout(new Promise(() => {}), 5),
        /timed out/
    );
});

test('renders a safe Indonesian error state instead of a loader diagnostic', () => {
    const root = viewerRoot();
    setViewerState(root, 'loading', 'Memuat DICOM…');
    setViewerState(root, 'error', 'secret HTTP body and storage key');

    assert.equal(root.dataset.viewerState, 'error');
    assert.equal(root.status.textContent, 'Studi DICOM tidak tersedia.');
    assert.equal(root.secondaryStatus.textContent, 'Studi DICOM tidak tersedia.');
    assert.equal(root.error.hidden, false);
    assert.equal(root.error.textContent, 'Studi DICOM tidak dapat ditampilkan.');
    assert.equal(root.error.textContent.includes('secret'), false);
});

test('tolerates a stale engine during resize', () => {
    let resized = false;
    resizeRenderingEngine({ resize() { resized = true; } });
    resizeRenderingEngine({ resize() { throw new Error('unmounted'); } });
    assert.equal(resized, true);
});
