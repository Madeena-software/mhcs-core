import {
    Enums,
    RenderingEngine,
    getWebWorkerManager,
    init as cornerstoneInit,
} from '@cornerstonejs/core';
import {
    decodeImageFrame,
    init as dicomImageLoaderInit,
} from '@cornerstonejs/dicom-image-loader';
import dicomParser from 'dicom-parser';
import {
    dicomLoadTimeout,
    VIEWER_TIMEOUT_MS,
    withViewerTimeout,
} from './operator-viewer-timeout.js';

const VIEWPORT_ID = 'mhcs-dicom-viewport';
const ENGINE_ID = 'mhcs-dicom-engine';
const DEFAULT_VIEW = Object.freeze({ rotation: 0, flipHorizontal: true, flipVertical: false });
const PAN_START_THRESHOLD = 3;

export const VIEWER_INTERACTIONS = Object.freeze(['zoom', 'pan', 'reset', 'rotate', 'flip', 'fullscreen']);

function viewerStatusNodes(root) {
    if (typeof root.querySelectorAll === 'function') {
        const nodes = [...root.querySelectorAll('[data-viewer-status]')];
        if (nodes.length > 0) {
            return nodes;
        }
    }

    const status = root.querySelector('#dicom-viewer-status');

    return status ? [status] : [];
}

