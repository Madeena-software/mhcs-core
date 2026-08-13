import {
    Enums,
    RenderingEngine,
    init as cornerstoneInit,
} from '@cornerstonejs/core';
import dicomImageLoader, {
    init as dicomImageLoaderInit,
} from '@cornerstonejs/dicom-image-loader';
import dicomParser from 'dicom-parser';
import { VIEWER_TIMEOUT_MS, withViewerTimeout } from './operator-viewer-timeout.js';

const VIEWPORT_ID = 'mhcs-dicom-viewport';
const ENGINE_ID = 'mhcs-dicom-engine';

export const VIEWER_INTERACTIONS = Object.freeze(['zoom', 'pan']);

export function setViewerState(root, state, message = '') {
    const status = root.querySelector('#dicom-viewer-status');
    const errorNode = root.querySelector('#dicom-viewer-error');
    root.dataset.viewerState = state;
    if (status) {
        status.textContent = state === 'error' ? root.dataset.unavailableMessage : message;
    }
    if (errorNode) {
        errorNode.hidden = state !== 'error';
        errorNode.textContent = state === 'error' ? root.dataset.displayErrorMessage : '';
    }
}

export function resizeRenderingEngine(renderingEngine) {
    if (!renderingEngine) {
        return;
    }
    try {
        renderingEngine.resize(true, true);
    } catch {
        // The engine may already be unmounted while the window is resizing.
    }
}

function bindZoomAndPan(element, viewport) {
    element.addEventListener('wheel', (event) => {
        event.preventDefault();
        const factor = event.deltaY < 0 ? 1.1 : 0.9;
        viewport.setZoom(Math.min(10, Math.max(0.25, viewport.getZoom() * factor)));
        viewport.render();
    }, { passive: false });

    let lastPoint = null;
    element.addEventListener('pointerdown', (event) => {
        lastPoint = { x: event.clientX, y: event.clientY };
        element.setPointerCapture(event.pointerId);
    });
    element.addEventListener('pointermove', (event) => {
        if (lastPoint === null) {
            return;
        }
        const pan = viewport.getPan();
        viewport.setPan({
            x: pan.x + (event.clientX - lastPoint.x),
            y: pan.y + (event.clientY - lastPoint.y),
        });
        lastPoint = { x: event.clientX, y: event.clientY };
        viewport.render();
    });
    const stopPan = () => { lastPoint = null; };
    element.addEventListener('pointerup', stopPan);
    element.addEventListener('pointercancel', stopPan);
    element.addEventListener('pointerleave', stopPan);
}

function viewerTimeout(root) {
    const configured = Number(root.dataset.viewerTimeoutMs);

    return Number.isFinite(configured) && configured > 0 ? configured : VIEWER_TIMEOUT_MS;
}

export async function renderStudy(root) {
    const element = root.querySelector('[data-testid="dicom-viewport"]');
    if (!element) {
        return;
    }

    setViewerState(root, 'loading', root.dataset.loadingMessage);
    window.__mhcsDicomViewerReady = false;
    const timeoutMs = viewerTimeout(root);

    try {
        if (typeof dicomParser.parseDicom !== 'function') {
            throw new Error(root.dataset.parserUnavailableMessage);
        }
        await withViewerTimeout(Promise.resolve(cornerstoneInit()), timeoutMs);
        await withViewerTimeout(Promise.resolve(dicomImageLoaderInit({ maxWebWorkers: 1 })), timeoutMs);
        const renderingEngine = new RenderingEngine(ENGINE_ID);
        renderingEngine.enableElement({
            viewportId: VIEWPORT_ID,
            element,
            type: Enums.ViewportType.STACK,
        });

        window.addEventListener('resize', () => {
            resizeRenderingEngine(renderingEngine);
        });

        const viewport = renderingEngine.getViewport(VIEWPORT_ID);
        const imageId = 'wadouri:' + root.dataset.imageUrl;
        const image = dicomImageLoader.wadouri.loadImage(imageId);
        if (!image?.promise) {
            throw new Error(root.dataset.parserUnavailableMessage);
        }
        await withViewerTimeout(image.promise, timeoutMs);
        await withViewerTimeout(viewport.setStack([imageId], 0), timeoutMs);

        const center = Number(root.dataset.windowCenter);
        const width = Number(root.dataset.windowWidth);
        if (Number.isFinite(center) && Number.isFinite(width) && width > 0) {
            viewport.setProperties({
                voiRange: {
                    lower: center - (width / 2),
                    upper: center + (width / 2),
                },
            });
        }
        viewport.resetCamera();
        await withViewerTimeout(Promise.resolve(viewport.render()), timeoutMs);
        bindZoomAndPan(element, viewport);
        window.__mhcsDicomViewerReady = true;
        setViewerState(root, 'ready', root.dataset.readyMessage);
    } catch {
        setViewerState(root, 'error');
    }
}
