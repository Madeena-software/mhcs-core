@extends('operator.layout')

@section('title', 'Basic-examination worklist')

@section('content')
<section aria-labelledby="basic-examination-worklist-title">
    <h1 id="basic-examination-worklist-title">Basic-examination worklist</h1>
    <p class="muted">Advance-booking paper tickets admitted to the active site's assigned shifts, ordered by ready time.</p>
    <p><a href="{{ route('operator.xray-readiness-worklist') }}">View X-ray readiness worklist</a></p>
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
                        <td>{{ in_array($entry['state'], ['called', 'in_service'], true) ? 'Current claimed admission' : $entry['ticket_number'] }}</td>
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
                                <span class="status">Claimed by you</span>
                                @if ($entry['state'] === 'in_service')
                                    @if (! $entry['has_vital_signs_execution'])
                                        <a href="{{ route('operator.basic-examination-worklist.vital-signs', $entry['admission_id']) }}">Record vital signs</a>
                                    @endif
                                    @if (! $entry['has_questionnaire'])
                                        <a href="{{ route('operator.basic-examination-worklist.questionnaire', $entry['admission_id']) }}">Upload paper questionnaire</a>
                                    @endif
                                    @if ($entry['can_complete'])
                                        <form method="POST" action="{{ route('operator.basic-examination-worklist.complete', $entry['admission_id']) }}">
                                            @csrf
                                            <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <button type="submit">Complete basic examination</button>
                                        </form>
                                    @endif
                                @elseif ($entry['state'] === 'called')
                                    <form method="POST" action="{{ route('operator.basic-examination-worklist.start', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">Start</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('operator.basic-examination-worklist.call', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">Call</button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operator.basic-examination-worklist.claim', $entry['admission_id']) }}">
                                    @csrf
                                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">Claim</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No advance-booking tickets are waiting for basic examination.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
