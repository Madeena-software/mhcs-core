@extends('operator.layout')

@section('title', __('DICOM study :reference', ['reference' => $display_reference ?? __('Identifier withheld')]))

@section('content')
<style>
    body:has(#dicom-study) { overflow: hidden; background: #101010; }
    body:has(#dicom-study) .shell { max-width: none; height: 100vh; box-sizing: border-box; padding: 12px; }
    body:has(#dicom-study) .nav,
    .shell:has(#dicom-study) > .notice,
    .shell:has(#dicom-study) > .card { display: none !important; }

    .dicom-study-shell {
        --viewer-bg: #101010;
        --viewer-surface: #191818;
        --viewer-surface-2: #242323;
        --viewer-surface-3: #313030;
        --viewer-fg: #f3f0ef;
        --viewer-fg-2: #c0c7d3;
        --viewer-fg-3: #89909d;
        --viewer-border: #383737;
        --viewer-border-subtle: #2d2c2c;
        --viewer-accent: #48b8ad;
        --viewer-accent-soft: rgba(72, 184, 173, .14);
        --viewer-green: #69c98b;
        display: grid;
        grid-template-rows: 56px minmax(0, 1fr) 48px;
        height: calc(100vh - 24px);
        min-height: 620px;
        overflow: hidden;
        color: var(--viewer-fg);
        background: var(--viewer-bg);
        border: 1px solid var(--viewer-border);
        border-radius: 10px;
        font-family: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
    }

    .dicom-topbar,
    .dicom-bottom-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 0 16px;
        background: var(--viewer-surface);
        border-bottom: 1px solid var(--viewer-border);
    }
    .dicom-bottom-bar { border-top: 1px solid var(--viewer-border); border-bottom: 0; }
    .dicom-brand { display: flex; align-items: center; gap: 10px; color: inherit; text-decoration: none; min-width: 220px; }
    .dicom-brand-mark { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; background: var(--viewer-fg); color: #111; font: 700 11px/1 ui-monospace, monospace; }
    .dicom-brand-title { display: block; font-size: 13px; font-weight: 600; }
    .dicom-brand-subtitle { display: block; margin-top: 2px; color: var(--viewer-fg-3); font-size: 10px; }
    .dicom-module-tabs { display: flex; gap: 4px; padding: 3px; background: var(--viewer-surface-2); border: 1px solid var(--viewer-border); border-radius: 8px; }
    .dicom-module-tab { padding: 6px 14px; color: var(--viewer-fg-3); border-radius: 6px; font-size: 11px; }
    .dicom-module-tab.active { color: var(--viewer-fg); background: var(--viewer-surface-3); }
    .dicom-topbar-status { display: flex; align-items: center; gap: 7px; min-width: 220px; justify-content: flex-end; color: var(--viewer-fg-2); font: 11px ui-monospace, monospace; }
    .dicom-live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--viewer-green); box-shadow: 0 0 8px rgba(105, 201, 139, .55); }

    .dicom-workstation { display: grid; grid-template-columns: 280px minmax(0, 1fr) 280px; min-height: 0; overflow: hidden; }
    .booth-left, .right-sidebar { min-width: 0; overflow-y: auto; background: var(--viewer-surface); }
    .booth-left { border-right: 1px solid var(--viewer-border); }
    .right-sidebar { padding: 16px; border-left: 1px solid var(--viewer-border); background: var(--viewer-surface-2); }
    .patient-card { padding: 22px 18px; border-bottom: 1px solid var(--viewer-border-subtle); }
    .patient-card-label, .panel-header { display: flex; align-items: center; gap: 8px; color: var(--viewer-fg-3); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .patient-card-locked, .viewer-readonly { padding: 2px 6px; color: var(--viewer-accent); background: var(--viewer-accent-soft); border-radius: 4px; font-size: 9px; }
    .patient-card-name { margin-top: 16px; color: var(--viewer-fg); font-size: 20px; font-weight: 600; line-height: 1.2; word-break: break-word; }
    .patient-card-id { margin-top: 5px; color: var(--viewer-fg-3); font: 11px ui-monospace, monospace; word-break: break-word; }
    .patient-details { display: grid; gap: 12px; padding: 18px; }
    .detail-row { display: grid; grid-template-columns: 92px minmax(0, 1fr); gap: 10px; font-size: 11px; }
    .detail-row dt { color: var(--viewer-fg-3); }
    .detail-row dd { margin: 0; color: var(--viewer-fg-2); font: 11px ui-monospace, monospace; overflow-wrap: anywhere; }
    .session-meta { display: grid; gap: 8px; padding: 18px; border-top: 1px solid var(--viewer-border-subtle); color: var(--viewer-fg-3); font-size: 11px; }
    .session-meta strong { color: var(--viewer-fg-2); font: 11px ui-monospace, monospace; }

    .center-viewer { display: flex; min-width: 0; flex-direction: column; overflow: hidden; background: var(--viewer-bg); }
    .viewer-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; min-height: 48px; padding: 0 16px; background: var(--viewer-surface); border-bottom: 1px solid var(--viewer-border); }
    .viewer-toolbar-left, .viewer-toolbar-right { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .viewer-state { color: var(--viewer-fg-2); font: 11px ui-monospace, monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .viewer-meta { color: var(--viewer-fg-3); font: 10px ui-monospace, monospace; white-space: nowrap; }
    .viewer-stage { position: relative; display: grid; flex: 1; place-items: center; min-height: 0; padding: 22px; overflow: hidden; background: radial-gradient(circle at center, #242323 0%, #101010 78%); }
    .dicom-stage-frame { position: relative; display: flex; align-items: center; justify-content: center; width: min(74%, 470px); height: min(86%, 650px); min-height: 280px; overflow: hidden; background: #050505; border: 1px solid #454b51; border-radius: 8px; box-shadow: 0 20px 50px rgba(0, 0, 0, .6); }
    .dicom-viewport-stack { width: 100%; height: 100%; position: relative; }
    .dicom-viewport { width: 100%; height: 100%; background: #050505; touch-action: none; }
    .viewer-error-card { display: none; flex-direction: column; align-items: center; gap: 10px; width: min(86%, 330px); padding: 22px; color: var(--viewer-fg-2); background: rgba(25, 24, 24, .96); border: 1px solid #654546; border-radius: 10px; text-align: center; }
    .viewer-error-icon { display: grid; place-items: center; width: 32px; height: 32px; color: #f2a7a0; border: 1px solid #8b5758; border-radius: 50%; font-weight: 800; }
    .viewer-error-card strong { color: var(--viewer-fg); font-size: 13px; }
    .viewer-error-card p { margin: 0; color: var(--viewer-fg-3); font-size: 11px; line-height: 1.5; }
    .dicom-study-shell[data-viewer-state="error"] .dicom-viewport-stack { display: none; }
    .dicom-study-shell[data-viewer-state="error"] .viewer-error-card { display: flex; }
    .viewer-info { display: flex; justify-content: center; gap: 16px; min-height: 36px; padding: 0 16px; color: var(--viewer-fg-3); font: 10px ui-monospace, monospace; }
    .viewer-info-item { display: flex; gap: 5px; }
    .viewer-info-item strong { color: var(--viewer-fg-2); font-weight: 500; }

    .right-sidebar { display: flex; flex-direction: column; gap: 18px; }
    .panel-header { justify-content: space-between; }
    .tools-card { display: grid; gap: 1px; margin-top: 10px; overflow: hidden; border: 1px solid var(--viewer-border); border-radius: 9px; }
    .tool-row { display: grid; grid-template-columns: 30px 1fr auto; align-items: center; gap: 9px; padding: 11px; background: var(--viewer-surface); }
    .tool-icon { display: grid; place-items: center; width: 26px; height: 26px; color: var(--viewer-accent); background: var(--viewer-accent-soft); border-radius: 6px; font: 700 13px ui-monospace, monospace; }
    .tool-row strong { display: block; color: var(--viewer-fg-2); font-size: 11px; }
    .tool-row small { display: block; margin-top: 2px; color: var(--viewer-fg-3); font-size: 10px; }
    .tool-limit { color: var(--viewer-fg-3); font: 10px ui-monospace, monospace; }
    .workflow-list { display: grid; gap: 12px; margin: 10px 0 0; padding: 0; list-style: none; }
    .workflow-step { display: grid; grid-template-columns: 24px 1fr; gap: 9px; align-items: start; color: var(--viewer-fg-3); font-size: 11px; }
    .workflow-step > span { display: grid; place-items: center; width: 22px; height: 22px; border: 1px solid var(--viewer-border); border-radius: 50%; font: 10px ui-monospace, monospace; }
    .workflow-step.active { color: var(--viewer-fg-2); }
    .workflow-step.active > span { color: #111; background: var(--viewer-accent); border-color: var(--viewer-accent); }
    .workflow-step strong { display: block; color: inherit; font-size: 11px; }
    .workflow-step small { display: block; margin-top: 3px; color: var(--viewer-fg-3); font-size: 10px; line-height: 1.4; }
    .viewer-help { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--viewer-border); color: var(--viewer-fg-3); font-size: 11px; line-height: 1.5; }
    .viewer-help strong { color: var(--viewer-fg-2); }
    .bottom-meta { color: var(--viewer-fg-3); font: 10px ui-monospace, monospace; }
    .dicom-actions-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .dicom-study-shell .primary-action, .dicom-study-shell .secondary { display: inline-flex; align-items: center; padding: 7px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; }
    .dicom-study-shell .primary-action { color: #111; background: var(--viewer-accent); }
    .dicom-study-shell .secondary { color: var(--viewer-fg-2); background: transparent; border: 1px solid var(--viewer-border); }
    .dicom-study-shell a:focus { outline: 2px solid var(--viewer-accent); outline-offset: 2px; }
    .dicom-notice { width: min(86%, 360px); padding: 12px; color: var(--viewer-fg-2); background: var(--viewer-surface); border: 1px solid var(--viewer-border); border-radius: 8px; font-size: 11px; }
    .dicom-guidance, .dicom-readonly, #dicom-viewer-error { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

    @media (max-width: 1100px) {
        .dicom-workstation { grid-template-columns: 230px minmax(0, 1fr) 230px; }
        .dicom-topbar-status, .dicom-brand { min-width: 160px; }
    }
    @media (max-width: 820px) {
        body:has(#dicom-study) { overflow: auto; }
        body:has(#dicom-study) .shell { height: auto; padding: 6px; }
        .dicom-study-shell { height: auto; min-height: calc(100vh - 12px); grid-template-rows: 56px auto 48px; }
        .dicom-module-tabs, .dicom-topbar-status { display: none; }
        .dicom-workstation { grid-template-columns: 1fr; overflow: visible; }
        .center-viewer { min-height: 650px; order: 1; }
        .booth-left { order: 2; border-right: 0; border-top: 1px solid var(--viewer-border); }
        .right-sidebar { order: 3; border-left: 0; border-top: 1px solid var(--viewer-border); }
        .dicom-stage-frame { width: min(78%, 430px); height: min(74vh, 620px); }
        .dicom-bottom-bar { min-height: 48px; }
        .bottom-meta { display: none; }
    }
</style>

@php
    $studyReference = $display_reference ?? __('Identifier withheld');
    $studyFilename = $filename ?? ($studyReference.'.dcm');
    $studyFormat = $format ?? 'application/dicom';
    $studyRows = $rows ?? null;
    $studyColumns = $columns ?? null;
@endphp

<div class="dicom-study-shell" id="dicom-study"
     data-dicom-viewer
     data-image-url="{{ route('operator.study.dicom', $study_id) }}"
     data-unavailable-message="{{ __('DICOM study unavailable.') }}"
     data-parser-unavailable-message="{{ __('The DICOM viewer is unavailable.') }}"
     data-loading-message="{{ __('Loading DICOM…') }}"
     data-ready-message="{{ __('DICOM study ready. Automatic VOI is applied.') }}"
     data-display-error-message="{{ __('The DICOM study could not be displayed.') }}"
     data-viewer-timeout-ms="15000"
     data-viewer-state="loading"
     @if (isset($window_center) && $window_center !== null) data-window-center="{{ $window_center }}" @endif
     @if (isset($window_width) && $window_width !== null) data-window-width="{{ $window_width }}" @endif>
    <header class="dicom-topbar">
        <a class="dicom-brand" href="{{ route('operator.study.results') }}">
            <span class="dicom-brand-mark">MH</span>
            <span><span class="dicom-brand-title">MHCS Core</span><span class="dicom-brand-subtitle">{{ __('DICOM workstation') }}</span></span>
        </a>
        <div class="dicom-module-tabs" aria-label="{{ __('Study mode badges') }}">
            <span class="dicom-module-tab active">{{ __('DICOM viewer') }}</span>
        </div>
        <div class="dicom-topbar-status"><span class="dicom-live-dot"></span><span id="dicom-viewer-status" data-viewer-status role="status" aria-live="polite">{{ __('Loading DICOM…') }}</span></div>
    </header>

    <div class="dicom-workstation">
        <aside class="booth-left" aria-label="{{ __('Study context') }}">
            <div class="patient-card">
                <div class="patient-card-label">{{ __('Active study') }} <span class="patient-card-locked">{{ __('Read-only') }}</span></div>
                <h1 class="patient-card-name" id="dicom-study-title">{{ $studyReference }}</h1>
                <div class="patient-card-id">{{ $studyFilename }}</div>
            </div>
            <dl class="patient-details">
                <div class="detail-row"><dt>{{ __('Study') }}</dt><dd>{{ $studyReference }}</dd></div>
                <div class="detail-row"><dt>{{ __('Format') }}</dt><dd>{{ $studyFormat }}</dd></div>
                <div class="detail-row"><dt>{{ __('Image dimensions') }}</dt><dd>{{ $studyColumns !== null && $studyRows !== null ? $studyColumns.' × '.$studyRows : '—' }}</dd></div>
                <div class="detail-row"><dt>{{ __('Automatic VOI') }}</dt><dd>{{ __('enabled') }}</dd></div>
            </dl>
            <div class="session-meta">
                <div>{{ __('Read-only') }} <strong>{{ __('enabled') }}</strong></div>
                <div>{{ __('Current tab') }} <strong>{{ __('enabled') }}</strong></div>
                <div>{{ __('Zoom and pan only') }} <strong>2</strong></div>
            </div>
        </aside>

        <main class="center-viewer" aria-labelledby="dicom-study-title">
            <p class="dicom-readonly">{{ __('Read-only DICOM study. Automatic VOI is applied; use drag to pan and wheel to zoom.') }}</p>
            <div class="viewer-toolbar">
                <div class="viewer-toolbar-left"><span class="dicom-live-dot"></span><span class="viewer-state" data-viewer-status>{{ __('Loading DICOM…') }}</span></div>
                <div class="viewer-toolbar-right"><span class="viewer-meta">{{ $studyFormat }} · {{ __('Read-only') }}</span><span class="viewer-readonly">{{ __('Automatic VOI') }}</span></div>
            </div>
            <div class="viewer-stage">
                <noscript><p class="dicom-notice" role="status">{{ __('JavaScript is unavailable. Enable JavaScript to view this study in the current tab.') }}</p></noscript>
                <div class="dicom-stage-frame">
                    <div class="dicom-viewport-stack" aria-label="{{ __('DICOM viewport stack') }}">
                        <div class="dicom-viewport" data-testid="dicom-viewport" aria-label="{{ __('DICOM image viewport') }}"></div>
                    </div>
                    <div class="viewer-error-card" role="alert">
                        <div class="viewer-error-icon">!</div>
                        <strong>{{ __('The DICOM study could not be displayed.') }}</strong>
                        <p>{{ __('DICOM study unavailable.') }}</p>
                        <a href="{{ route('operator.study.download', $study_id) }}" class="primary-action">{{ __('Download DICOM') }}</a>
                    </div>
                </div>
                <p id="dicom-viewer-error" role="alert" hidden></p>
            </div>
            <div class="viewer-info" aria-label="{{ __('Zoom and pan only') }}">
                <span class="viewer-info-item">VOI <strong>{{ __('Automatic VOI') }}</strong></span>
                <span class="viewer-info-item">{{ __('Zoom') }} <strong>10×</strong></span>
                <span class="viewer-info-item">{{ __('Pan') }} <strong>{{ __('Drag to pan') }}</strong></span>
            </div>
        </main>

        <aside class="right-sidebar" aria-label="{{ __('Viewer tools') }}">
            <section>
                <div class="panel-header">{{ __('Viewer tools') }} <span class="viewer-readonly">{{ __('Read-only') }}</span></div>
                <div class="tools-card">
                    <div class="tool-row"><span class="tool-icon">⌕</span><span><strong>{{ __('Zoom') }}</strong><small>{{ __('Wheel to zoom') }}</small></span><span class="tool-limit">10×</span></div>
                    <div class="tool-row"><span class="tool-icon">✥</span><span><strong>{{ __('Pan') }}</strong><small>{{ __('Drag to pan') }}</small></span><span class="tool-limit">↔</span></div>
                </div>
            </section>
            <section>
                <div class="panel-header">{{ __('Workflow') }}</div>
                <ol class="workflow-list">
                    <li class="workflow-step active"><span>1</span><div><strong>{{ __('Study received') }}</strong><small>{{ __('Automatic VOI') }}</small></div></li>
                    <li class="workflow-step"><span>2</span><div><strong>{{ __('Current tab') }}</strong><small>{{ __('Read-only') }}</small></div></li>
                    <li class="workflow-step"><span>3</span><div><strong>{{ __('Download DICOM') }}</strong><small>{{ __('Back to DICOM results') }}</small></div></li>
                </ol>
            </section>
            <p class="viewer-help"><strong>{{ __('Zoom and pan only') }}</strong><br>{{ __('Pointer drag pans the image. Use the mouse wheel to zoom.') }}</p>
        </aside>
    </div>

    <footer class="dicom-bottom-bar">
        <span class="bottom-meta">{{ $studyReference }} · {{ __('Read-only') }}</span>
        <div class="dicom-actions-group">
            <a href="{{ route('operator.study.download', $study_id) }}" class="primary-action">{{ __('Download DICOM') }}</a>
            <a href="{{ route('operator.study.results') }}" class="secondary">{{ __('Back to DICOM results') }}</a>
        </div>
    </footer>
</div>
@vite('resources/js/app.js')
@endsection
