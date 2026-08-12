@extends('operator.layout')

@section('title', 'Identity verification')

@section('content')
<section aria-labelledby="identity-title">
    <h1 id="identity-title">Front-desk identity verification</h1>
    <p class="muted">Compare the physical KTP/KIA and profile photograph. This page records only the Operator verification decision.</p>

    <section class="card">
        <h2>Case</h2>
        <p><strong>Status:</strong> {{ $case['state'] }}</p>
        <p><strong>Booking:</strong> <code>{{ $case['booking_id'] }}</code></p>
        @if ($case['reason'])<p><strong>Reason:</strong> {{ $case['reason'] }}</p>@endif
    </section>

    @if ($view)
        <section class="card">
            <h2>Member summary</h2>
            <p><strong>Name:</strong> {{ $view['member_name'] }}</p>
            <p><strong>Medical record:</strong> {{ $view['medical_record_number'] }}</p>
            <p><strong>NIK:</strong> {{ $view['nik'] ?? 'Withheld' }}</p>
            <p><strong>Service:</strong> {{ $view['service_name'] }}</p>
        </section>

        <section class="grid">
            @foreach ([['label' => 'Current identity document', 'asset' => $view['identity_document']], ['label' => 'Latest approved profile photo', 'asset' => $view['latest_profile_photo']] ] as $item)
                <section class="card">
                    <h2>{{ $item['label'] }}</h2>
                    @if ($item['asset'])
                        <img src="{{ route('operator.identity-verification.asset', [$case['case_id'], $item['asset']['asset_id']]) }}" alt="{{ $item['label'] }}" style="max-width:100%;height:auto;">
                        <p class="muted">{{ $item['asset']['type'] }} · {{ $item['asset']['format'] }}</p>
                    @else
                        <p class="muted">No approved current asset is available.</p>
                    @endif
                </section>
            @endforeach
        </section>

        <section class="card">
            <h2>Confirm physical NIK</h2>
            <form method="POST" action="{{ route('operator.identity-verification.lookup', $case['case_id']) }}">
                @csrf
                <label for="nik">NIK from the physical KTP/KIA</label>
                <input id="nik" name="nik" inputmode="numeric" autocomplete="off" required>
                <input type="hidden" name="at" value="{{ now()->format(DATE_ATOM) }}">
                <button type="submit">Verify exact NIK</button>
            </form>
        </section>

        <section class="card">
            <h2>Previous profile photographs</h2>
            <p class="muted">Previous photographs remain hidden unless the latest photograph is explicitly insufficient.</p>
            @if ($view['previous_photos_revealed'] ?? false)
                <div class="grid">
                    @foreach (($view['previous_profile_photos'] ?? []) as $photo)
                        <img src="{{ route('operator.identity-verification.asset', [$case['case_id'], $photo['asset_id']]) }}" alt="Previous approved profile photograph" style="max-width:100%;height:auto;">
                    @endforeach
                </div>
            @else
                <form method="POST" action="{{ route('operator.identity-verification.previous-photos', $case['case_id']) }}">
                    @csrf
                    <label for="previous-reason">Why is the latest photograph insufficient?</label>
                    <input id="previous-reason" name="reason" maxlength="500" required>
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <button type="submit" class="secondary">Reveal previous photographs</button>
                </form>
            @endif
        </section>

    @elseif ($evidenceStatus === 'unavailable')
        <section class="card">
            <h2>Member summary</h2>
            <p><strong>Name:</strong> {{ $safeSummary['member_name'] }}</p>
            <p><strong>Medical record:</strong> {{ $safeSummary['medical_record_number'] }}</p>
            <p><strong>Service:</strong> {{ $safeSummary['service_name'] }}</p>
            <p class="muted">Current identity evidence is unavailable. Protected comparison remains closed.</p>
        </section>
    @else
        <section class="card"><p class="muted">This case is closed. Protected identity assets are no longer available.</p></section>
    @endif

    @if ($case['state'] === 'matched')
        <section class="card">
            <h2>Paper consent</h2>
            <p class="muted">Identity is matched. Confirm the Member's signed paper consent before a later check-in step.</p>
            <a href="{{ route('operator.paper-consent.show', $case['case_id']) }}">Confirm paper consent</a>
        </section>
    @endif

    @if ($case['state'] === 'open' && $evidenceStatus !== 'available')
        <section class="card">
            <h2>Record decision</h2>
            <div class="actions">
                @if (in_array('matched', $allowedDecisions, true))
                    <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                        @csrf
                        <input type="hidden" name="state" value="matched">
                        <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                        <button type="submit">Matched</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                    @csrf
                    <input type="hidden" name="state" value="mismatch_reported">
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <label for="mismatch-reason">Mismatch reason</label>
                    <input id="mismatch-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="secondary">Report mismatch</button>
                </form>
                <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                    @csrf
                    <input type="hidden" name="state" value="insufficient_evidence">
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <label for="insufficient-reason">Insufficient evidence reason</label>
                    <input id="insufficient-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="secondary">Insufficient evidence</button>
                </form>
            </div>
        </section>
        <section class="card">
            <h2>Cancel case</h2>
            <form method="POST" action="{{ route('operator.identity-verification.cancel', $case['case_id']) }}">
                @csrf
                <label for="cancel-reason">Cancellation reason</label>
                <input id="cancel-reason" name="reason" maxlength="500" required>
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit" class="secondary">Cancel verification</button>
            </form>
        </section>
    @endif
</section>
@endsection
