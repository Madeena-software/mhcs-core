<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Operator workstation') — MHCS Core</title>
    <style>
        :root { color-scheme: dark; font-family: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif; color: #f4f7fb; background: #10181f; }
        body { margin: 0; background: #10181f; }
        a { color: #8fdfff; }
        .shell { max-width: 1180px; margin: 0 auto; padding: 24px 18px 56px; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 34px; }
        .nav-links { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .card { background: #17232c; border: 1px solid #435461; border-radius: 12px; padding: 22px; box-shadow: 0 8px 24px rgba(0, 0, 0, .18); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
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
    <nav class="nav" aria-label="Operator workstation navigation">
        <a href="{{ route('operator.dashboard') }}"><strong>MHCS Core / Operator</strong></a>
        <div class="nav-links">
            <a href="{{ route('operator.dashboard') }}">Workstation</a>
            <a href="{{ route('operator.site') }}">Active site</a>
            <a href="{{ route('operator.eligible-shifts') }}">Assigned shifts</a>
            <a href="{{ route('operator.verification-worklist') }}">Verification worklist</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="secondary">Sign out</button>
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
