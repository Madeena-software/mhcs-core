<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Operator workstation sign in — MHCS Core') }}</title>
    <style>
        :root { color-scheme: dark; font-family: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif; color: #f3f0ef; background: #1c1b1b; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: radial-gradient(circle at 50% 0, #263b4a 0, #1c1b1b 52%); }
        main { width: min(100% - 32px, 520px); margin: 0 auto; padding: 56px 0; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .mark { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 8px; color: #08202b; background: #1adcfd; font-weight: 800; letter-spacing: .04em; }
        .brand strong { display: block; font-size: 16px; }
        .brand span { color: #c0c7d3; font-size: 12px; }
        .card { padding: 28px; background: #242323; border: 1px solid #404751; border-radius: 12px; box-shadow: 0 16px 40px rgba(0, 0, 0, .3); }
        .eyebrow { color: #1adcfd; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 8px 0 12px; font-size: 30px; line-height: 1.2; }
        p { line-height: 1.5; }
        .muted { color: #c0c7d3; }
        label { display: block; margin: 18px 0 6px; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid #717882; border-radius: 6px; color: #f3f0ef; background: #191818; font: inherit; }
        input:focus, button:focus { outline: 3px solid #1adcfd; outline-offset: 2px; }
        button { width: 100%; margin-top: 24px; padding: 12px 16px; border: 0; border-radius: 6px; color: #08202b; background: #1adcfd; font: inherit; font-weight: 800; cursor: pointer; }
        .error { color: #ffb4ab; }
        .note { margin-top: 22px; padding-top: 16px; border-top: 1px solid #404751; color: #c0c7d3; font-size: 13px; }
    </style>
</head>
<body>
<main>
    <header class="brand">
        <div class="mark" aria-hidden="true">MH</div>
        <div><strong>{{ __('MHCS Core') }}</strong><span>{{ __('Clinic operations') }}</span></div>
    </header>

    <section class="card" aria-labelledby="operator-login-title">
        <p class="eyebrow">{{ __('Staff access') }}</p>
        <h1 id="operator-login-title">{{ __('Operator workstation') }}</h1>
        <p class="muted">{{ __('Sign in to manage your assigned site and clinic-day workflow.') }}</p>

        @if ($errors->any())
            <p class="error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('operator.login.store') }}">
            @csrf
            <label for="identifier">{{ __('Email or NIK') }}</label>
            <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="username" required>

            <label for="password">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">{{ __('Sign in') }}</button>
        </form>

        <p class="note">{{ __('Use your authorized MHCS Operator credentials. Member and administrator access use their existing entry points.') }}</p>
    </section>
</main>
</body>
</html>
