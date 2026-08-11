@extends('operator.layout')

@section('title', 'Paper consent')

@section('content')
<section aria-labelledby="consent-title">
    <h1 id="consent-title">Paper consent confirmation</h1>
    <p class="muted">Confirm the Member's signed offline Informed Consent form. This action does not check in the booking or issue a ticket.</p>

    <section class="card">
        <h2>Visit</h2>
        <p><strong>Member:</strong> {{ $summary['member_name'] }}</p>
        <p><strong>Medical record:</strong> {{ $summary['medical_record_number'] }}</p>
        <p><strong>Booking:</strong> <code>{{ $summary['booking_id'] }}</code></p>
        <p><strong>Service:</strong> {{ $summary['service_name'] }}</p>
        <p><strong>Booking status:</strong> {{ $summary['booking_status'] }}</p>
    </section>

    @if ($consent)
        <section class="card" aria-live="polite">
            <h2>Confirmed</h2>
            <p><strong>Form:</strong> {{ $consent['form_name'] }} / {{ $consent['form_version'] }}</p>
            <p><strong>Signer:</strong> Member</p>
            <p><strong>Signed at:</strong> {{ $consent['signed_at'] }}</p>
            <p><strong>Private scan:</strong> {{ $consent['has_private_scan'] ? 'Stored privately' : 'Not supplied' }}</p>
            <p><a href="{{ route('operator.check-in.show', $case['case_id']) }}">Proceed to verified check-in and paper ticket issue</a></p>
        </section>
    @else
        <section class="card">
            <h2>Confirm the approved form</h2>
            <form method="POST" action="{{ route('operator.paper-consent.store', $case['case_id']) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_name" value="Informed Consent">
                <input type="hidden" name="form_version" value="V1">
                <input type="hidden" name="signer_type" value="member">
                <input type="hidden" name="signature_confirmed" value="1">
                <label for="signed-at">Actual signing time</label>
                <input id="signed-at" name="signed_at" type="text" placeholder="2040-01-10T10:20:00+07:00" required>
                <p class="muted">Use the actual time from the signed paper form and include its time zone in the request.</p>
                <label for="scan">Required signed-paper photo or scan</label>
                <input id="scan" name="scan" type="file" accept="image/jpeg,image/png,application/pdf" required>
                <p class="muted">JPEG, PNG, or PDF only, up to 10 MiB. The file is encrypted and is not retrievable from this portal.</p>
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit">Confirm paper consent</button>
            </form>
        </section>
    @endif
</section>
@endsection
