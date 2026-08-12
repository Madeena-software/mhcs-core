@extends('member.layout')

@section('title', $offering->name)

@section('content')
<section aria-labelledby="service-title">
    <h1 id="service-title">{{ $offering->name }}</h1>
    <dl class="summary">
        <div><dt>{{ __('Service code') }}</dt><dd>{{ $offering->code }}</dd></div>
        <div><dt>{{ __('Price') }}</dt><dd>{{ $offering->pointPrice() }} Madeena Points</dd></div>
        <div><dt>{{ __('Automatic interpretation assistance') }}</dt><dd>{{ $offering->includes_ai ? __('Included') : __('Not included') }}</dd></div>
        <div><dt>{{ __('Doctor review') }}</dt><dd>{{ $offering->includes_doctor ? __('Included') : __('Not included') }}</dd></div>
    </dl>

    <h2>{{ __('Choose Schedule') }}</h2>
    @if ($schedules->isEmpty())
        <div class="card"><p class="muted">{{ __('No schedules are open for this service yet.') }}</p></div>
    @else
        @foreach ($schedules as $schedule)
            <article class="card" style="margin: 12px 0">
                <h3>{{ $schedule->site_name_snapshot ?? $schedule->site?->display_name }}</h3>
                <p>{{ $schedule->starts_at->setTimezone($schedule->site?->timezone ?? 'UTC')->translatedFormat('l, j F Y') }} {{ __('at') }} {{ $schedule->starts_at->setTimezone($schedule->site?->timezone ?? 'UTC')->format('H.i') }}–{{ $schedule->ends_at->setTimezone($schedule->site?->timezone ?? 'UTC')->format('H.i') }}</p>
                <p class="muted">{{ __('Remaining capacity') }}: {{ $schedule->remaining_capacity }} {{ __('of') }} {{ $schedule->quota }}</p>
                @if ($schedule->remaining_capacity > 0)
                    <form method="POST" action="{{ route('member.bookings.store') }}">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $schedule->getKey() }}">
                        <input type="hidden" name="point_cost" value="{{ $offering->pointPrice() }}">
                        <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <label><input type="checkbox" name="confirmation" value="1" required> {{ __('I understand the price and want to use personal Madeena Points.') }}</label>
                        <button type="submit">{{ __('Confirm Schedule') }}</button>
                    </form>
                @endif
            </article>
        @endforeach
    @endif
</section>
@endsection
