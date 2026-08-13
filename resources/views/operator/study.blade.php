@extends('operator.layout')

@section('title', __('DICOM study :reference', ['reference' => $display_reference ?? __('Identifier withheld')]))

@section('content')
<style>
    body:has(#dicom-study) { overflow-x: hidden; }
    body:has(#dicom-study) .nav { display: none !important; }
    body:has(#dicom-study) .shell { max-width: 100%; padding: 20px 14px 32px; }
    .dicom-study-shell { display: flex; flex-direction: column; gap: 16px; max-width: 760px; margin: 0 auto; min-height: calc(100vh - 80px); }
    .dicom-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #344651; }
    .dicom-title-wrap { display: flex; flex-direction: column; gap: 6px; }
    .dicom-title-wrap h1 { margin: 0; font-size: 24px; line-height: 1.2; color: #f4f7fb; }
    .dicom-badges { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .dicom-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #172a36; color: #8fdfff; border: 1px solid #2b4554; }
    .dicom-stage-frame { position: relative; background: radial-gradient(circle at center, #101922 0%, #04070a 72%); border: 1px solid #344651; border-radius: 12px; box-shadow: inset 0 0 24px rgba(0, 0, 0, 0.8), 0 8px 24px rgba(0, 0, 0, 0.3); overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; width: min(100%, 620px); aspect-ratio: 5 / 6; min-height: 420px; max-height: 78vh; margin: 0 auto; }
    .dicom-viewport-stack { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; }
    .dicom-viewport { width: 100%; height: 100%; min-height: 0; background: #04070a; touch-action: none; }
    .dicom-notice { padding: 12px 16px; border-radius: 8px; background: #214858; color: #d8edf5; font-size: 14px; margin: 0; }
    .dicom-actions-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
    .dicom-actions-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

    .dicom-guidance { margin: 0; }
    .dicom-readonly { margin: 0; font-size: 14px; }
</style>
<div class="dicom-study-shell">
    <section aria-labelledby="dicom-study-title" id="dicom-study" data-dicom-viewer
             data-image-url="{{ route('operator.study.dicom', $study_id) }}"
             data-unavailable-message="{{ __('DICOM study unavailable.') }}"
             data-parser-unavailable-message="{{ __('The DICOM viewer is unavailable.') }}"
             data-loading-message="{{ __('Loading DICOM…') }}"
             data-ready-message="{{ __('DICOM study ready. Automatic VOI is applied.') }}"
             data-display-error-message="{{ __('The DICOM study could not be displayed.') }}"
             data-viewer-timeout-ms="15000"
             data-viewer-state="loading"
             @if ($window_center !== null) data-window-center="{{ $window_center }}" @endif
             @if ($window_width !== null) data-window-width="{{ $window_width }}" @endif>
        <div class="dicom-header">
            <div class="dicom-title-wrap">
                <h1 id="dicom-study-title">{{ __('DICOM study :reference', ['reference' => $display_reference ?? __('Identifier withheld')]) }}</h1>
                <div class="dicom-badges" aria-label="{{ __('Study mode badges') }}">
                    <span class="dicom-badge">{{ __('Automatic VOI') }}</span>
                    <span class="dicom-badge">{{ __('Zoom and pan only') }}</span>
                </div>
                <p class="muted dicom-readonly">{{ __('Read-only DICOM study. Automatic VOI is applied; use drag to pan and wheel to zoom.') }}</p>
            </div>
        </div>

        <div style="margin-top: 8px;">
            <p id="dicom-viewer-status" role="status" aria-live="polite">{{ __('Loading DICOM…') }}</p>
            <p id="dicom-viewer-error" class="error" role="alert" hidden></p>
            <p class="muted dicom-guidance">{{ __('Pointer drag pans the image. Use the mouse wheel to zoom.') }}</p>
        </div>

        <noscript><p class="dicom-notice" role="status">{{ __('JavaScript is unavailable. Enable JavaScript to view this study in the current tab.') }}</p></noscript>

        <div class="dicom-stage-frame">
            <div class="dicom-viewport-stack" aria-label="{{ __('DICOM viewport stack') }}">
                <div class="dicom-viewport" data-testid="dicom-viewport" aria-label="{{ __('DICOM image viewport') }}"></div>
            </div>
        </div>

        <div class="dicom-actions-bar">
            <div class="dicom-actions-group">
                <a download href="{{ route('operator.study.download', $study_id) }}" class="primary-action">{{ __('Download DICOM') }}</a>
                <a href="{{ route('operator.study.results') }}" class="secondary">{{ __('Back to DICOM results') }}</a>
            </div>
        </div>
    </section>
</div>
@vite('resources/js/app.js')
@endsection
