@extends('operator.layout')

@section('title', __('Identity verification'))

@section('content')
<section aria-labelledby="identity-title">
    <h1 id="identity-title">{{ __('Front-desk identity verification') }}</h1>
    <p class="muted">{{ __('Compare the physical KTP/KIA and profile photograph. This page records only the Operator verification decision.') }}</p>

    <section class="card">
        <h2>{{ __('Case') }}</h2>
        <p><strong>{{ __('Status') }}:</strong> {{ __($case['state']) }}</p>
        <p><strong>{{ __('Booking') }}:</strong> <code>{{ $case['booking_id'] }}</code></p>
        @if ($case['reason'])<p><strong>{{ __('Reason') }}:</strong> {{ $case['reason'] }}</p>@endif
    </section>

    @if ($evidenceStatus === 'nonclinical_validation')
        <section class="card">
            <h2>{{ __('Nonclinical validation') }}</h2>
            <p class="muted">{{ __('No patient identity evidence or clinical consent is applicable to this validation context.') }}</p>
            <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                @csrf
                <input type="hidden" name="state" value="nonclinical_validation">
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit">{{ __('Confirm nonclinical validation') }}</button>
            </form>
        </section>
    @elseif ($evidenceStatus === 'walk_in_assisted')
        <section class="card">
            <h2>{{ __('Member summary') }}</h2>
            <p><strong>{{ __('Name') }}:</strong> {{ $view['member_name'] }}</p>
            <p><strong>{{ __('Medical record') }}:</strong> {{ $view['medical_record_number'] }}</p>
            <p><strong>{{ __('NIK') }}:</strong> {{ __('Not collected during walk-in registration') }}</p>
            <p class="muted">{{ __('No stored identity document or photograph is available. Confirm the in-person match explicitly before recording a matched decision.') }}</p>
        </section>
    @elseif ($view)
        <section class="card">
            <h2>{{ __('Member summary') }}</h2>
            <p><strong>{{ __('Name') }}:</strong> {{ $view['member_name'] }}</p>
            <p><strong>{{ __('Medical record') }}:</strong> {{ $view['medical_record_number'] }}</p>
            <p><strong>{{ __('NIK') }}:</strong> {{ $view['nik'] ?? __('Withheld') }}</p>
            <p><strong>{{ __('Service') }}:</strong> {{ $view['service_name'] }}</p>
        </section>

        <section class="grid">
            @foreach ([['label' => __('Current identity document'), 'asset' => $view['identity_document']], ['label' => __('Latest approved profile photo'), 'asset' => $view['latest_profile_photo']] ] as $item)
                <section class="card">
                    <h2>{{ $item['label'] }}</h2>
                    @if ($item['asset'])
                        <img src="{{ route('operator.identity-verification.asset', [$case['case_id'], $item['asset']['asset_id']]) }}" alt="{{ $item['label'] }}" style="max-width:100%;height:auto;">
                        <p class="muted">{{ $item['asset']['type'] }} · {{ $item['asset']['format'] }}</p>
                    @else
                        <p class="muted">{{ __('No approved current asset is available.') }}</p>
                    @endif
                </section>
            @endforeach
        </section>

        <section class="card">
            <h2>{{ __('Confirm physical NIK') }}</h2>
            <form method="POST" action="{{ route('operator.identity-verification.lookup', $case['case_id']) }}">
                @csrf
                <label for="nik">{{ __('NIK from the physical KTP/KIA') }}</label>
                <input id="nik" name="nik" inputmode="numeric" autocomplete="off" required>
                <input type="hidden" name="at" value="{{ now()->format(DATE_ATOM) }}">
                <button type="submit">{{ __('Verify exact NIK') }}</button>
            </form>
        </section>

        <section class="card">
            <h2>{{ __('Previous profile photographs') }}</h2>
            <p class="muted">{{ __('Previous photographs remain hidden unless the latest photograph is explicitly insufficient.') }}</p>
            @if ($view['previous_photos_revealed'] ?? false)
                <div class="grid">
                    @foreach (($view['previous_profile_photos'] ?? []) as $photo)
                        <img src="{{ route('operator.identity-verification.asset', [$case['case_id'], $photo['asset_id']]) }}" alt="{{ __('Previous approved profile photograph') }}" style="max-width:100%;height:auto;">
                    @endforeach
                </div>
            @else
                <form method="POST" action="{{ route('operator.identity-verification.previous-photos', $case['case_id']) }}">
                    @csrf
                    <label for="previous-reason">{{ __('Why is the latest photograph insufficient?') }}</label>
                    <input id="previous-reason" name="reason" maxlength="500" required>
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <button type="submit" class="secondary">{{ __('Reveal previous photographs') }}</button>
                </form>
            @endif
        </section>

    @elseif ($evidenceStatus === 'unavailable')
        <section class="card">
            <h2>{{ __('Member summary') }}</h2>
            <p><strong>{{ __('Name') }}:</strong> {{ $safeSummary['member_name'] }}</p>
            <p><strong>{{ __('Medical record') }}:</strong> {{ $safeSummary['medical_record_number'] }}</p>
            <p><strong>{{ __('Service') }}:</strong> {{ $safeSummary['service_name'] }}</p>
            <p class="muted">{{ __('Current identity evidence is unavailable. Protected comparison remains closed.') }}</p>
        </section>
    @else
        <section class="card"><p class="muted">{{ __('This case is closed. Protected identity assets are no longer available.') }}</p></section>
    @endif

    @if ($case['state'] === 'matched')
        <section class="card">
            <h2>{{ __('Paper consent') }}</h2>
            <p class="muted">{{ __('Identity is matched. Confirm the Member\'s signed paper consent before a later check-in step.') }}</p>
            <a href="{{ route('operator.paper-consent.show', $case['case_id']) }}">{{ __('Confirm paper consent') }}</a>
        </section>
    @endif

    @if ($case['state'] === 'open' && $evidenceStatus !== 'available')
        <section class="card">
            <h2>{{ __('Record decision') }}</h2>
            <div class="actions">
                @if (in_array('matched', $allowedDecisions, true))
                    <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                        @csrf
                        <input type="hidden" name="state" value="matched">
                        <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                        @if ($evidenceStatus === 'walk_in_assisted')
                            <label><input type="checkbox" name="manual_confirmation" value="1" required> {{ __('I confirm the person is physically matched to this walk-in registration.') }}</label>
                        @endif
                        <button type="submit">{{ __('Matched') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                    @csrf
                    <input type="hidden" name="state" value="mismatch_reported">
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <label for="mismatch-reason">{{ __('Mismatch reason') }}</label>
                    <input id="mismatch-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="secondary">{{ __('Report mismatch') }}</button>
                </form>
                <form method="POST" action="{{ route('operator.identity-verification.decision', $case['case_id']) }}">
                    @csrf
                    <input type="hidden" name="state" value="insufficient_evidence">
                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                    <label for="insufficient-reason">{{ __('Insufficient evidence reason') }}</label>
                    <input id="insufficient-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="secondary">{{ __('Insufficient evidence') }}</button>
                </form>
            </div>
        </section>
        <section class="card">
            <h2>{{ __('Cancel case') }}</h2>
            <form method="POST" action="{{ route('operator.identity-verification.cancel', $case['case_id']) }}">
                @csrf
                <label for="cancel-reason">{{ __('Cancellation reason') }}</label>
                <input id="cancel-reason" name="reason" maxlength="500" required>
                <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                <button type="submit" class="secondary">{{ __('Cancel verification') }}</button>
            </form>
        </section>
    @endif
</section>
@endsection
