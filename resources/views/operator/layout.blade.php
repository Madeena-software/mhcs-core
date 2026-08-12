<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Operator workstation')) — {{ __('MHCS Core') }}</title>
    <style>
        :root { color-scheme: dark; font-family: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif; color: #f4f7fb; background: #10181f; }
        body { margin: 0; background: #10181f; }
        a { color: #8fdfff; }
        .shell { max-width: 1180px; margin: 0 auto; padding: 24px 18px 56px; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 34px; }
        .nav-links { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .card { background: #17232c; border: 1px solid #435461; border-radius: 12px; padding: 22px; box-shadow: 0 8px 24px rgba(0, 0, 0, .18); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
        .workflow { display: grid; gap: 12px; margin: 24px 0 0; padding: 0; list-style: none; }
        .workflow-item { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 16px; padding: 16px 18px; background: #17232c; border: 1px solid #435461; border-left: 4px solid #71808b; border-radius: 10px; }
        .workflow-item.primary { border-left-color: #1adcfd; box-shadow: 0 0 0 1px rgba(26, 220, 253, .18); }
        .step-number { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; color: #08202b; background: #8fdfff; font-weight: 800; }
        .workflow-item h2 { margin-bottom: 4px; font-size: 18px; }
        .workflow-item p { margin: 0; }
        .queue-count { color: #b8f1c8; font-weight: 700; }
        .eyebrow { color: #1adcfd; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .primary-action { display: inline-block; padding: 12px 16px; border-radius: 6px; color: #08202b; background: #8fdfff; font-weight: 800; text-decoration: none; }
        .primary-action:focus { outline: 3px solid #1adcfd; outline-offset: 2px; }
        @media (max-width: 700px) { .workflow-item { grid-template-columns: auto 1fr; } .workflow-item > :last-child { grid-column: 2; } }
        h1 { font-size: 32px; line-height: 1.2; margin: 0 0 10px; }
        h2 { font-size: 22px; line-height: 1.3; margin: 0 0 14px; }
        p { line-height: 1.5; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #71808b; border-radius: 6px; padding: 11px; font: inherit; color: #f4f7fb; background: #10181f; }
        input:focus, select:focus, button:focus, a:focus { outline: 3px solid #1adcfd; outline-offset: 2px; }
        button { border: 0; border-radius: 6px; padding: 11px 16px; color: #08202b; background: #8fdfff; font: inherit; font-weight: 700; cursor: pointer; }
        .secondary { color: #d8edf5; background: #344651; }
        .actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .error { color: #ffb4ab; margin: 6px 0; }
        .notice { padding: 12px 16px; border-radius: 8px; background: #214858; margin-bottom: 20px; }
        .muted { color: #c4d0d7; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 12px 10px; border-bottom: 1px solid #435461; }
        th { color: #c4d0d7; font-size: 14px; }
        .status { color: #b8f1c8; font-weight: 700; }
        @media (max-width: 700px) { .nav { align-items: flex-start; flex-direction: column; } h1 { font-size: 28px; } }
    </style>
</head>
<body>
<div class="shell">
    <nav class="nav" aria-label="{{ __('Operator workstation navigation') }}">
        <a href="{{ route('operator.dashboard') }}"><strong>{{ __('MHCS Core / Operator') }}</strong></a>
        <div class="nav-links">
            <a href="{{ route('operator.dashboard') }}">{{ __('Workstation') }}</a>
            <a href="{{ route('operator.site') }}">{{ __('Active site') }}</a>
            <a href="{{ route('operator.eligible-shifts') }}">{{ __('Assigned shifts') }}</a>
            <a href="{{ route('operator.verification-worklist') }}">{{ __('Verification worklist') }}</a>
            <a href="{{ route('operator.basic-examination-worklist') }}">{{ __('Basic-examination worklist') }}</a>
            <a href="{{ route('operator.xray-readiness-worklist') }}">{{ __('X-ray readiness') }}</a>
            <a href="{{ route('operator.study.results') }}">{{ __('DICOM results') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="secondary">{{ __('Sign out') }}</button>
            </form>
        </div>
    </nav>

    @if (session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="card" role="alert">
            @foreach ($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
        </div>
    @endif

    @yield('content')
</div>
</body>
</html>
