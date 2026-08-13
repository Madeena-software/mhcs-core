@extends('operator.layout')

@section('title', __('Submit radiograph capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit radiograph capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }} <code>{{ $admissionId }}</code>; {{ __('Upload one radiograph NPZ and its matching gain NPZ.') }}</p>
    <p class="warning" role="alert">{{ __('Do not navigate away during upload. Processing continues safely after capture acceptance.') }}</p>
    <p id="capture-status" role="status" aria-live="polite" data-start="{{ __('Capture upload started.') }}" data-progress="{{ __('Uploading capture: :percent% (:loaded of :total bytes).') }}" data-processing="{{ __('Capture accepted. Waiting for DICOM processing.') }}" data-missing="{{ __('Capture sources are incomplete. Choose only the missing file.') }}" data-ready="{{ __('DICOM study ready. Opening results.') }}" data-failed="{{ __('Processing failed. No DICOM study is available. Retry DICOM processing or check status.') }}" data-error="{{ __('The capture status could not be checked. Retrying.') }}"></p>
    <progress id="capture-progress" max="100" value="0" hidden aria-label="{{ __('Capture upload progress') }}"></progress>
    @if ($form['missing'] !== [])
        <p class="muted">{{ __('Missing capture files:') }} {{ implode(', ', array_map(static fn (string $type): string => __($type === 'radiograph' ? 'Radiograph NPZ' : 'Matching gain NPZ'), $form['missing'])) }}</p>
    @else
        <p class="muted">{{ __('This capture is complete.') }}</p>
    @endif
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="capture-form" data-status-url="{{ route('operator.xray-capture.status', $admissionId) }}" data-missing="{{ implode(',', $form['missing']) }}" data-has-capture="{{ $form['capture_id'] === null ? '0' : '1' }}">
        @csrf
        @if ($form['metadata'] !== null)
            @php($metadata = $form['metadata'])
            @if ($form['metadata_editable'])
                <fieldset>
                    <legend>{{ __('Capture metadata') }}</legend>
                    <label for="study_description">{{ __('Study description') }}</label>
                    <input id="study_description" name="metadata[examination][study_description]" type="text" maxlength="64" value="{{ old('metadata.examination.study_description', $metadata['examination']['study_description']) }}" required>
                    <label for="detector_type">{{ __('Detector type') }}</label>
                    <select id="detector_type" name="metadata[capture][detector_type]" required>
                        <option value="" disabled {{ old('metadata.capture.detector_type', $metadata['capture']['detector_type']) === null ? 'selected' : '' }}>{{ __('Select detector type') }}</option>
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::DETECTOR_TYPES as $option)
                            <option value="{{ $option }}" @selected(old('metadata.capture.detector_type', $metadata['capture']['detector_type']) === $option)>{{ __($option) }}</option>
                        @endforeach
                    </select>
                    <label for="body_part_examined">{{ __('Body part examined') }}</label>
                    <input id="body_part_examined" name="metadata[capture][body_part_examined]" type="text" list="body-part-options" value="{{ old('metadata.capture.body_part_examined', $metadata['capture']['body_part_examined']) }}" required>
                    <datalist id="body-part-options">
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::BODY_PARTS as $option)
                            <option value="{{ $option }}">{{ __($option) }}</option>
                        @endforeach
                    </datalist>
                    <label for="laterality">{{ __('Laterality') }}</label>
                    <input id="laterality" name="metadata[capture][laterality]" type="text" list="laterality-options" value="{{ old('metadata.capture.laterality', $metadata['capture']['laterality']) }}" required>
                    <datalist id="laterality-options">
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::LATERALITIES as $option)
                            <option value="{{ $option }}">{{ __($option) }}</option>
                        @endforeach
                    </datalist>
                    <label for="projection">{{ __('Projection') }}</label>
                    <input id="projection" name="metadata[capture][projection]" type="text" list="projection-options" value="{{ old('metadata.capture.projection', $metadata['capture']['projection']) }}" required>
                    <datalist id="projection-options">
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::PROJECTIONS as $option)
                            <option value="{{ $option }}">{{ __($option) }}</option>
                        @endforeach
                    </datalist>
                </fieldset>
            @else
                <fieldset disabled>
                    <legend>{{ __('Capture metadata (frozen)') }}</legend>
                    <p>{{ __('Study description') }}: <code>{{ $metadata['examination']['study_description'] }}</code></p>
                    <p>{{ __('Detector type') }}: <code>{{ __($metadata['capture']['detector_type']) }}</code></p>
                    <p>{{ __('Body part examined') }}: <code>{{ __($metadata['capture']['body_part_examined']) }}</code></p>
                    <p>{{ __('Laterality') }}: <code>{{ $metadata['capture']['laterality'] }} ({{ __($metadata['capture']['laterality']) }})</code></p>
                    <p>{{ __('Projection') }}: <code>{{ __($metadata['capture']['projection']) }}</code></p>
                </fieldset>
            @endif
        @endif
        @if (in_array('radiograph', $form['missing'], true))
            <label for="radiograph_npz">{{ __('Radiograph NPZ') }}</label>
            <input id="radiograph_npz" name="radiograph_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        @if (in_array('gain', $form['missing'], true))
            <label for="gain_npz">{{ __('Matching gain NPZ') }}</label>
            <input id="gain_npz" name="gain_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        <input type="hidden" name="submission_id" value="{{ $form['submission_id'] }}">
        @if ($form['missing'] !== [] || $form['can_retry'])
            <button type="submit">{{ $form['can_retry'] ? __('Retry DICOM processing') : __('Submit capture set') }}</button>
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
        let uploading = false;
        let pollTimer = null;
        let request = null;

        const setStatus = (message) => { status.textContent = message; };
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
    })();
</script>
@endsection
