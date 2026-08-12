<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Member')) — {{ __('MHCS Core') }}</title>
    <style>
        :root { color-scheme: light; font-family: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif; color: #1c1b1b; background: #fcf9f8; }
        body { margin: 0; background: #fcf9f8; }
        a { color: #005c9b; }
        .shell { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 40px; }
        .nav-links { display: flex; align-items: center; gap: 16px; }
        .card { background: #fff; border: 1px solid #c0c7d3; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(19, 117, 192, .08); }
        .narrow { max-width: 520px; margin: 48px auto; }
        h1 { font-size: 32px; line-height: 40px; margin: 0 0 12px; }
        h2 { font-size: 24px; line-height: 32px; margin: 0 0 16px; }
        p { line-height: 1.5; }
        label { display: block; font-weight: 600; margin: 16px 0 6px; }
        input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #717882; border-radius: 8px; padding: 12px; font: inherit; background: #fff; }
        input:focus, textarea:focus, button:focus, a:focus { outline: 3px solid #1adcfd; outline-offset: 2px; }
        textarea { min-height: 110px; resize: vertical; }
        button { border: 0; border-radius: 8px; padding: 12px 18px; color: #fff; background: #1375c0; font: inherit; font-weight: 600; cursor: pointer; }
        .secondary { color: #005c9b; background: #d1e4ff; }
        .actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .error { color: #93000a; margin: 6px 0; }
        .notice { padding: 12px 16px; border-radius: 8px; background: #d1e4ff; margin-bottom: 20px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 24px 0; }
        .summary div { border: 1px solid #c0c7d3; border-radius: 8px; padding: 16px; background: #fff; }
        dt { color: #404751; font-size: 14px; }
        dd { margin: 6px 0 0; font-weight: 600; }
        .muted { color: #404751; }
        @media (max-width: 640px) { .nav { align-items: flex-start; flex-direction: column; } .narrow { margin: 24px auto; } h1 { font-size: 28px; line-height: 36px; } }
    </style>
</head>
<body>
<div class="shell">
    @auth
        <nav class="nav" aria-label="{{ __('Member navigation') }}">
            <a href="{{ route('member.dashboard') }}"><strong>{{ __('MHCS Core') }}</strong></a>
            <div class="nav-links">
                @if (! auth()->user()->must_change_password)
                    <a href="{{ route('member.dashboard') }}">{{ __('Dashboard Member') }}</a>
                    <a href="{{ route('member.services') }}">{{ __('Radiography Sessions') }}</a>
                    <a href="{{ route('member.bookings') }}">{{ __('My Sessions') }}</a>
                    <a href="{{ route('member.profile') }}">{{ __('Profile') }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="secondary">{{ __('Log out') }}</button>
                </form>
            </div>
        </nav>
    @endauth

    @if (session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    @yield('content')
</div>
</body>
</html>
