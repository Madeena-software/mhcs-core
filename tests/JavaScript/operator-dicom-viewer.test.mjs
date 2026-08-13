import assert from 'node:assert/strict';
import test from 'node:test';

const viewerModule = await import('../../resources/js/operator-dicom-viewer.js');
const {
    resizeRenderingEngine,
    setViewerState,
} = viewerModule;
const { bootstrapViewer } = await import('../../resources/js/app.js');
const { withViewerTimeout } = await import('../../resources/js/operator-viewer-timeout.js');

function viewerRoot() {
    const status = { textContent: '' };
    const secondaryStatus = { textContent: '' };
    const error = { hidden: true, textContent: '' };

    return {
        dataset: {
            unavailableMessage: 'Studi DICOM tidak tersedia.',
            displayErrorMessage: 'Studi DICOM tidak dapat ditampilkan.',
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
    assert.deepEqual(viewerModule.VIEWER_INTERACTIONS, ['zoom', 'pan']);
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
