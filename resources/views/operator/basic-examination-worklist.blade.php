@extends('operator.layout')

@section('title', 'Basic-examination worklist')

@section('content')
<section aria-labelledby="basic-examination-worklist-title">
    <h1 id="basic-examination-worklist-title">Basic-examination worklist</h1>
    <p class="muted">Advance-booking paper tickets admitted to the active site's assigned shifts, ordered by ready time.</p>
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
                    <tr><td colspan="6" class="muted">No advance-booking tickets are waiting for basic examination.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
