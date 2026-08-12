@extends('member.layout')

@section('title', __('Radiography Session Schedule'))

@section('content')
<section aria-labelledby="schedules-title">
    <h1 id="schedules-title">{{ __('Radiography Session Schedule') }}</h1>
    @if ($schedules->isEmpty())
        <div class="card"><p class="muted">{{ __('No Radiography Sessions have been scheduled yet. Please choose a service first.') }}</p><a href="{{ route('member.services') }}">{{ __('Choose service') }}</a></div>
    @else
        @foreach ($schedules as $schedule)
            <article class="card" style="margin: 12px 0">
                <h2>{{ $schedule->service->name }}</h2>
                <p>{{ $schedule->site->display_name }} · {{ $schedule->starts_at->setTimezone($schedule->site->timezone)->translatedFormat('l, j F Y') }} {{ __('at') }} {{ $schedule->starts_at->setTimezone($schedule->site->timezone)->format('H.i') }}</p>
                <p class="muted">{{ __('Remaining capacity') }}: {{ $schedule->remaining_capacity }} {{ __('of') }} {{ $schedule->quota }}</p>
                <a href="{{ route('member.services.show', $schedule->service->getKey()) }}">{{ __('View service details') }}</a>
            </article>
        @endforeach
    @endif
</section>
@endsection
