@extends('operator.layout')

@section('title', __('Operator workstation'))

@section('content')
<section aria-labelledby="workstation-title">
    <p class="eyebrow">{{ __('Clinic-day operations') }}</p>
    <h1 id="workstation-title">{{ __('Operator workstation') }}</h1>
    <p class="muted">{{ __('Welcome, :name. Follow the clinic flow in order and keep each action in its assigned site context.', ['name' => $operatorName]) }}</p>

    <section class="card" aria-labelledby="active-site-title">
        <h2 id="active-site-title">{{ __('Active site') }}</h2>
        @if ($activeSite)
            <p><strong>{{ $activeSite->display_name }}</strong><br><span class="muted">{{ $activeSite->code }} · {{ $activeSite->timezone }}</span></p>
            <a href="{{ route('operator.site') }}">{{ __('Review or switch site') }}</a>
            <p><strong>{{ __('LCD queue') }}</strong><br><a class="primary-action" href="{{ route('lcd.show', $activeSite->getKey()) }}" target="_blank" rel="noopener">{{ __('Open LCD queue') }}</a></p>
            <p class="muted"><code>{{ route('lcd.show', $activeSite->getKey()) }}</code></p>
        @else
            <p class="muted">{{ __('No active site is selected. Site selection is required before attendance actions.') }}</p>
            <a class="primary-action" href="{{ route('operator.site') }}">{{ __('Select an assigned site') }}</a>
        @endif
    </section>

    <ol class="workflow" aria-label="{{ __('Ordered clinic workflow') }}">
        <li class="workflow-item {{ $activeSite ? 'primary' : '' }}">
            <span class="step-number" aria-hidden="true">1</span>
            <div>
                <h2>1. {{ __('Attendance') }}</h2>
                <p class="muted">{{ __(':count assigned shift(s) available.', ['count' => $shiftCount]) }}</p>
            </div>
            @if ($activeSite)
                <a class="primary-action" href="{{ route('operator.eligible-shifts') }}">{{ __('Open assigned-shift attendance') }}</a>
            @else
                <span class="muted">{{ __('Select a site first') }}</span>
            @endif
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">2</span>
            <div>
                <h2>2. {{ __('Arrival and verification') }}</h2>
                <p class="queue-count" data-testid="verification-queue-count">{{ __(':count awaiting verification', ['count' => $verificationCount]) }}</p>
            </div>
            <a href="{{ route('operator.verification-worklist') }}">{{ __('Open verification worklist') }}</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">3</span>
            <div>
                <h2>3. {{ __('Consent and ticket') }}</h2>
                <p class="muted">{{ __('Confirm paper consent, then check in and print the private ticket.') }}</p>
            </div>
            <a href="{{ route('operator.verification-worklist') }}">{{ __('Continue from verification') }}</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">4</span>
            <div>
                <h2>4. {{ __('Basic examination') }}</h2>
                <p class="queue-count" data-testid="basic-examination-queue-count">{{ __(':count ready for basic examination', ['count' => $basicExaminationCount]) }}</p>
            </div>
            <a href="{{ route('operator.basic-examination-worklist') }}">{{ __('Open basic-examination queue') }}</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">5</span>
            <div>
                <h2>5. {{ __('Radiography session readiness') }}</h2>
                <p class="queue-count" data-testid="xray-queue-count">{{ __(':count ready for radiography session readiness', ['count' => $xrayReadinessCount]) }}</p>
            </div>
            <a href="{{ route('operator.xray-readiness-worklist') }}">{{ __('Open radiography-ready queue') }}</a>
        </li>
    </ol>
</section>
@endsection
