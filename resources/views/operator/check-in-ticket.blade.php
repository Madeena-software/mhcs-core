@extends('operator.layout')

@section('title', 'Verified check-in and paper ticket')

@section('content')
<section aria-labelledby="check-in-ticket-title">
    <h1 id="check-in-ticket-title">Verified check-in and paper ticket</h1>
    @if ($ticket)
        <section class="card" aria-live="polite">
            <h2>Ticket already issued</h2>
            <p>Ticket <strong>{{ $ticket['ticket_number'] }}</strong> is already recorded for this visit.</p>
            <a href="{{ route('operator.paper-ticket.show', $ticket['ticket_id']) }}">Open ticket result</a>
        </section>
    @else
        <section class="card">
            <p>The system will generate the paper ticket number automatically. The server will recheck the matched identity case, confirmed Member consent, booking status, site, and shift assignment before committing check-in.</p>
            <p><strong>Site:</strong> {{ $case['site_name'] }}</p>
            <p><strong>Shift:</strong> <time datetime="{{ $case['schedule_starts_at'] }}">{{ $case['schedule_starts_at'] }}</time> – <time datetime="{{ $case['schedule_ends_at'] }}">{{ $case['schedule_ends_at'] }}</time></p>
            <form method="POST" action="{{ route('operator.check-in.store', $case['case_id']) }}">
                @csrf
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <div class="actions"><button type="submit">Check in and issue ticket</button></div>
            </form>
        </section>
    @endif
</section>
@endsection
