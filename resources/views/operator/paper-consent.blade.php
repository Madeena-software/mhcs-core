@extends('operator.layout')

@section('title', __('Paper consent'))

@section('content')
<section aria-labelledby="consent-title">
    <h1 id="consent-title">{{ __('Paper consent confirmation') }}</h1>
    <p class="muted">{{ __('Confirm the Member\'s signed offline Informed Consent form. This action does not check in the booking or issue a ticket.') }}</p>

    <section class="card">
        <h2>{{ __('Visit') }}</h2>
        <p><strong>{{ __('Member') }}:</strong> {{ $summary['member_name'] }}</p>
        <p><strong>{{ __('Medical record') }}:</strong> {{ $summary['medical_record_number'] }}</p>
        <p><strong>{{ __('Booking') }}:</strong> <code>{{ $summary['booking_id'] }}</code></p>
        <p><strong>{{ __('Service') }}:</strong> {{ $summary['service_name'] }}</p>
        <p><strong>{{ __('Booking status') }}:</strong> {{ __($summary['booking_status']) }}</p>
    </section>

    @if ($consent)
        <section class="card" aria-live="polite">
            <h2>{{ __('Confirmed') }}</h2>
            <p><strong>{{ __('Form') }}:</strong> {{ $consent['form_name'] }} / {{ $consent['form_version'] }}</p>
            <p><strong>{{ __('Signer') }}:</strong> {{ __('Member') }}</p>
            <p><strong>{{ __('Signed date') }}:</strong> {{ $consent['signed_at'] }}</p>
            <p><strong>{{ __('Private scan') }}:</strong> {{ $consent['has_private_scan'] ? __('Stored privately') : __('Not supplied') }}</p>
            <p><a href="{{ route('operator.check-in.show', $case['case_id']) }}">{{ __('Proceed to verified check-in and paper ticket issue') }}</a></p>
        </section>
    @else
        <section class="card">
            <h2>{{ __('Confirm the approved form') }}</h2>
            <form method="POST" action="{{ route('operator.paper-consent.store', $case['case_id']) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_name" value="Informed Consent">
                <input type="hidden" name="form_version" value="V1">
                <input type="hidden" name="signer_type" value="member">
                <input type="hidden" name="signature_confirmed" value="1">
                <label for="signed-at">{{ __('Actual signing date') }}</label>
                <input id="signed-at" name="signed_at" type="date" value="{{ old('signed_at', now()->format('Y-m-d')) }}" required>
                <p class="muted">{{ __('Choose the date shown on the signed paper form.') }}</p>
                <label for="scan">{{ __('Required signed-paper photo or scan') }}</label>
                <input id="scan" name="scan" type="file" accept="image/jpeg,image/png,application/pdf" required>
                <p class="muted">{{ __('JPEG, PNG, or PDF only, up to 100 MB. The file is encrypted and is not retrievable from this portal.') }}</p>
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit">{{ __('Confirm paper consent') }}</button>
            </form>
        </section>
    @endif
</section>
@endsection
