@extends('operator.layout')

@section('title', 'Confirm physical arrival')

@section('content')
<section aria-labelledby="arrival-confirmation-title">
    <h1 id="arrival-confirmation-title">Confirm physical arrival</h1>
    <p class="muted">Review the safe operational summary before recording this arrival.</p>

    <section class="card">
        <dl>
            <dt>Member</dt>
            <dd>{{ $summary['member_name'] }}</dd>
            <dt>Medical record</dt>
            <dd>{{ $summary['medical_record_number'] }}</dd>
            <dt>Service</dt>
            <dd>{{ $summary['service_name'] }} ({{ $summary['service_code'] }})</dd>
            <dt>Occurrence time</dt>
            <dd>{{ $occurrence_at }}</dd>
        </dl>

        <div class="actions">
            <form method="POST" action="{{ route('operator.arrivals.store') }}">
                @csrf
                <input type="hidden" name="confirmation_token" value="{{ $confirmation_token }}">
                <button type="submit">Confirm and record arrival</button>
            </form>
            <form method="POST" action="{{ route('operator.arrivals.cancel') }}">
                @csrf
                <input type="hidden" name="confirmation_token" value="{{ $confirmation_token }}">
                <button type="submit" class="secondary">Cancel</button>
            </form>
        </div>
    </section>
</section>
@endsection
