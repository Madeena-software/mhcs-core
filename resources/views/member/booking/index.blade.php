@extends('member.layout')

@section('title', __('History of Radiography Sessions'))

@section('content')
<section aria-labelledby="bookings-title">
    <h1 id="bookings-title">{{ __('History of Radiography Sessions') }}</h1>
    @if ($bookings->isEmpty())
        <div class="card"><p class="muted">{{ __('No Radiography Sessions have been recorded yet.') }}</p><a href="{{ route('member.services') }}">{{ __('Schedule a Radiography Session') }}</a></div>
    @else
        <div class="summary">
            @foreach ($bookings as $booking)
                <article class="card">
                    <h2>{{ $booking->service_code_snapshot }}</h2>
                    <p>{{ $booking->site_name_snapshot }}</p>
                    <p>{{ __('Status') }}: <strong>{{ $booking->status === 'confirmed' ? __('Scheduled Radiography Session') : __($booking->status) }}</strong></p>
                    <a href="{{ route('member.bookings.show', $booking->getKey()) }}">{{ __('View Session Details') }}</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
