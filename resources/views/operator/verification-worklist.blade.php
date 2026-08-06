@extends('operator.layout')

@section('title', 'Verification worklist')

@section('content')
<section aria-labelledby="worklist-title">
    <h1 id="worklist-title">Verification worklist</h1>
    <p class="muted">Arrivals recorded at the active site and awaiting the next verification slice.</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Medical record</th><th>Schedule</th><th>Recorded by</th><th>Occurrence</th><th>Status</th><th>Verification</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($arrivals as $arrival)
                    <tr>
                        <td>{{ $arrival['member_name'] }}</td>
                        <td>{{ $arrival['medical_record_number'] ?? 'Withheld' }}</td>
                        <td><code>{{ $arrival['booking_id'] }}</code></td>
                        <td>{{ $arrival['operator_name'] }}</td>
                        <td>{{ $arrival['occurrence_at'] }}</td>
                        <td class="status">{{ $arrival['status'] }}</td>
                        <td>{{ $arrival['verification_state'] }}</td>
                        <td>
                            @if ($canVerify && $arrival['verification_state'] === 'unclaimed')
                                <form method="POST" action="{{ route('operator.identity-verification.start') }}">
                                    @csrf
                                    <input type="hidden" name="arrival_id" value="{{ $arrival['arrival_id'] }}">
                                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">Start verification</button>
                                </form>
                            @elseif ($arrival['verification_case_id'])
                                <a href="{{ route('operator.identity-verification.show', $arrival['verification_case_id']) }}">Open case</a>
                            @else
                                <span class="muted">Unavailable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No arrivals await verification.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
