import dicomParser from 'dicom-parser';
import { createImage, wadouri } from '@cornerstonejs/dicom-image-loader';
import { init as cornerstoneInit, metaData, utilities } from '@cornerstonejs/core';
import { registerDicomDecoder } from './operator-dicom-viewer.js';
import { VIEWER_TIMEOUT_MS, DICOM_LOAD_TIMEOUT_MS, withViewerTimeout } from './operator-viewer-timeout.js';

export { registerDicomDecoder };

export function extractPixelData(dataSet, frame = 0) {
    // The installed native extractor assumes byteArray starts at buffer offset zero.
    const pixelDataSet = dataSet.byteArray.byteOffset === 0 ? dataSet
        : Object.assign(Object.create(dataSet), { byteArray: new Uint8Array(dataSet.byteArray) });
    const pixels = wadouri.getPixelData(pixelDataSet, frame);
    if (!pixels?.length) throw new Error('PNG export failed.');
    return pixels;
}

export async function renderImageToCanvas(image, canvas) {
    if (!Number.isInteger(image.columns) || !Number.isInteger(image.rows) || image.columns <= 0 || image.rows <= 0) {
        throw new Error('PNG export failed.');
    }
    canvas.width = image.columns;
    canvas.height = image.rows;
    // Export the native pixel grid, rather than fitting physical pixel spacing into a viewport.
    await utilities.renderToCanvasCPU(canvas, { ...image, rowPixelSpacing: 1, columnPixelSpacing: 1 });
    return canvas;
}

export async function downloadStudyAsPng(dicomUrl, filename, statusCallback = () => {}, {
    timeoutMs = VIEWER_TIMEOUT_MS, loadTimeoutMs = DICOM_LOAD_TIMEOUT_MS,
} = {}) {
    const abortController = new AbortController();
    let provider;
    let canvas;
    let blobUrl;
    let link;
    try {
        await withViewerTimeout(cornerstoneInit(), timeoutMs);
        registerDicomDecoder();
        statusCallback('downloading');
        const response = await withViewerTimeout(fetch(dicomUrl, {
            credentials: 'same-origin', signal: abortController.signal,
        }), loadTimeoutMs);
        if (!response.ok) throw new Error('PNG export failed.');
        const buffer = await withViewerTimeout(response.arrayBuffer(), loadTimeoutMs);
        statusCallback('decoding');
        const dataSet = dicomParser.parseDicom(new Uint8Array(buffer));
        const imageId = `wadouri:png-export-${crypto.randomUUID()}`;
        provider = (type, requestedId) => requestedId === imageId
            ? wadouri.metaData.metadataForDataset(type, imageId, dataSet) : undefined;
        metaData.addProvider(provider, 10000);
        const image = await withViewerTimeout(createImage(imageId, extractPixelData(dataSet),
            dataSet.string('x00020010') || '1.2.840.10008.1.2', { decodeLevel: 0, preScale: { enabled: false } }), timeoutMs);
        statusCallback('rendering');
        canvas = document.createElement('canvas');
        await withViewerTimeout(renderImageToCanvas(image, canvas), timeoutMs);
        statusCallback('saving');
        const blob = await withViewerTimeout(new Promise((resolve, reject) => {
            canvas.toBlob(value => value ? resolve(value) : reject(new Error('PNG export failed.')), 'image/png');
        }), timeoutMs);
        blobUrl = URL.createObjectURL(blob);
        link = document.createElement('a');
        link.href = blobUrl;
        // The existing public study reference is the only permitted filename component.
        link.download = `${String(filename).replace(/\.png$/i, '').replace(/[^A-Za-z0-9_-]/g, '_') || 'DCM'}.png`;
        document.body.appendChild(link);
        link.click();
    } catch {
        throw new Error('PNG export failed.');
    } finally {
        abortController.abort();
        if (provider) metaData.removeProvider(provider);
        link?.remove();
        if (blobUrl) URL.revokeObjectURL(blobUrl);
        if (canvas) { canvas.width = 0; canvas.height = 0; }
    }
}

export function initPngDownloadButtons(container = document) {
    container.querySelectorAll('[data-png-download]').forEach(btn => {
        if (btn.dataset.pngInitialized === 'true') return;
        const messages = JSON.parse(btn.dataset.pngMessages || '{}');
        if (!['processing', 'saving', 'done', 'error'].every(key => typeof messages[key] === 'string' && messages[key])) return;
        btn.dataset.pngInitialized = 'true';
        const originalText = btn.textContent;
        btn.addEventListener('click', async event => {
            event.preventDefault();
            if (btn.disabled || btn.dataset.busy === 'true') return;
            btn.disabled = true;
            btn.dataset.busy = 'true';
            btn.setAttribute('aria-busy', 'true');
            btn.textContent = messages.processing;
            try {
                await downloadStudyAsPng(btn.dataset.dicomUrl, `${btn.dataset.reference || 'DCM'}.png`, stage => {
                    if (stage === 'rendering' || stage === 'saving') btn.textContent = messages.saving;
                });
                btn.textContent = messages.done;
            } catch {
                btn.textContent = messages.error;
                alert(messages.error);
            } finally {
                btn.disabled = false;
                btn.dataset.busy = 'false';
                btn.removeAttribute('aria-busy');
                setTimeout(() => { if (btn.dataset.busy !== 'true') btn.textContent = originalText; }, 1500);
            }
        });
    });
}
