import { VIEWER_TIMEOUT_MS, withViewerTimeout } from './operator-viewer-timeout.js';

function viewerTimeout(root) {
    const configured = Number(root.dataset.viewerTimeoutMs);

    return Number.isFinite(configured) && configured > 0 ? configured : VIEWER_TIMEOUT_MS;
}

export function setViewerUnavailable(root) {
    root.dataset.viewerState = 'error';

    const status = root.querySelector('#dicom-viewer-status');
    const error = root.querySelector('#dicom-viewer-error');
    if (status) {
        status.textContent = root.dataset.unavailableMessage;
    }
    if (error) {
        error.hidden = false;
        error.textContent = root.dataset.displayErrorMessage;
    }
}

export async function bootstrapViewer(root, importViewer = () => import('./operator-dicom-viewer.js')) {
    if (typeof window !== 'undefined') {
        window.__mhcsDicomViewerReady = false;
    }

    try {
        const viewer = await withViewerTimeout(importViewer(), viewerTimeout(root));
        await withViewerTimeout(viewer.renderStudy(root), viewerTimeout(root));
    } catch {
        setViewerUnavailable(root);
    }
}

if (typeof document !== 'undefined') {
    const root = document.querySelector('[data-dicom-viewer]');
    if (root) {
        bootstrapViewer(root);
    }
}
