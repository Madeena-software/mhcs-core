@extends('operator.layout')

@section('title', 'Operator workstation')

@section('content')
<section aria-labelledby="workstation-title">
    <p class="eyebrow">Clinic-day operations</p>
    <h1 id="workstation-title">Operator workstation</h1>
    <p class="muted">Welcome, {{ $operatorName }}. Follow the clinic flow in order and keep each action in its assigned site context.</p>

    <section class="card" aria-labelledby="active-site-title">
        <h2 id="active-site-title">Active site</h2>
        @if ($activeSite)
            <p><strong>{{ $activeSite->display_name }}</strong><br><span class="muted">{{ $activeSite->code }} · {{ $activeSite->timezone }}</span></p>
            <a href="{{ route('operator.site') }}">Review or switch site</a>
            <p><strong>LCD queue</strong><br><a class="primary-action" href="{{ route('lcd.show', $activeSite->getKey()) }}" target="_blank" rel="noopener">Open LCD queue</a></p>
            <p class="muted"><code>{{ route('lcd.show', $activeSite->getKey()) }}</code></p>
        @else
            <p class="muted">No active site is selected. Site selection is required before attendance actions.</p>
            <a class="primary-action" href="{{ route('operator.site') }}">Select an assigned site</a>
        @endif
    </section>

    <ol class="workflow" aria-label="Ordered clinic workflow">
        <li class="workflow-item {{ $activeSite ? 'primary' : '' }}">
            <span class="step-number" aria-hidden="true">1</span>
            <div>
                <h2>1. Attendance</h2>
                <p class="muted">{{ $shiftCount }} assigned shift(s) available.</p>
            </div>
            @if ($activeSite)
                <a class="primary-action" href="{{ route('operator.eligible-shifts') }}">Open assigned-shift attendance</a>
            @else
                <span class="muted">Select a site first</span>
            @endif
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">2</span>
            <div>
                <h2>2. Arrival and verification</h2>
                <p class="queue-count" data-testid="verification-queue-count">{{ $verificationCount }} awaiting verification</p>
            </div>
            <a href="{{ route('operator.verification-worklist') }}">Open verification worklist</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">3</span>
            <div>
                <h2>3. Consent and ticket</h2>
                <p class="muted">Confirm paper consent, then check in and print the private ticket.</p>
            </div>
            <a href="{{ route('operator.verification-worklist') }}">Continue from verification</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">4</span>
            <div>
                <h2>4. Basic examination</h2>
                <p class="queue-count" data-testid="basic-examination-queue-count">{{ $basicExaminationCount }} ready for basic examination</p>
            </div>
            <a href="{{ route('operator.basic-examination-worklist') }}">Open basic-examination queue</a>
        </li>
        <li class="workflow-item">
            <span class="step-number" aria-hidden="true">5</span>
            <div>
                <h2>5. X-ray readiness</h2>
                <p class="queue-count" data-testid="xray-queue-count">{{ $xrayReadinessCount }} ready for X-ray readiness</p>
            </div>
            <a href="{{ route('operator.xray-readiness-worklist') }}">Open X-ray-ready queue</a>
        </li>
    </ol>
</section>
@endsection
