export const VIEWER_TIMEOUT_MS = 45000;
export const DICOM_LOAD_TIMEOUT_MS = 300000;

export function dicomLoadTimeout(root) {
    const configured = Number(root?.dataset?.dicomLoadTimeoutMs);

    return Number.isFinite(configured) && configured > 0 ? configured : DICOM_LOAD_TIMEOUT_MS;
}

export function withViewerTimeout(value, timeoutMs = VIEWER_TIMEOUT_MS) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Viewer operation timed out.')), timeoutMs);
        Promise.resolve(value).then(
            (result) => {
                clearTimeout(timer);
                resolve(result);
            },
            (error) => {
                clearTimeout(timer);
                reject(error);
            },
        );
    });
}
