import { VIEWER_TIMEOUT_MS, withViewerTimeout } from './operator-viewer-timeout.js';
import { initCaptureUpload } from './operator-upload.js';
import { initWorklistFilters } from './operator-worklist-filters.js';

function viewerTimeout(root) {
    const configured = Number(root.dataset.viewerTimeoutMs);

    return Number.isFinite(configured) && configured > 0 ? configured : VIEWER_TIMEOUT_MS;
}

export function setViewerUnavailable(root) {
    root.dataset.viewerState = 'error';

    const error = root.querySelector('#dicom-viewer-error');
    const statuses = typeof root.querySelectorAll === 'function'
        ? [...root.querySelectorAll('[data-viewer-status]')]
        : [];
    if (statuses.length > 0) {
        for (const status of statuses) {
            status.textContent = root.dataset.unavailableMessage;
        }
    } else {
        const status = root.querySelector('#dicom-viewer-status');
        if (status) {
            status.textContent = root.dataset.unavailableMessage;
        }
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
        await viewer.renderStudy(root);
    } catch (error) {
        console.error('[BOOTSTRAP VIEWER ERROR]:', error);
        setViewerUnavailable(root);
    }
}

export async function bootstrapPngDownloads(container, importPng = () => import('./operator-dicom-png.js'), timeoutMs = VIEWER_TIMEOUT_MS) {
    const buttons = [...container.querySelectorAll('[data-png-download]')];
    const labels = buttons.map((button) => button.textContent);
    const restore = () => buttons.forEach((button, index) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = labels[index];
    });
    buttons.forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = JSON.parse(button.dataset.pngMessages).processing;
    });
    try {
        const png = await withViewerTimeout(importPng(), timeoutMs);
        restore();
        png.initPngDownloadButtons(container);
    } catch {
        restore();
        buttons.forEach((button) => {
            button.dataset.pngState = 'error';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                alert(JSON.parse(button.dataset.pngMessages).error);
            });
        });
    }
}


if (typeof document !== 'undefined') {
    initCaptureUpload();
    initWorklistFilters(document);
    if (document.querySelector('[data-png-download]')) {
        bootstrapPngDownloads(document);
    }
    const root = document.querySelector('[data-dicom-viewer]');
    if (root) {
        bootstrapViewer(root);
    }
}
