@extends('operator.layout')

@section('title', 'DICOM study')

@section('content')
<style>
    .dicom-viewport-stack { display: grid; gap: 16px; max-width: 760px; }
    .dicom-viewport { min-height: 480px; background: #05090c; border: 1px solid #435461; touch-action: none; }
</style>
<section aria-labelledby="dicom-study-title" id="dicom-study" data-dicom-viewer
         data-image-url="{{ route('operator.study.dicom', $study_id) }}"
         data-window-center="{{ $window_center }}"
         data-window-width="{{ $window_width }}">
    <h1 id="dicom-study-title">DICOM study</h1>
    <p class="muted">Automatic VOI. Zoom and pan only.</p>
    <div class="dicom-viewport-stack" aria-label="DICOM viewport stack">
        <div class="dicom-viewport" data-testid="dicom-viewport" aria-label="DICOM image viewport"></div>
    </div>
    <p id="dicom-viewer-status" role="status">Loading synthetic DICOM…</p>
    <p id="dicom-viewer-error" class="error" role="alert" hidden></p>
    <p class="actions">
        <a download href="{{ route('operator.study.download', $study_id) }}">Download DICOM</a>
        <a href="{{ route('operator.xray-readiness-worklist') }}">Back to X-ray readiness</a>
    </p>
</section>
@vite('resources/js/app.js')
@endsection
