@extends('member.layout')

@section('title', __('Session Details'))

@section('content')
<section aria-labelledby="booking-title">
    <h1 id="booking-title">{{ __('Session Details') }}</h1>
    <div class="card">
        <dl class="summary">
            <div><dt>{{ __('Service') }}</dt><dd>{{ $booking->service_code_snapshot }}</dd></div>
            <div><dt>{{ __('Location') }}</dt><dd>{{ $booking->site_name_snapshot }}</dd></div>
            <div><dt>{{ __('Schedule') }}</dt><dd>{{ $booking->schedule->starts_at->setTimezone($booking->site_timezone_snapshot)->translatedFormat('l, j F Y') }} {{ __('at') }} {{ $booking->schedule->starts_at->setTimezone($booking->site_timezone_snapshot)->format('H.i') }}</dd></div>
            <div><dt>{{ __('Status') }}</dt><dd>{{ $booking->status === 'confirmed' ? __('Scheduled Radiography Session') : __($booking->status) }}</dd></div>
            <div><dt>{{ __('Price') }}</dt><dd>{{ $booking->pointCost() }} Madeena Points</dd></div>
            <div><dt>{{ __('Payment source') }}</dt><dd>{{ __('Personal Madeena Points') }}</dd></div>
            <div><dt>{{ __('Automatic interpretation assistance') }}</dt><dd>{{ $booking->includes_ai_snapshot ? __('Included') : __('Not included') }}</dd></div>
            <div><dt>{{ __('Doctor review') }}</dt><dd>{{ $booking->includes_doctor_snapshot ? __('Included') : __('Not included') }}</dd></div>
        </dl>
        <p class="muted">{{ __('Local reference number') }}: {{ $booking->imagingOrder?->id }}</p>
    </div>
</section>
@endsection
