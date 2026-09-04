@extends('operator.layout')

@section('title', __('On-the-Spot Member Registration'))

@section('content')
<section aria-labelledby="register-title">
    <h1 id="register-title">{{ __('On-the-Spot Member Registration') }}</h1>
    <p class="muted">{{ __('Register a member directly on the field and admit them immediately to active shift') }} <strong>{{ $schedule->display_reference }}</strong>.</p>

    <section class="card">
        <form method="POST" action="{{ route('operator.shifts.members.register.store', $schedule->id) }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <label for="name">{{ __('Full Name (as on ID card / KTP)') }} *</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255">

            <label for="nik">{{ __('16-digit NIK') }} *</label>
            <input id="nik" name="nik" type="text" pattern="\d{16}" minlength="16" maxlength="16" value="{{ old('nik') }}" placeholder="16 numeric digits" required>
            <p class="muted">{{ __('If this NIK was previously registered, the system will resolve the existing member record without creating a duplicate.') }}</p>

            <label for="administrative-gender">{{ __('Gender') }} *</label>
            <select id="administrative-gender" name="administrative_gender" required>
                <option value="">{{ __('— Select Gender —') }}</option>
                <option value="male" {{ old('administrative_gender') === 'male' ? 'selected' : '' }}>{{ __('Male (Laki-laki)') }}</option>
                <option value="female" {{ old('administrative_gender') === 'female' ? 'selected' : '' }}>{{ __('Female (Perempuan)') }}</option>
            </select>

            <label for="birth-date">{{ __('Birth Date') }} *</label>
            <input id="birth-date" name="birth_date" type="date" value="{{ old('birth_date') }}" required>

            <label for="phone">{{ __('Phone Number') }} *</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="08..." required maxlength="32">

            <label for="affiliation">{{ __('Affiliation / Institution / Organization Name') }} *</label>
            <input id="affiliation" name="affiliation" type="text" value="{{ old('affiliation') }}" placeholder="e.g. PT Example Nusantara / Dinas Kesehatan" required maxlength="255">

            <label for="office-location">{{ __('Office Location of Affiliation') }} *</label>
            <input id="office-location" name="office_location" type="text" value="{{ old('office_location') }}" placeholder="e.g. Gedung A Lantai 3, Jakarta Selatan" required maxlength="255">

            <div class="actions" style="margin-top: 25px">
                <button type="submit">{{ __('Register and Admit to Shift') }}</button>
                <a href="{{ route('operator.shifts.members.add', $schedule->id) }}" class="btn-secondary" style="margin-left: 10px">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</section>
@endsection
