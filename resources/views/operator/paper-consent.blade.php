@extends('operator.layout')

@section('title', __('Paper consent'))

@section('content')
<section aria-labelledby="consent-title">
    <h1 id="consent-title">{{ __('Paper consent confirmation') }}</h1>
    <p class="muted">{{ __('Confirm the Member\'s signed offline Informed Consent form or apply an active versioned reusable consent. This action does not check in the booking or issue a ticket.') }}</p>

    <section class="card">
        <h2>{{ __('Visit') }}</h2>
        <p><strong>{{ __('Member') }}:</strong> {{ $summary['member_name'] }}</p>
        <p><strong>{{ __('Medical record') }}:</strong> {{ $summary['medical_record_number'] }}</p>
        <p><strong>{{ __('Booking') }}:</strong> <code>{{ $summary['booking_id'] }}</code></p>
        <p><strong>{{ __('Service') }}:</strong> {{ $summary['service_name'] }}</p>
        <p><strong>{{ __('Booking status') }}:</strong> {{ __($summary['booking_status']) }}</p>
    </section>

    @if (! empty($reusableConsent['is_withdrawn']))
        <section class="card" style="border-left: 4px solid #e53e3e">
            <h2 style="color: #e53e3e">{{ __('Consent Withdrawn — Progression Blocked') }}</h2>
            <p>{{ __('Informed consent was previously withdrawn for this Member.') }}</p>
            <p><strong>{{ __('Withdrawal Reason') }}:</strong> {{ $reusableConsent['latest_master_consent']->withdrawn_reason }}</p>
            <p><strong>{{ __('Withdrawn Date') }}:</strong> {{ $reusableConsent['latest_master_consent']->withdrawn_at }}</p>
            <p class="muted">{{ __('A brand new signed consent version must be recorded below before this member can proceed to ticket issue.') }}</p>
        </section>
    @endif

    @if (! empty($reusableConsent['visit_confirmed']) || $consent)
        <section class="card" aria-live="polite">
            <h2>{{ __('Consent Confirmed for this Visit') }}</h2>
            @if (! empty($reusableConsent['active_master_consent']))
                <p><strong>{{ __('Master Consent Version') }}:</strong> V{{ $reusableConsent['active_master_consent']->consent_version }} ({{ $reusableConsent['active_master_consent']->screening_scope }})</p>
                <p><strong>{{ __('Master Signed Date') }}:</strong> {{ $reusableConsent['active_master_consent']->signed_at }}</p>
                @if ($consent)
                    <p><strong>{{ __('Private scan') }}:</strong> {{ $consent['has_private_scan'] ? __('Stored privately') : __('Not supplied') }}</p>
                @endif
            @elseif ($consent)
                <p><strong>{{ __('Form') }}:</strong> {{ $consent['form_name'] }} / {{ $consent['form_version'] }}</p>
                <p><strong>{{ __('Signed date') }}:</strong> {{ $consent['signed_at'] }}</p>
                <p><strong>{{ __('Private scan') }}:</strong> {{ $consent['has_private_scan'] ? __('Stored privately') : __('Not supplied') }}</p>
            @endif
            <p><a href="{{ route('operator.check-in.show', $case['case_id']) }}" class="btn">{{ __('Proceed to verified check-in and paper ticket issue') }}</a></p>

            @if (! empty($reusableConsent['active_master_consent']))
                <details style="margin-top: 20px">
                    <summary style="cursor: pointer; color: #718096">{{ __('Withdraw Informed Consent') }}</summary>
                    <form method="POST" action="{{ route('operator.paper-consent.withdraw', $case['case_id']) }}" style="margin-top: 10px">
                        @csrf
                        <input type="hidden" name="master_consent_id" value="{{ $reusableConsent['active_master_consent']->id }}">
                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <label for="withdraw-reason">{{ __('Reason for Withdrawal') }} *</label>
                        <input id="withdraw-reason" name="reason" type="text" placeholder="e.g. Member explicitly withdrew screening consent" required>
                        <button type="submit" class="btn-danger" style="margin-top: 8px">{{ __('Confirm Consent Withdrawal') }}</button>
                    </form>
                </details>
            @endif
        </section>
    @elseif (! empty($reusableConsent['has_active_master_consent']))
        <section class="card">
            <h2>{{ __('Active Master Informed Consent Found') }}</h2>
            <p>{{ __('This Member has an active versioned master consent on file for this screening scope. Repeated full signature is NOT required.') }}</p>
            <p><strong>{{ __('Consent Version') }}:</strong> V{{ $reusableConsent['active_master_consent']->consent_version }}</p>
            <p><strong>{{ __('Scope') }}:</strong> {{ $reusableConsent['active_master_consent']->screening_scope }}</p>
            <p><strong>{{ __('Signed date') }}:</strong> {{ $reusableConsent['active_master_consent']->signed_at }}</p>

            <form method="POST" action="{{ route('operator.paper-consent.visit-confirm', $case['case_id']) }}">
                @csrf
                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button type="submit">{{ __('Confirm Visit with Reusable Consent') }}</button>
            </form>

            <details style="margin-top: 25px">
                <summary style="cursor: pointer; color: #4a5568">{{ __('Or: Record New Master Consent Version (e.g. scope/guardian change)') }}</summary>
                <form method="POST" action="{{ route('operator.paper-consent.store', $case['case_id']) }}" enctype="multipart/form-data" style="margin-top: 15px">
                    @csrf
                    <input type="hidden" name="form_name" value="Informed Consent">
                    <input type="hidden" name="form_version" value="V1">
                    <input type="hidden" name="signer_type" value="member">
                    <input type="hidden" name="signature_confirmed" value="1">
                    <label for="signed-at-new">{{ __('Actual signing date') }}</label>
                    <input id="signed-at-new" name="signed_at" type="date" value="{{ old('signed_at', now()->format('Y-m-d')) }}" required>
                    <label for="scan-new">{{ __('Optional signed paper photo or scan') }}</label>
                    <input id="scan-new" name="scan" type="file" accept="image/jpeg,image/png,application/pdf">
                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <button type="submit" class="btn-secondary" style="margin-top: 10px">{{ __('Record New Versioned Master Consent') }}</button>
                </form>
            </details>
        </section>
    @else
        <section class="card">
            <h2>{{ __('Confirm the approved form') }}</h2>
            <form method="POST" action="{{ route('operator.paper-consent.store', $case['case_id']) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_name" value="Informed Consent">
                <input type="hidden" name="form_version" value="V1">
                <input type="hidden" name="signer_type" value="member">
                <input type="hidden" name="signature_confirmed" value="1">
                <label for="signed-at">{{ __('Actual signing date') }}</label>
                <input id="signed-at" name="signed_at" type="date" value="{{ old('signed_at', now()->format('Y-m-d')) }}" required>
                <p class="muted">{{ __('Choose the date shown on the signed paper form.') }}</p>
                <label for="scan">{{ __('Signed paper photo or scan') }}</label>
                <input id="scan" name="scan" type="file" accept="image/jpeg,image/png,application/pdf" required>
                <p class="muted">{{ __('JPEG, PNG, or PDF only, up to 100 MB. The file is stored privately and is not retrievable from this portal.') }}</p>
                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button type="submit">{{ __('Confirm paper consent') }}</button>
            </form>
        </section>
    @endif
</section>
@endsection
