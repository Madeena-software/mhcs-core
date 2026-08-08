@extends('operator.layout')

@section('title', 'X-ray readiness worklist')

@section('content')
<section aria-labelledby="xray-readiness-worklist-title">
    <h1 id="xray-readiness-worklist-title">X-ray readiness worklist</h1>
    <p class="muted">X-ray tickets for the active site's assigned shifts, ordered by ready time.</p>
    <p><a href="{{ route('operator.basic-examination-worklist') }}">View basic-examination worklist</a></p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Paper ticket</th>
                    <th>Site</th>
                    <th>Shift</th>
                    <th>Stage</th>
                    <th>State</th>
                    <th>Ready time</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry['ticket_number'] }}</td>
                        <td>{{ $entry['site_name'] }}</td>
                        <td>
                            <time datetime="{{ $entry['schedule_starts_at'] }}">{{ $entry['schedule_starts_at'] }}</time>
                            –
                            <time datetime="{{ $entry['schedule_ends_at'] }}">{{ $entry['schedule_ends_at'] }}</time>
                        </td>
                        <td>{{ $entry['stage'] }}</td>
                        <td class="status">{{ $entry['state'] }}</td>
                        <td><time datetime="{{ $entry['ready_at'] }}">{{ $entry['ready_at'] }}</time></td>
                        <td>
                            @if ($entry['claimed_by_current_operator'])
                                @if ($entry['state'] === 'waiting')
                                    <form method="POST" action="{{ route('operator.xray-readiness-worklist.call', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">Call</button>
                                    </form>
                                    <span class="status">Claimed by you</span>
                                @else
                                    <span class="status">Called</span>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operator.xray-readiness-worklist.claim', $entry['admission_id']) }}">
                                    @csrf
                                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">Claim</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No X-ray tickets are ready.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
