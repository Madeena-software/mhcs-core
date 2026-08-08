@extends('operator.layout')

@section('title', 'X-ray readiness worklist')

@section('content')
<section aria-labelledby="xray-readiness-worklist-title">
    <h1 id="xray-readiness-worklist-title">X-ray readiness worklist</h1>
    <p class="muted">Unclaimed X-ray tickets for the active site's assigned shifts, ordered by ready time.</p>
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
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No X-ray tickets are ready.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
