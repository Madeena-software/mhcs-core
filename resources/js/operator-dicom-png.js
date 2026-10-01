/**
 * Operator DICOM PNG Exporter
 *
 * Exports a DICOM study directly to PNG at native pixel dimensions (columns × rows)
 * using Cornerstone CPU rendering and default VOI LUT.
 * Completely independent of viewport zoom/pan/rotation/flip or DPR.
 */

import dicomParser from 'dicom-parser';
import {
    decodeImageFrame,
    createImage,
    wadouri,
} from '@cornerstonejs/dicom-image-loader';
import {
    init as cornerstoneInit,
    metaData,
} from '@cornerstonejs/core';
import { registerDicomDecoder } from './operator-dicom-viewer.js';

export { registerDicomDecoder };

export function extractPixelData(dataSet, frame = 0) {
    const pixelDataElement = dataSet.elements.x7fe00010;
    if (!pixelDataElement) {
        throw new Error('DICOM pixel data tag (7FE0,0010) is missing.');
    }
    if (pixelDataElement.encapsulated) {
        if (!dataSet.elements.x7fe00010.fragments || dataSet.elements.x7fe00010.fragments.length === 0) {
            throw new Error('Encapsulated pixel data has no fragments.');
        }
        const fragments = dataSet.elements.x7fe00010.fragments;
        const targetFragment = fragments.length > 1 ? fragments[frame + 1] || fragments[1] : fragments[0];
        return new Uint8Array(dataSet.byteArray.buffer, targetFragment.offset, targetFragment.length);
    } else {
        return new Uint8Array(dataSet.byteArray.buffer, pixelDataElement.dataOffset, pixelDataElement.length);
    }
}

export function generateLinearVOILUT(windowWidth, windowCenter) {
    return function (modalityLutValue) {
        const value = ((modalityLutValue - (windowCenter - 0.5)) / (windowWidth - 1) + 0.5) * 255.0;
        return Math.min(Math.max(value, 0), 255);
    };
}

export function generateLinearModalityLUT(slope, intercept) {
    return (storedPixelValue) => storedPixelValue * slope + intercept;
}

/**
 * Renders an uncompressed or decoded Cornerstone Image object to an offscreen Canvas
 * at its exact native width and height (columns × rows) with default VOI LUT applied.
 */
export function renderImageToCanvas(image, canvas) {
    const width = image.columns;
    const height = image.rows;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    const imgData = ctx.createImageData(width, height);
    const data = imgData.data;

    // Color images
    if (image.color) {
        const pixelData = image.voxelManager ? image.voxelManager.getScalarData() : image.getPixelData();
        if (pixelData.length === width * height * 4) {
            data.set(pixelData);
        } else if (pixelData.length === width * height * 3) {
            let j = 0;
            for (let i = 0; i < pixelData.length; i += 3) {
                data[j++] = pixelData[i];
                data[j++] = pixelData[i + 1];
                data[j++] = pixelData[i + 2];
                data[j++] = 255;
            }
        }
    } else {
        // Grayscale / MONOCHROME
        const pixelData = image.voxelManager ? image.voxelManager.getScalarData() : image.getPixelData();
        const minVal = image.minPixelValue ?? 0;
        const maxVal = image.maxPixelValue ?? 65535;
        const windowWidth = Number(image.windowWidth) || (maxVal - minVal) || 1;
        const windowCenter = Number(image.windowCenter) || (minVal + windowWidth / 2);
        const invert = Boolean(image.invert);

        const slope = image.slope ?? 1;
        const intercept = image.intercept ?? 0;
        const mlutfn = generateLinearModalityLUT(slope, intercept);
        const vlutfn = generateLinearVOILUT(windowWidth, windowCenter);

        const numPixels = width * height;
        let pIdx = 0;
        let cIdx = 0;

        for (let i = 0; i < numPixels; i++) {
            const raw = pixelData[pIdx++];
            const modVal = image.isPreScaled ? raw : mlutfn(raw);
            let intensity = vlutfn(modVal);
            if (invert) {
                intensity = 255 - intensity;
            }
            intensity = Math.min(255, Math.max(0, Math.round(intensity)));

            data[cIdx++] = intensity;
            data[cIdx++] = intensity;
            data[cIdx++] = intensity;
            data[cIdx++] = 255;
        }
    }

    ctx.putImageData(imgData, 0, 0);
    return canvas;
}

/**
 * Downloads a DICOM study as a native PNG.
 */
