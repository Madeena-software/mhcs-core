@extends('operator.layout')

@section('title', 'Attendance list')

@section('content')
<section aria-labelledby="attendance-title">
    <h1 id="attendance-title">Attendance list</h1>
    <p class="muted">{{ $site->display_name }} · schedule <code>{{ $scheduleId }}</code> · {{ count($rows) }} eligible members</p>
    <section class="card" style="margin-top: 18px">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Medical record</th><th>Service</th><th>Operational state</th><th>Next safe action</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['member_name'] }}<br><span class="muted">NIK: {{ $row['nik'] ?? 'Identifier withheld' }}</span></td>
                        <td>{{ $row['medical_record_number'] }}</td>
                        <td>{{ $row['service_name'] }} ({{ $row['service_code'] }})</td>
                        <td class="status">{{ ucfirst(str_replace('_', ' ', $row['booking_status'])) }}</td>
                        <td>
                            @if ($row['booking_status'] === 'confirmed')
                                <form method="POST" action="{{ route('operator.arrivals.confirm') }}">
                                    @csrf
                                    <input type="hidden" name="booking_id" value="{{ $row['booking_id'] }}">
                                    <input type="hidden" name="occurrence_at" value="{{ $at }}">
                                    <button type="submit">{{ $row['next_action'] }}</button>
                                </form>
                            @elseif ($row['booking_status'] === 'arrived')
                                <a href="{{ route('operator.verification-worklist') }}">{{ $row['next_action'] }}</a>
                            @elseif (in_array($row['booking_status'], ['checked_in', 'in_progress'], true))
                                <a href="{{ route('operator.basic-examination-worklist') }}">{{ $row['next_action'] }}</a>
                            @else
                                <span class="muted">{{ $row['next_action'] }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No eligible clinic-day bookings were found for this attendance window.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
