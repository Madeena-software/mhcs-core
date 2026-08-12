@extends('member.layout')

@section('title', __('Dashboard Member'))

@section('content')
<section aria-labelledby="dashboard-title">
    <h1 id="dashboard-title">{{ __('Dashboard Member') }}</h1>
    <p>{{ __('Welcome, :name.', ['name' => $memberName]) }}</p>

    <dl class="summary">
        <div><dt>{{ __('Medical record number') }}</dt><dd>{{ $medicalRecordNumber }}</dd></div>
        <div><dt>{{ __('Profile completeness') }}</dt><dd>{{ $completionPercentage }}%</dd></div>
        <div><dt>{{ __('Identity status') }}</dt><dd>{{ $identityStatus }}</dd></div>
        <div><dt>{{ __('Account status') }}</dt><dd>{{ $accountStatus }}</dd></div>
    </dl>

    <section class="card" aria-labelledby="services-title">
        <h2 id="services-title">{{ __('Next services') }}</h2>
        <p class="muted">{{ __('Schedule a Radiography Session and view your session status.') }}</p>
        <div class="actions">
            <a href="{{ route('member.services') }}">{{ __('Schedule a Radiography Session') }}</a>
            <a href="{{ route('member.bookings') }}">{{ __('View My Sessions') }}</a>
        </div>
    </section>
</section>
@endsection
