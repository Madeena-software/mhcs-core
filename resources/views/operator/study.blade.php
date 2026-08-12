@extends('operator.layout')

@section('title', __('DICOM study'))

@section('content')
<style>
    .dicom-viewport-stack { display: grid; gap: 16px; max-width: 760px; }
    .dicom-viewport { min-height: 480px; background: #05090c; border: 1px solid #435461; touch-action: none; }
</style>
<section aria-labelledby="dicom-study-title" id="dicom-study" data-dicom-viewer
         data-image-url="{{ route('operator.study.dicom', $study_id) }}"
         data-unavailable-message="{{ __('DICOM study unavailable.') }}"
         data-parser-unavailable-message="{{ __('The DICOM viewer is unavailable.') }}"
         data-ready-message="{{ __('DICOM study ready. Automatic VOI is applied.') }}"
         data-display-error-message="{{ __('The DICOM study could not be displayed.') }}"
         @if ($window_center !== null) data-window-center="{{ $window_center }}" @endif
         @if ($window_width !== null) data-window-width="{{ $window_width }}" @endif>
    <h1 id="dicom-study-title">{{ __('DICOM study') }}</h1>
    <p class="muted">{{ __('Automatic VOI. Zoom and pan only.') }}</p>
    <div class="dicom-viewport-stack" aria-label="{{ __('DICOM viewport stack') }}">
        <div class="dicom-viewport" data-testid="dicom-viewport" aria-label="{{ __('DICOM image viewport') }}"></div>
    </div>
    <p id="dicom-viewer-status" role="status">{{ __('Loading DICOM…') }}</p>
    <p id="dicom-viewer-error" class="error" role="alert" hidden></p>
    <p class="actions">
        <a download href="{{ route('operator.study.download', $study_id) }}">{{ __('Download DICOM') }}</a>
        <a href="{{ route('operator.study.results') }}">{{ __('Back to DICOM results') }}</a>
    </p>
</section>
@vite('resources/js/app.js')
@endsection
