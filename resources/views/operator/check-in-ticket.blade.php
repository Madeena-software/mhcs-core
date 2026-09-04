@extends('operator.layout')

@section('title', __('Verified check-in and paper ticket'))

@section('content')
<section aria-labelledby="check-in-ticket-title">
    <h1 id="check-in-ticket-title">{{ __('Verified check-in and paper ticket') }}</h1>
    @if ($ticket)
        <section class="card" aria-live="polite">
            <h2>{{ __('Ticket already issued') }}</h2>
            <p>{{ __('Ticket :ticket is already recorded for this visit.', ['ticket' => $ticket['ticket_number']]) }}</p>
            <a href="{{ route('operator.paper-ticket.show', $ticket['ticket_id']) }}">{{ __('Open ticket result') }}</a>
        </section>
    @else
        <section class="card">
            <p>{{ __('The system will generate the paper ticket number automatically. The server will recheck the matched identity case, confirmed Member consent, booking status, site, and shift assignment before committing check-in.') }}</p>
            <p><strong>{{ __('Site') }}:</strong> {{ $case['site_name'] }}</p>
            <p><strong>{{ __('Shift') }}:</strong> <time datetime="{{ $case['schedule_starts_at'] }}">{{ $case['schedule_starts_at'] }}</time> – <time datetime="{{ $case['schedule_ends_at'] }}">{{ $case['schedule_ends_at'] }}</time></p>

            <form method="POST" action="{{ route('operator.check-in.store', $case['case_id']) }}">
                @csrf
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">

                <div style="margin: 20px 0; padding: 15px; background: #f7fafc; border-radius: 6px; border: 1px solid #e2e8f0">
                    <p style="margin-top: 0"><strong>{{ __('Clinical Pathway Choice') }}:</strong></p>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap">
                        <button type="submit" name="bypass_basic_examination" value="0">{{ __('Standard: Check in to Basic Examination') }}</button>
                        <button type="submit" name="bypass_basic_examination" value="1" class="btn-secondary">{{ __('Bypass: Skip to Radiography Readiness') }}</button>
                    </div>
                    <p class="muted" style="margin-bottom: 0; margin-top: 10px; font-size: 0.9em">
                        {{ __('Skipping basic examination will mark stage as skipped, record audit timestamp, create zero basic examination earnings, and place ticket directly into the X-ray readiness queue.') }}
                    </p>
                </div>
            </form>
        </section>
    @endif
</section>
@endsection
