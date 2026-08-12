@extends('member.layout')

@section('title', __('Profile'))

@section('content')
<section class="card" aria-labelledby="profile-title">
    <h1 id="profile-title">{{ __('Complete your profile') }}</h1>
    <p>{{ __('This information helps us contact you when needed. Email and phone number are optional.') }}</p>
    <p><strong>{{ __('Profile completeness') }}: {{ $completionPercentage }}%</strong></p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('member.profile.update') }}">
        @csrf
        @method('PATCH')

        <label for="email">{{ __('Email (optional)') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" aria-describedby="email-error">
        @error('email') <p id="email-error" class="error">{{ $message }}</p> @enderror

        <label for="phone">{{ __('Phone number (optional)') }}</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone) }}" autocomplete="tel" aria-describedby="phone-error">
        @error('phone') <p id="phone-error" class="error">{{ $message }}</p> @enderror

        <label for="current_address">{{ __('Current address') }}</label>
        <textarea id="current_address" name="current_address" aria-describedby="current-address-error">{{ old('current_address', $member->current_address) }}</textarea>
        @error('current_address') <p id="current-address-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_name">{{ __('Emergency contact name') }}</label>
        <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name', $member->emergency_contact_name) }}" aria-describedby="emergency-name-error">
        @error('emergency_contact_name') <p id="emergency-name-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_relationship">{{ __('Relationship to emergency contact') }}</label>
        <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text" value="{{ old('emergency_contact_relationship', $member->emergency_contact_relationship) }}" aria-describedby="emergency-relationship-error">
        @error('emergency_contact_relationship') <p id="emergency-relationship-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_phone">{{ __('Emergency contact phone number') }}</label>
        <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $member->emergency_contact_phone) }}" aria-describedby="emergency-phone-error">
        @error('emergency_contact_phone') <p id="emergency-phone-error" class="error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit">{{ __('Save profile') }}</button>
        </div>
    </form>
</section>
@endsection
