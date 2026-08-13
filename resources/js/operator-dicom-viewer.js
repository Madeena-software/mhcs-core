import {
    Enums,
    RenderingEngine,
    init as cornerstoneInit,
} from '@cornerstonejs/core';
import dicomImageLoader, {
    init as dicomImageLoaderInit,
} from '@cornerstonejs/dicom-image-loader';
import dicomParser from 'dicom-parser';

const VIEWPORT_ID = 'mhcs-dicom-viewport';
const ENGINE_ID = 'mhcs-dicom-engine';

export const VIEWER_INTERACTIONS = Object.freeze(['zoom', 'pan']);

function setStatus(root, message, error = false) {
    const status = root.querySelector('#dicom-viewer-status');
    const errorNode = root.querySelector('#dicom-viewer-error');
    if (status) {
        status.textContent = error ? root.dataset.unavailableMessage : message;
    }
    if (errorNode) {
        errorNode.hidden = !error;
        errorNode.textContent = error ? message : '';
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

export function bindMonitorWindow(root) {
    if (window.name === 'mhcs-dicom-monitor') {
        document.body.classList.add('is-monitor-popup');
        root.dataset.isMonitorPopup = 'true';
    }

    const button = root.querySelector('[data-open-monitor]');
    if (!button) {
        return;
    }
    button.addEventListener('click', () => {
        const targetUrl = button.dataset.monitorUrl || window.location.href;
        const popupStatus = root.querySelector('#dicom-popup-status');
        try {
            const popup = window.open(
                targetUrl,
                'mhcs-dicom-monitor',
                'width=640,height=960,resizable=yes,scrollbars=yes'
            );
            if (popup && !popup.closed) {
                popup.focus();
                if (popupStatus) {
                    popupStatus.hidden = true;
                    popupStatus.textContent = '';
                }
            } else {
                if (popupStatus) {
                    popupStatus.hidden = false;
                    popupStatus.textContent = root.dataset.popupBlockedMessage || 'Browser memblokir jendela pop-up. Lanjutkan pada tab ini atau izinkan pop-up.';
                }
            }
        } catch (e) {
            if (popupStatus) {
                popupStatus.hidden = false;
                popupStatus.textContent = root.dataset.popupBlockedMessage || 'Browser memblokir jendela pop-up. Lanjutkan pada tab ini atau izinkan pop-up.';
            }
        }
    });
}

async function renderStudy(root) {
    const element = root.querySelector('[data-testid="dicom-viewport"]');
    if (!element) {
        return;
    }

    bindMonitorWindow(root);

    try {
        if (typeof dicomParser.parseDicom !== 'function') {
            throw new Error(root.dataset.parserUnavailableMessage);
        }
        cornerstoneInit();
        dicomImageLoaderInit({ maxWebWorkers: 1 });
        const renderingEngine = new RenderingEngine(ENGINE_ID);
        renderingEngine.enableElement({
            viewportId: VIEWPORT_ID,
            element,
            type: Enums.ViewportType.STACK,
        });

        window.addEventListener('resize', () => {
            if (renderingEngine) {
                try {
                    renderingEngine.resize(true, true);
                } catch (e) {
                    // ignore if unmounted
                }
            }
        });

        const viewport = renderingEngine.getViewport(VIEWPORT_ID);
        const imageId = 'wadouri:' + root.dataset.imageUrl;
        await dicomImageLoader.wadouri.loadImage(imageId).promise;
        await viewport.setStack([imageId], 0);

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
        viewport.render();
        bindZoomAndPan(element, viewport);
        root.dataset.viewerState = 'ready';
        window.__mhcsDicomViewerReady = true;
        setStatus(root, root.dataset.readyMessage);
    } catch (error) {
        root.dataset.viewerState = 'error';
        setStatus(root, error instanceof Error ? error.message : root.dataset.displayErrorMessage, true);
    }
}

const root = document.querySelector('[data-dicom-viewer]');
if (root) {
    renderStudy(root);
}
