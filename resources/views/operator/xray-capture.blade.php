@extends('operator.layout')

@section('title', __('Submit radiograph capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit radiograph capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }}: <code>{{ $form['ticket_number'] }}</code>; {{ __('Upload one radiograph NPZ and its matching gain NPZ.') }}</p>
    <p class="warning" role="alert">{{ __('Do not navigate away during upload. Processing continues safely after capture acceptance.') }}</p>
    <p id="capture-status" role="status" aria-live="polite" data-start="{{ __('Capture upload started.') }}" data-progress-unknown="{{ __('Uploading capture: :loaded.') }}" data-calculating="{{ __('Uploading capture: :percent% (:loaded of :total). Speed and ETA are being calculated.') }}" data-telemetry="{{ __('Uploading capture: :percent% (:loaded of :total). Speed: :speed. ETA: :eta.') }}" data-processing="{{ __('Capture accepted. Waiting for DICOM processing.') }}" data-missing="{{ __('Capture sources are incomplete. Choose only the missing file.') }}" data-ready="{{ __('DICOM study ready. Opening results.') }}" data-failed="{{ __('Processing failed. No DICOM study is available. Retry DICOM processing or check status.') }}" data-error="{{ __('The capture status could not be checked. Retrying.') }}" data-normalization-error="{{ __('The radiograph NPZ could not be safely prepared for upload. Choose a valid NPZ file.') }}"></p>
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
                    <select id="body_part_examined" name="metadata[capture][body_part_examined]" required>
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::BODY_PARTS as $option)
                            <option value="{{ $option }}" @selected(old('metadata.capture.body_part_examined', $metadata['capture']['body_part_examined']) === $option)>{{ __($option) }}</option>
                        @endforeach
                    </select>
                    <label for="laterality">{{ __('Laterality') }}</label>
                    <select id="laterality" name="metadata[capture][laterality]" required>
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::LATERALITIES as $option)
                            <option value="{{ $option }}" @selected(old('metadata.capture.laterality', $metadata['capture']['laterality']) === $option)>{{ __($option) }}</option>
                        @endforeach
                    </select>
                    <label for="projection">{{ __('Projection') }}</label>
                    <select id="projection" name="metadata[capture][projection]" required>
                        @foreach (\App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService::PROJECTIONS as $option)
                            <option value="{{ $option }}" @selected(old('metadata.capture.projection', $metadata['capture']['projection']) === $option)>{{ __($option) }}</option>
                        @endforeach
                    </select>
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
@vite('resources/js/app.js')
@endsection
