@extends('member.layout')

@section('title', __('Log in'))

@section('content')
<section class="card narrow" aria-labelledby="login-title">
    <h1 id="login-title">{{ __('Log in to MHCS Core') }}</h1>
    <p class="muted">{{ __('Use your email or NIK and password.') }}</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="identifier">{{ __('Email or NIK') }}</label>
        <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="username" required aria-describedby="identifier-error">
        @error('identifier') <p id="identifier-error" class="error">{{ $message }}</p> @enderror

        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error">
        @error('password') <p id="password-error" class="error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit">{{ __('Log in') }}</button>
        </div>
    </form>
</section>
@endsection
