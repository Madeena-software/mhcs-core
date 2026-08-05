@extends('operator.layout')

@section('title', 'Attendance list')

@section('content')
<section aria-labelledby="attendance-title">
    <h1 id="attendance-title">Attendance list</h1>
    <p class="muted">{{ $site->display_name }} · schedule <code>{{ $scheduleId }}</code></p>
    <section class="card">
        <form method="GET" action="{{ route('operator.attendance', $scheduleId) }}">
            <label for="at">Attendance time (ISO 8601 with explicit offset)</label>
            <input id="at" name="at" value="{{ $at }}" required aria-describedby="at-help">
            <p id="at-help" class="muted">Example: 2030-01-10T10:15:00+07:00. Times are normalized to UTC for persistence.</p>
            <div class="actions"><button type="submit">Refresh attendance</button></div>
        </form>
    </section>

    <section class="card" style="margin-top: 18px">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Medical record</th><th>Service</th><th>Status</th><th>Record arrival</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['member_name'] }}<br><span class="muted">{{ $row['masked_nik'] ?? 'Identifier withheld' }}</span></td>
                        <td>{{ $row['medical_record_number'] }}</td>
                        <td>{{ $row['service_name'] }} ({{ $row['service_code'] }})</td>
                        <td class="status">{{ $row['booking_status'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('operator.arrivals.confirm') }}">
                                @csrf
                                <input type="hidden" name="booking_id" value="{{ $row['booking_id'] }}">
                                <input type="hidden" name="occurrence_at" value="{{ $at }}">
                                <button type="submit">Confirm physical arrival</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No eligible confirmed personal bookings were found for this attendance window.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
