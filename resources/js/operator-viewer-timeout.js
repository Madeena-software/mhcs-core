export const VIEWER_TIMEOUT_MS = 45000;

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
