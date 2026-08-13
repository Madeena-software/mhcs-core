@extends('operator.layout')

@section('title', __('Submit radiograph capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit radiograph capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }} <code>{{ $admissionId }}</code>; {{ __('Upload one radiograph NPZ and its matching gain NPZ.') }}</p>
    <p class="warning" role="alert">{{ __('Keep this page open until the upload status is complete.') }}</p>
    <p id="capture-status" role="status" aria-live="polite" data-start="{{ __('Capture upload started.') }}" data-progress="{{ __('Uploading capture: :percent% (:loaded of :total bytes).') }}" data-processing="{{ __('Capture accepted. Waiting for DICOM processing.') }}" data-missing="{{ __('Capture sources are incomplete. Choose only the missing file.') }}" data-ready="{{ __('DICOM study ready. Opening results.') }}" data-failed="{{ __('Processing failed. No DICOM study is available. Retry the missing source or check status.') }}" data-error="{{ __('The capture status could not be checked. Retrying.') }}"></p>
    <progress id="capture-progress" max="100" value="0" hidden aria-label="{{ __('Capture upload progress') }}"></progress>
    @if ($form['missing'] !== [])
        <p class="muted">{{ __('Missing capture files:') }} {{ implode(', ', array_map(static fn (string $type): string => __($type === 'radiograph' ? 'Radiograph NPZ' : 'Matching gain NPZ'), $form['missing'])) }}</p>
    @else
        <p class="muted">{{ __('This capture is complete.') }}</p>
    @endif
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="capture-form" data-status-url="{{ route('operator.xray-capture.status', $admissionId) }}" data-missing="{{ implode(',', $form['missing']) }}" data-has-capture="{{ $form['capture_id'] === null ? '0' : '1' }}">
        @csrf
        @if (in_array('radiograph', $form['missing'], true))
            <label for="radiograph_npz">{{ __('Radiograph NPZ') }}</label>
            <input id="radiograph_npz" name="radiograph_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        @if (in_array('gain', $form['missing'], true))
            <label for="gain_npz">{{ __('Matching gain NPZ') }}</label>
            <input id="gain_npz" name="gain_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        <input type="hidden" name="submission_id" value="{{ $form['submission_id'] }}">
        @if ($form['missing'] !== [])
            <button type="submit">{{ __('Submit capture set') }}</button>
        @endif
    </form>
</section>
<script>
    (() => {
        const form = document.getElementById('capture-form');
        if (!form) return;
        const status = document.getElementById('capture-status');
        const progress = document.getElementById('capture-progress');
        const inputs = [...form.querySelectorAll('input[type="file"]')];
        const button = form.querySelector('button[type="submit"]');
        let active = false;
        let terminal = false;
        let pollTimer = null;
        let request = null;

        const setStatus = (message) => { status.textContent = message; };
        const setControls = (disabled, missing = form.dataset.missing.split(',').filter(Boolean)) => {
            inputs.forEach((input) => {
                const type = input.name === 'radiograph_npz' ? 'radiograph' : 'gain';
                input.disabled = disabled || !missing.includes(type);
            });
            if (button) button.disabled = disabled || missing.length === 0;
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
                terminal = true;
                setStatus(status.dataset.ready);
                window.location.assign(result.ready_results_url);
                return true;
            }
            if (result.processing_state === 'failed') {
                stopPolling();
                active = false;
                terminal = true;
                setControls(false, missing);
                setStatus(status.dataset.failed);
                return true;
            }
            if (result.processing_state === 'awaiting_sources') {
                stopPolling();
                active = false;
                terminal = true;
                setControls(false, missing);
                setStatus(status.dataset.missing);
                return true;
            }
            active = true;
            terminal = false;
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
                    } catch (error) {
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
            if (active && !terminal) {
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
            if (active && !terminal) return;
            active = true;
            terminal = false;
            setControls(true);
            progress.hidden = false;
            progress.value = 0;
            setStatus(status.dataset.start);
            request = new XMLHttpRequest();
            request.open('POST', form.action);
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', (uploadEvent) => {
                if (!uploadEvent.lengthComputable) return;
                const percent = Math.round((uploadEvent.loaded / uploadEvent.total) * 100);
                progress.value = percent;
                setStatus(status.dataset.progress.replace(':percent', percent).replace(':loaded', uploadEvent.loaded.toLocaleString()).replace(':total', uploadEvent.total.toLocaleString()));
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
            request.send(new FormData(form));
        });
        if (form.dataset.hasCapture === '1') {
            active = true;
            setControls(true);
            poll();
        } else {
            setControls(false);
        }
    })();
</script>
@endsection
