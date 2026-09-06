@extends('operator.layout')

@section('title', __('Create Field Operational Shift'))

@section('content')
<section aria-labelledby="shift-create-title">
    <h1 id="shift-create-title">{{ __('Create Field Operational Shift') }}</h1>
    <p class="muted">{{ __('Create an on-demand field screening operational shift within your authorized site scope.') }}</p>

    <section class="card">
        <form method="POST" action="{{ route('operator.shifts.store') }}">
            @csrf

            <label for="operator-site-id">{{ __('Screening Site') }}</label>
            <select id="operator-site-id" name="operator_site_id" required>
                @foreach ($assignedSites as $site)
                    <option value="{{ $site->operator_site_id }}" {{ ($activeSite && $activeSite->operator_site_id === $site->operator_site_id) ? 'selected' : '' }}>
                        {{ $site->display_name }} ({{ $site->operator_site_id }})
                    </option>
                @endforeach
            </select>
            <p class="muted">{{ __('You can only create shifts for sites assigned to your Operator profile.') }}</p>

            <label for="starts-at">{{ __('Shift Start Time') }}</label>
            <input id="starts-at" name="starts_at" type="datetime-local" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required>

            <label for="ends-at">{{ __('Shift End Time') }}</label>
            <input id="ends-at" name="ends_at" type="datetime-local" value="{{ old('ends_at', now()->addHours(8)->format('Y-m-d\TH:i')) }}" required>

            <label for="quota">{{ __('Capacity Quota') }}</label>
            <input id="quota" name="quota" type="number" min="1" max="1000" value="{{ old('quota', 100) }}" required>
            <p class="muted">{{ __('Maximum number of members for this operational field screening shift.') }}</p>

            <div class="actions" style="margin-top: 20px">
                <button type="submit">{{ __('Create Shift and Assign to Self') }}</button>
                <a href="{{ route('operator.eligible-shifts') }}" class="btn-secondary" style="margin-left: 10px">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</section>
@endsection
