@extends('operator.layout')

@section('title', __('Register Member'))

@section('content')
<section aria-labelledby="register-member-title">
    <p class="eyebrow">{{ __('Front desk') }}</p>
    <h1 id="register-member-title">{{ __('Register Member') }}</h1>
    <p class="muted">{{ __('Form ringkas walk-in. Identitas lengkap tetap berstatus pending verification dan login tidak diaktifkan.') }}</p>
    <section class="card">
        <form method="POST" action="{{ route('operator.members.store') }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ $operationId }}">
            @if ($scheduleId !== '')
                <input type="hidden" name="schedule_id" value="{{ $scheduleId }}">
                <input type="hidden" name="booking_operation_id" value="{{ $bookingOperationId }}">
            @endif
            <label for="member-name">{{ __('Nama') }}</label>
            <input id="member-name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name">
            <label for="member-email">{{ __('Email') }} {{ __('(email atau telepon wajib diisi)') }}</label>
            <input id="member-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email">
            <label for="member-phone">{{ __('Telepon') }}</label>
            <input id="member-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="64" autocomplete="tel">
            <div class="actions">
                <button type="submit">{{ $scheduleId !== '' ? __('Register dan Tambah ke Jadwal') : __('Register Member') }}</button>
                @if ($scheduleId !== '')<a href="{{ route('operator.schedules.show', $scheduleId) }}">{{ __('Batal') }}</a>@endif
            </div>
        </form>
    </section>
</section>
@endsection
