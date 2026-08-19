export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '0 B';
    if (bytes < 1024) return `${Math.round(bytes)} B`;

    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = -1;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${Number(value.toFixed(1))} ${units[unit]}`;
}

export function formatSpeed(bytesPerSecond) {
    return `${formatBytes(bytesPerSecond)}/s`;
}

export function formatDuration(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '0 detik';
    let remaining = Math.floor(seconds);
    const hours = Math.floor(remaining / 3600);
    remaining %= 3600;
    const minutes = Math.floor(remaining / 60);
    remaining %= 60;
    const parts = [];
    if (hours > 0) parts.push(`${hours} jam`);
    if (minutes > 0) parts.push(`${minutes} menit`);
    if (remaining > 0 || parts.length === 0) parts.push(`${remaining} detik`);

    return parts.join(' ');
}

export function uploadTelemetry({ loaded, total, elapsedMs, lengthComputable }) {
    const validLoaded = Number.isFinite(loaded) && loaded >= 0;
    const computable = lengthComputable === true && Number.isFinite(total) && total > 0 && validLoaded;
    const percent = computable ? Math.min(100, Math.max(0, Math.round((loaded / total) * 100))) : null;
    const speed = computable && loaded > 0 && Number.isFinite(elapsedMs) && elapsedMs > 0
        ? loaded / (elapsedMs / 1000)
        : null;
    const eta = speed !== null && loaded < total
        ? Math.max(0, Math.ceil((total - loaded) / speed))
        : null;

    return {
        percent,
        loaded: formatBytes(validLoaded ? loaded : 0),
        total: computable ? formatBytes(total) : null,
        speed: speed === null || !Number.isFinite(speed) ? null : formatSpeed(speed),
        eta: eta === null || !Number.isFinite(eta) ? null : formatDuration(eta),
    };
}

const now = () => typeof performance !== 'undefined' && typeof performance.now === 'function'
    ? performance.now()
    : Date.now();

const message = (template, values) => Object.entries(values).reduce(
    (result, [key, value]) => result.replace(`:${key}`, String(value)),
    template,
);

export function initCaptureUpload() {
    const form = document.getElementById('capture-form');
    if (!form) return;

    const status = document.getElementById('capture-status');
    const progress = document.getElementById('capture-progress');
    const inputs = [...form.querySelectorAll('input[type="file"]')];
    const button = form.querySelector('button[type="submit"]');
    let active = false;
    let uploading = false;
    let pollTimer = null;
    let request = null;

    const setStatus = (text) => { status.textContent = text; };
    const setControls = (disabled, missing = form.dataset.missing.split(',').filter(Boolean)) => {
        inputs.forEach((input) => {
            const type = input.name === 'radiograph_npz' ? 'radiograph' : 'gain';
            input.disabled = disabled || !missing.includes(type);
        });
        if (button) button.disabled = disabled;
    };
    const stopPolling = () => {
        if (pollTimer !== null) window.clearTimeout(pollTimer);
        pollTimer = null;
    };
    const applyStatus = (result) => {
        const missing = Array.isArray(result.missing_components) ? result.missing_components : [];
        form.dataset.missing = missing.join(',');
        if (result.processing_state === 'ready') {
            stopPolling();
            active = false;
            uploading = false;
            setStatus(status.dataset.ready);
            window.location.assign(result.ready_results_url);
            return true;
        }
        if (result.processing_state === 'failed') {
            stopPolling();
            active = false;
            uploading = false;
            setControls(false, missing);
            setStatus(status.dataset.failed);
            return true;
        }
        if (result.processing_state === 'awaiting_sources') {
            stopPolling();
            active = false;
            uploading = false;
            setControls(false, missing);
            setStatus(status.dataset.missing);
            return true;
        }
        active = true;
        uploading = false;
        setControls(true, missing);
        setStatus(status.dataset.processing);
        return false;
    };
    const poll = () => {
        stopPolling();
        const xhr = new XMLHttpRequest();
        request = xhr;
        xhr.open('GET', form.dataset.statusUrl);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = () => {
            if (request === xhr) request = null;
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    if (applyStatus(JSON.parse(xhr.responseText))) return;
                } catch {
                    setStatus(status.dataset.error);
                }
            } else {
                setStatus(status.dataset.error);
            }
            pollTimer = window.setTimeout(poll, 2000);
        };
        xhr.onerror = () => {
            if (request === xhr) request = null;
            setStatus(status.dataset.error);
            pollTimer = window.setTimeout(poll, 2000);
        };
        xhr.send();
    };

    window.addEventListener('beforeunload', (event) => {
        if (uploading) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.addEventListener('pagehide', () => {
        stopPolling();
        if (request) request.abort();
    }, { once: true });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (active) return;
        active = true;
        uploading = true;
        const body = new FormData(form);
        const startedAt = now();
        setControls(true);
        progress.hidden = false;
        progress.value = 0;
        setStatus(status.dataset.start);
        request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.upload.addEventListener('progress', (uploadEvent) => {
            const telemetry = uploadTelemetry({
                loaded: uploadEvent.loaded,
                total: uploadEvent.total,
                elapsedMs: now() - startedAt,
                lengthComputable: uploadEvent.lengthComputable,
            });
            if (telemetry.percent === null) {
                progress.hidden = true;
                setStatus(message(status.dataset.progressUnknown, { loaded: telemetry.loaded }));
                return;
            }

            progress.hidden = false;
            progress.value = telemetry.percent;
            if (telemetry.percent >= 100) {
                setStatus(status.dataset.processing);
                return;
            }

            const values = { percent: telemetry.percent, loaded: telemetry.loaded, total: telemetry.total };
            setStatus(telemetry.speed !== null && telemetry.eta !== null
                ? message(status.dataset.telemetry, { ...values, speed: telemetry.speed, eta: telemetry.eta })
                : message(status.dataset.calculating, values));
        });
        request.onload = () => {
            request = null;
            poll();
        };
        request.onerror = () => {
            request = null;
            setStatus(status.dataset.error);
            poll();
        };
        request.send(body);
    });
    if (form.dataset.hasCapture === '1') {
        active = true;
        uploading = false;
        setControls(true);
        poll();
    } else {
        setControls(false);
    }
}
