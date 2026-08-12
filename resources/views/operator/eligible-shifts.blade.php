@extends('operator.layout')

@section('title', 'Assigned shifts')

@section('content')
<section aria-labelledby="eligible-title">
    <h1 id="eligible-title">Assigned shifts</h1>
    <p class="muted">{{ $activeSite->display_name }} · eligible schedules assigned to your Operator profile.</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Schedule</th><th>Window</th><th>Confirmed</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td><code>{{ $shift->member_schedule_id }}</code></td>
                        <td>{{ $shift->schedule_starts_at }} — {{ $shift->schedule_ends_at }}</td>
                        <td>{{ $shift->confirmed_count_at_eligibility }} / {{ $shift->quota }}</td>
                        <td><a href="{{ route('operator.attendance', ['schedule' => $shift->member_schedule_id, 'at' => $shift->schedule_starts_at->format(DATE_ATOM)]) }}">Open attendance</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No assigned eligible shifts are available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
