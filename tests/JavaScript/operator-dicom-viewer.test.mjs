import assert from 'node:assert/strict';
import test from 'node:test';

const viewerModule = await import('../../resources/js/operator-dicom-viewer.js');
const {
    flipViewport,
    isPrimaryPointerDrag,
    loadDicomStack,
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
        ['camera', { flipHorizontal: false, flipVertical: false }],
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

// Deterministic 3x3 asymmetric raster fixture where left, center, and right columns are distinct
const ASYMMETRIC_FIXTURE = Object.freeze([
    ['L_TOP', 'C_TOP', 'R_TOP'],
    ['L_MID', 'C_MID', 'R_MID'],
    ['L_BOT', 'C_BOT', 'R_BOT'],
]);

function projectPresentation(matrix, { flipHorizontal = false, flipVertical = false, rotation = 0 } = {}) {
    let result = matrix.map((row) => [...row]);
    if (flipHorizontal) {
        result = result.map((row) => [...row].reverse());
    }
    if (flipVertical) {
        result = [...result].reverse();
    }
    const normalizedRotation = ((rotation % 360) + 360) % 360;
    if (normalizedRotation === 90) {
        const rows = result.length;
        const cols = result[0].length;
        const rotated = [];
        for (let c = 0; c < cols; c++) {
            const newRow = [];
            for (let r = rows - 1; r >= 0; r--) {
                newRow.push(result[r][c]);
            }
            rotated.push(newRow);
        }
        result = rotated;
    } else if (normalizedRotation === 180) {
        result = result.map((row) => [...row].reverse()).reverse();
    } else if (normalizedRotation === 270) {
        const rows = result.length;
        const cols = result[0].length;
        const rotated = [];
        for (let c = cols - 1; c >= 0; c--) {
            const newRow = [];
            for (let r = 0; r < rows; r++) {
                newRow.push(result[r][c]);
            }
            rotated.push(newRow);
        }
        result = rotated;
    }
    return result;
}

function createPresentationViewport(initialCamera = { flipHorizontal: false, flipVertical: false }, initialRotation = 0) {
    let camera = { ...initialCamera };
    let rotation = initialRotation;
    let renderedCount = 0;

    return {
        resetCamera() {
            camera = { flipHorizontal: false, flipVertical: false };
        },
        setRotation(r) { rotation = r; },
        getRotation() { return rotation; },
        setCamera(next) {
            camera = { ...camera, ...next };
        },
        getCamera() { return { ...camera }; },
        render() { renderedCount += 1; },
        get renderedCount() { return renderedCount; },
        renderPresentation(source = ASYMMETRIC_FIXTURE) {
            return projectPresentation(source, {
                flipHorizontal: camera.flipHorizontal,
                flipVertical: camera.flipVertical,
                rotation,
            });
        },
    };
}

test('asymmetric fixture mathematically distinguishes canonical from horizontally mirrored presentation', () => {
    const canonical = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: false, flipVertical: false, rotation: 0 });
    const mirrored = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: true, flipVertical: false, rotation: 0 });

    assert.notDeepEqual(canonical, mirrored);
    assert.deepEqual(canonical, ASYMMETRIC_FIXTURE);
    assert.equal(canonical[0][0], 'L_TOP');
    assert.equal(mirrored[0][0], 'R_TOP');
    assert.equal(canonical[0][2], 'R_TOP');
    assert.equal(mirrored[0][2], 'L_TOP');
    assert.equal(canonical[1][0], 'L_MID');
    assert.equal(mirrored[1][0], 'R_MID');
});

test('default viewport presentation renders canonical orientation without horizontal reflection', () => {
    const viewport = createPresentationViewport();
    resetViewport(viewport);

    const canonical = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: false, flipVertical: false, rotation: 0 });
    const mirrored = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: true, flipVertical: false, rotation: 0 });
    const presentation = viewport.renderPresentation(ASYMMETRIC_FIXTURE);

    assert.deepEqual(presentation, canonical);
    assert.notDeepEqual(presentation, mirrored);
    assert.equal(presentation[0][0], 'L_TOP');
    assert.equal(presentation[0][2], 'R_TOP');
    assert.equal(viewport.getCamera().flipHorizontal, false);
    assert.equal(viewport.getCamera().flipVertical, false);
    assert.equal(viewport.getRotation(), 0);
});

test('accidental horizontal mirroring default would be caught by asymmetric fixture regression', () => {
    const legacyMirroredCamera = { flipHorizontal: true, flipVertical: false, rotation: 0 };
    const buggyPresentation = projectPresentation(ASYMMETRIC_FIXTURE, legacyMirroredCamera);
    const canonical = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: false, flipVertical: false, rotation: 0 });

    assert.notDeepEqual(buggyPresentation, canonical);
    assert.equal(buggyPresentation[0][0], 'R_TOP');
    assert.equal(buggyPresentation[0][2], 'L_TOP');
});

test('explicit horizontal flip reversibly transforms viewport from canonical to mirrored and back', () => {
    const viewport = createPresentationViewport();
    resetViewport(viewport);

    const canonical = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: false, flipVertical: false, rotation: 0 });
    const mirrored = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: true, flipVertical: false, rotation: 0 });

    assert.deepEqual(viewport.renderPresentation(), canonical);

    // Explicit horizontal flip
    flipViewport(viewport, 'horizontal');
    assert.equal(viewport.getCamera().flipHorizontal, true);
    assert.deepEqual(viewport.renderPresentation(), mirrored);

    // Second explicit flip (reversibility)
    flipViewport(viewport, 'horizontal');
    assert.equal(viewport.getCamera().flipHorizontal, false);
    assert.deepEqual(viewport.renderPresentation(), canonical);
});

test('resetViewport restores canonical presentation and clears all flips and rotations', () => {
    const viewport = createPresentationViewport();
    resetViewport(viewport);

    const canonical = projectPresentation(ASYMMETRIC_FIXTURE, { flipHorizontal: false, flipVertical: false, rotation: 0 });

    rotateViewport(viewport, 90);
    flipViewport(viewport, 'vertical');
    flipViewport(viewport, 'horizontal');

    assert.notDeepEqual(viewport.renderPresentation(), canonical);

    resetViewport(viewport);

    assert.equal(viewport.getCamera().flipHorizontal, false);
    assert.equal(viewport.getCamera().flipVertical, false);
    assert.equal(viewport.getRotation(), 0);
    assert.deepEqual(viewport.renderPresentation(), canonical);
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

test('loads a stack through the bounded DICOM boundary', async () => {
    let calls = 0;
    const viewport = {
        setStack(ids, index) {
            calls += 1;
            assert.deepEqual([ids, index], [['wadouri:test'], 0]);
            return new Promise((resolve) => setTimeout(resolve, 20));
        },
    };

    const load = loadDicomStack(viewport, 'wadouri:test', 20);
    await new Promise((resolve) => setTimeout(resolve, 5));
    assert.equal(calls, 1);
    await load;
});

test('resolves a stack before the DICOM-specific timeout', async () => {
    const viewport = { setStack: async () => 'loaded' };

    await assert.doesNotReject(loadDicomStack(viewport, 'wadouri:test', 20));
});

test('rejects an unsettled stack at the DICOM-specific timeout', async () => {
    const viewport = { setStack: () => new Promise(() => {}) };

    await assert.rejects(loadDicomStack(viewport, 'wadouri:test', 5), /timed out/);
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