export function setViewerState(root, state, message = '') {
    const errorNode = root.querySelector('#dicom-viewer-error');
    root.dataset.viewerState = state;
    for (const status of viewerStatusNodes(root)) {
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

function applyDefaultViewport(viewport) {
    viewport.resetCamera();
    viewport.setRotation(DEFAULT_VIEW.rotation);
    viewport.setCamera({
        flipHorizontal: DEFAULT_VIEW.flipHorizontal,
        flipVertical: DEFAULT_VIEW.flipVertical,
    });
}

export function resetViewport(viewport) {
    applyDefaultViewport(viewport);
    viewport.render();
}

export function rotateViewport(viewport, degrees) {
    const rotation = ((viewport.getRotation() + degrees) % 360 + 360) % 360;
    viewport.setRotation(rotation);
    viewport.render();
}

export function flipViewport(viewport, axis) {
    const camera = viewport.getCamera();
    viewport.setCamera({
        flipHorizontal: axis === 'horizontal' ? !camera.flipHorizontal : camera.flipHorizontal,
        flipVertical: axis === 'vertical' ? !camera.flipVertical : camera.flipVertical,
    });
    viewport.render();
}

export function isPrimaryPointerDrag(event, lastPoint, startPoint = lastPoint) {
    if (lastPoint === null || event.buttons !== 1) {
        return false;
    }
    return Math.hypot(event.clientX - startPoint.x, event.clientY - startPoint.y) >= PAN_START_THRESHOLD;
}

export function panViewport(viewport, deltaX, deltaY) {
    const pan = viewport.getPan();
    viewport.setPan([pan[0] + deltaX, pan[1] + deltaY]);
    viewport.render();
}

export async function toggleFullscreen(root, documentLike = globalThis.document) {
    const target = root.querySelector('[data-viewer-fullscreen-target]');
    if (!target) {
        return;
    }
    if (documentLike.fullscreenElement === target) {
        if (typeof documentLike.exitFullscreen === 'function') {
            await documentLike.exitFullscreen();
        }
        return;
    }
    if (typeof target.requestFullscreen === 'function') {
        await target.requestFullscreen();
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
    let startPoint = null;
    element.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        lastPoint = { x: event.clientX, y: event.clientY };
        startPoint = lastPoint;
        element.setPointerCapture(event.pointerId);
    });
    element.addEventListener('pointermove', (event) => {
        if (!isPrimaryPointerDrag(event, lastPoint, startPoint)) {
            return;
        }
        panViewport(viewport, event.clientX - lastPoint.x, event.clientY - lastPoint.y);
        lastPoint = { x: event.clientX, y: event.clientY };
    });
    const stopPan = () => { lastPoint = null; startPoint = null; };
    element.addEventListener('pointerup', stopPan);
    element.addEventListener('pointercancel', stopPan);
    element.addEventListener('pointerleave', stopPan);
}

function bindViewerControls(root, viewport, renderingEngine) {
    const documentLike = root.ownerDocument || globalThis.document;
    const buttons = root.querySelectorAll('[data-viewer-action]');
    const updateFullscreenLabels = () => {
        const fullscreen = documentLike.fullscreenElement !== null;
        for (const button of buttons) {
            if (button.dataset.viewerAction !== 'fullscreen') {
                continue;
            }
            const label = fullscreen ? button.dataset.exitFullscreenLabel : button.dataset.fullscreenLabel;
            button.setAttribute('aria-label', label);
            button.title = label;
        }
    };

    for (const button of buttons) {
        button.addEventListener('click', async () => {
            try {
                switch (button.dataset.viewerAction) {
                    case 'reset':
                        resetViewport(viewport);
                        break;
                    case 'rotate-left':
                        rotateViewport(viewport, -90);
                        break;
                    case 'rotate-right':
                        rotateViewport(viewport, 90);
                        break;
                    case 'flip-horizontal':
                        flipViewport(viewport, 'horizontal');
                        break;
                    case 'flip-vertical':
                        flipViewport(viewport, 'vertical');
                        break;
                    case 'fullscreen':
                        await toggleFullscreen(root, documentLike);
                        break;
                    default:
                        break;
                }
            } catch {
                // A browser may reject fullscreen after the user gesture is gone.
            }
        });
    }
    documentLike.addEventListener?.('fullscreenchange', () => {
        updateFullscreenLabels();
        if (typeof globalThis.requestAnimationFrame === 'function') {
            globalThis.requestAnimationFrame(() => resizeRenderingEngine(renderingEngine));
        } else {
            resizeRenderingEngine(renderingEngine);
        }
    });
    updateFullscreenLabels();
}

function viewerTimeout(root) {
    const configured = Number(root.dataset.viewerTimeoutMs);

    return Number.isFinite(configured) && configured > 0 ? configured : VIEWER_TIMEOUT_MS;
}

export function loadDicomStack(viewport, imageId, timeoutMs) {
    return withViewerTimeout(viewport.setStack([imageId], 0), timeoutMs);
}

export function registerDicomDecoder() {
    try {
        dicomImageLoaderInit({ maxWebWorkers: 1 });
    } catch {
        // Fall back to direct decoder registration if native worker initialization fails.
    }
    const workerManager = typeof getWebWorkerManager === 'function' ? getWebWorkerManager() : null;
    if (workerManager) {
        const inProcessDecoder = {
            async decodeTask({ imageFrame, transferSyntax, decodeConfig, options, pixelData, callbackFn }) {
                return decodeImageFrame(imageFrame, transferSyntax, pixelData, decodeConfig, options, callbackFn);
            },
        };
        workerManager.workerRegistry['dicomImageLoader'] = {
            instances: [inProcessDecoder],
            nativeWorkers: [],
            loadCounters: [0],
            lastActiveTime: [Date.now()],
            workerFn: () => null,
            autoTerminateOnIdle: false,
            idleCheckIntervalId: null,
            idleTimeThreshold: 3000,
        };
    }
}

export async function renderStudy(root) {
    const element = root.querySelector('[data-testid="dicom-viewport"]');
    if (!element) {
        return;
    }

    setViewerState(root, 'loading', root.dataset.loadingMessage);
    window.__mhcsDicomViewerReady = false;
    const timeoutMs = viewerTimeout(root);
    const dicomTimeoutMs = dicomLoadTimeout(root);

    try {
        if (typeof dicomParser.parseDicom !== 'function') {
            throw new Error(root.dataset.parserUnavailableMessage);
        }
        await withViewerTimeout(Promise.resolve(cornerstoneInit()), timeoutMs);
        registerDicomDecoder();
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
        await loadDicomStack(viewport, imageId, dicomTimeoutMs);

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
        applyDefaultViewport(viewport);
        await withViewerTimeout(Promise.resolve(viewport.render()), timeoutMs);
        bindZoomAndPan(element, viewport);
        bindViewerControls(root, viewport, renderingEngine);
        window.__mhcsDicomViewerReady = true;
        setViewerState(root, 'ready', root.dataset.readyMessage);
    } catch (error) {
        console.error('[RENDER STUDY ERROR]:', error);
        setViewerState(root, 'error');
    }
}
