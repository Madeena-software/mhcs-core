@extends('operator.layout')

@section('title', __('Paper ticket result'))

@section('content')
<section aria-labelledby="paper-ticket-result-title">
    <h1 id="paper-ticket-result-title">{{ __('Paper ticket issued') }}</h1>
    <section class="card" aria-live="polite">
        <p><strong>{{ __('Ticket number') }}:</strong> {{ $ticket['ticket_number'] }}</p>
        <p><strong>{{ __('Site') }}:</strong> {{ $ticket['site_name'] }}</p>
        <p><strong>{{ __('Shift') }}:</strong> <time datetime="{{ $ticket['schedule_starts_at'] }}">{{ $ticket['schedule_starts_at'] }}</time> – <time datetime="{{ $ticket['schedule_ends_at'] }}">{{ $ticket['schedule_ends_at'] }}</time></p>
        <p><strong>{{ __('Recorded at') }}:</strong> {{ $ticket['issued_at'] }}</p>
        <div class="actions">
            <a href="{{ route('operator.paper-ticket.print', $ticket['ticket_id']) }}" target="_blank" rel="noopener">{{ __('Open print view') }}</a>
            <form method="POST" action="{{ route('operator.paper-ticket.reprint', $ticket['ticket_id']) }}" target="_blank">
                @csrf
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit" class="secondary">{{ __('Request reprint') }}</button>
            </form>
        </div>
    </section>
</section>
@endsection
