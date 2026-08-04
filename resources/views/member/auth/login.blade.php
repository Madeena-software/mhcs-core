@extends('member.layout')

@section('title', 'Masuk')

@section('content')
<section class="card narrow" aria-labelledby="login-title">
    <h1 id="login-title">Masuk ke MHCS Core</h1>
    <p class="muted">Gunakan email atau NIK dan kata sandi Anda.</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="identifier">Email atau NIK</label>
        <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" autocomplete="username" required aria-describedby="identifier-error">
        @error('identifier') <p id="identifier-error" class="error">{{ $message }}</p> @enderror

        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error">
        @error('password') <p id="password-error" class="error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit">Masuk</button>
        </div>
    </form>
</section>
@endsection