export async function downloadStudyAsPng(dicomUrl, filename, statusCallback = () => {}) {
    registerDicomDecoder();
    try {
        await cornerstoneInit();
    } catch {
        // Continue if already initialized
    }

    statusCallback('downloading');
    const response = await fetch(dicomUrl, { credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error(`Failed to fetch DICOM file: HTTP ${response.status}`);
    }

    statusCallback('decoding');
    const arrayBuffer = await response.arrayBuffer();
    const byteArray = new Uint8Array(arrayBuffer);
    const dataSet = dicomParser.parseDicom(byteArray);

    const imageId = 'wadouri:' + dicomUrl;
    wadouri.metaData.getImagePixelModule = wadouri.metaData.getImagePixelModule || (() => {});
    const pixelModule = {
        samplesPerPixel: dataSet.uint16('x00280002') || 1,
        photometricInterpretation: dataSet.string('x00280004') || 'MONOCHROME2',
        rows: dataSet.uint16('x00280010'),
        columns: dataSet.uint16('x00280011'),
        bitsAllocated: dataSet.uint16('x00280100') || 16,
        bitsStored: dataSet.uint16('x00280101') || 16,
        highBit: dataSet.uint16('x00280102') || 15,
        pixelRepresentation: dataSet.uint16('x00280103') || 0,
        planarConfiguration: dataSet.uint16('x00280006') || 0,
        smallestPixelValue: dataSet.uint16('x00280106'),
        largestPixelValue: dataSet.uint16('x00280107'),
    };

    const windowCenter = dataSet.floatString('x00281050') ?? dataSet.intString('x00281050');
    const windowWidth = dataSet.floatString('x00281051') ?? dataSet.intString('x00281051');
    const rescaleIntercept = dataSet.floatString('x00281052') ?? 0;
    const rescaleSlope = dataSet.floatString('x00281053') ?? 1;

    metaData.addProvider((type, qImageId) => {
        if (qImageId === imageId) {
            if (type === 'imagePixelModule') {
                return pixelModule;
            }
            if (type === 'voiLutModule') {
                return {
                    windowCenter: windowCenter !== undefined ? Number(windowCenter) : undefined,
                    windowWidth: windowWidth !== undefined ? Number(windowWidth) : undefined,
                };
            }
            if (type === 'modalityLutModule') {
                return {
                    rescaleIntercept: Number(rescaleIntercept),
                    rescaleSlope: Number(rescaleSlope),
                };
            }
        }
    }, 10000);

    const pixelData = extractPixelData(dataSet, 0);
    const transferSyntax = dataSet.string('x00020010') || '1.2.840.10008.1.2';

    const image = await createImage(imageId, pixelData, transferSyntax, {
        decodeLevel: 0,
    });

    statusCallback('rendering');
    let canvas = document.createElement('canvas');
    renderImageToCanvas(image, canvas);

    statusCallback('saving');
    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((b) => {
            if (b) resolve(b);
            else reject(new Error('Canvas toBlob failed'));
        }, 'image/png');
    });

    // Trigger download
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename.endsWith('.png') ? filename : `${filename}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Cleanup resources
    setTimeout(() => {
        URL.revokeObjectURL(blobUrl);
        canvas.width = 0;
        canvas.height = 0;
        canvas = null;
    }, 1000);
}

export function initPngDownloadButtons(container = document) {
    const buttons = container.querySelectorAll('[data-png-download]');
    buttons.forEach((btn) => {
        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            if (btn.disabled || btn.dataset.busy === 'true') return;

            const url = btn.dataset.dicomUrl;
            const ref = btn.dataset.reference || 'study';
            const originalText = btn.textContent;

            btn.disabled = true;
            btn.dataset.busy = 'true';
            btn.setAttribute('aria-busy', 'true');
            btn.textContent = 'Memproses...';

            try {
                await downloadStudyAsPng(url, `${ref}.png`, (stage) => {
                    if (stage === 'rendering' || stage === 'saving') {
                        btn.textContent = 'Menyimpan...';
                    }
                });
                btn.textContent = 'Selesai';
                setTimeout(() => {
                    btn.disabled = false;
                    btn.dataset.busy = 'false';
                    btn.removeAttribute('aria-busy');
                    btn.textContent = originalText;
                }, 1500);
            } catch (err) {
                console.error('[PNG DOWNLOAD ERROR]:', err);
                btn.textContent = 'Gagal';
                alert('Gagal mengunduh gambar PNG. Pastikan berkas studi tersedia.');
                setTimeout(() => {
                    btn.disabled = false;
                    btn.dataset.busy = 'false';
                    btn.removeAttribute('aria-busy');
                    btn.textContent = originalText;
                }, 2000);
            }
        });
    });
}
