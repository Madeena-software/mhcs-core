@extends('operator.layout')

@section('title', __('Confirm physical arrival'))

@section('content')
<section aria-labelledby="arrival-confirmation-title">
    <h1 id="arrival-confirmation-title">{{ __('Confirm physical arrival') }}</h1>
    <p class="muted">{{ __('Review the safe operational summary before recording this arrival.') }}</p>

    <section class="card">
        <dl>
            <dt>{{ __('Member') }}</dt>
            <dd>{{ $summary['member_name'] }}</dd>
            <dt>{{ __('Medical record') }}</dt>
            <dd>{{ $summary['medical_record_number'] }}</dd>
            <dt>{{ __('Service') }}</dt>
            <dd>{{ $summary['service_name'] }} ({{ $summary['service_code'] }})</dd>
            <dt>{{ __('Occurrence time') }}</dt>
            <dd>{{ $occurrence_at }}</dd>
        </dl>

        <div class="actions">
            <form method="POST" action="{{ route('operator.arrivals.store') }}">
                @csrf
                <input type="hidden" name="confirmation_token" value="{{ $confirmation_token }}">
                <button type="submit">{{ __('Confirm and record arrival') }}</button>
            </form>
            <form method="POST" action="{{ route('operator.arrivals.cancel') }}">
                @csrf
                <input type="hidden" name="confirmation_token" value="{{ $confirmation_token }}">
                <button type="submit" class="secondary">{{ __('Cancel') }}</button>
            </form>
        </div>
    </section>
</section>
@endsection
