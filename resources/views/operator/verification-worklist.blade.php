@extends('operator.layout')

@section('title', 'Verification worklist')

@section('content')
<section aria-labelledby="worklist-title">
    <h1 id="worklist-title">Verification worklist</h1>
    <p class="muted">Arrivals recorded at the active site and awaiting the next verification slice.</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Medical record</th><th>Schedule</th><th>Recorded by</th><th>Occurrence</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($arrivals as $arrival)
                    <tr>
                        <td>{{ $arrival['member_name'] }}</td>
                        <td>{{ $arrival['medical_record_number'] ?? 'Withheld' }}</td>
                        <td><code>{{ $arrival['booking_id'] }}</code></td>
                        <td>{{ $arrival['operator_name'] }}</td>
                        <td>{{ $arrival['occurrence_at'] }}</td>
                        <td class="status">{{ $arrival['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No arrivals await verification.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
