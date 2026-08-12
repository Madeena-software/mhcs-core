@extends('member.layout')

@section('title', __('Update password'))

@section('content')
<section class="card narrow" aria-labelledby="password-title">
    <h1 id="password-title">{{ __('Update your password') }}</h1>
    <p>{{ __('For account security, create a new password before continuing.') }}</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.change-required.update') }}">
        @csrf
        <label for="current_password">{{ __('Current password') }}</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required aria-describedby="current-password-error">
        @error('current_password') <p id="current-password-error" class="error">{{ $message }}</p> @enderror

        <label for="password">{{ __('New password') }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-error">
        @error('password') <p id="password-error" class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">{{ __('Confirm new password') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required aria-describedby="password-confirmation-error">
        @error('password_confirmation') <p id="password-confirmation-error" class="error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit">{{ __('Save password') }}</button>
        </div>
    </form>
</section>
@endsection
