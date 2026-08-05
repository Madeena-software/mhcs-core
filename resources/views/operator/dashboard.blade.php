@extends('operator.layout')

@section('title', 'Operator workstation')

@section('content')
<section aria-labelledby="workstation-title">
    <h1 id="workstation-title">Operator workstation</h1>
    <p class="muted">Welcome, {{ $operatorName }}. Select one authorized site before handling attendance.</p>

    <div class="grid">
        <section class="card" aria-labelledby="site-title">
            <h2 id="site-title">Active site</h2>
            @if ($activeSite)
                <p><strong>{{ $activeSite->display_name }}</strong><br><span class="muted">{{ $activeSite->code }} · {{ $activeSite->timezone }}</span></p>
                <a href="{{ route('operator.site') }}">Review or switch site</a>
            @else
                <p class="muted">No active site is selected.</p>
                <a href="{{ route('operator.site') }}">Select an authorized site</a>
            @endif
        </section>
        <section class="card" aria-labelledby="shift-title">
            <h2 id="shift-title">Assigned shifts</h2>
            <p>{{ count($shifts) }} shift(s) are available in this site context.</p>
            <a href="{{ route('operator.eligible-shifts') }}">Open assigned shifts</a>
        </section>
        <section class="card" aria-labelledby="verification-title">
            <h2 id="verification-title">Verification worklist</h2>
            <p>{{ count($arrivals) }} arrival(s) await verification.</p>
            <a href="{{ route('operator.verification-worklist') }}">Open worklist</a>
        </section>
    </div>

    @if ($activeSite && $sites)
        <section class="card" aria-labelledby="quick-site-title" style="margin-top: 18px">
            <h2 id="quick-site-title">Switch active site</h2>
            <form method="POST" action="{{ route('operator.site.select') }}">
                @csrf
                <label for="site_id">Authorized site</label>
                <select id="site_id" name="site_id" required>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected($activeSite->id === $site->id)>{{ $site->display_name }} ({{ $site->code }})</option>
                    @endforeach
                </select>
                <div class="actions"><button type="submit">Switch site</button></div>
            </form>
        </section>
    @endif
</section>
@endsection
