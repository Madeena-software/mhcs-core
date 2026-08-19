@extends('operator.layout')

@section('title', __('Assigned shifts'))

@section('content')
<section aria-labelledby="eligible-title">
    <h1 id="eligible-title">{{ __('Assigned shifts') }}</h1>
    <p class="muted">{{ $activeSite->display_name }} · {{ __('eligible schedules assigned to your Operator profile.') }}</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Schedule') }}</th><th>{{ __('Window') }}</th><th>{{ __('Confirmed') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td><strong>{{ $shift->schedule_display_reference }}</strong></td>
                        <td>{{ $shift->schedule_starts_at }} — {{ $shift->schedule_ends_at }}</td>
                        <td>{{ $shift->current_confirmed_count }} / {{ $shift->schedule_quota }}</td>
                        <td><a href="{{ route('operator.attendance', ['schedule' => $shift->member_schedule_id, 'at' => $shift->schedule_starts_at->format(DATE_ATOM)]) }}">{{ __('Open attendance') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('No assigned eligible shifts are available.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
