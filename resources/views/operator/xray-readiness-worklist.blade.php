@extends('operator.layout')

@section('title', __('Radiography session readiness worklist'))

@section('content')
<section aria-labelledby="xray-readiness-worklist-title" data-worklist-auto-refresh>
    <h1 id="xray-readiness-worklist-title">{{ __('Radiography session readiness worklist') }}</h1>
    <p class="muted">{{ __('Radiography session tickets for the active site\'s assigned shifts, ordered by ready time.') }}</p>
    <p><a href="{{ route('operator.basic-examination-worklist') }}">{{ __('View basic-examination worklist') }}</a></p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Paper ticket') }}</th>
                    <th>{{ __('Site') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th>{{ __('Stage') }}</th>
                    <th>{{ __('State') }}</th>
                    <th>{{ __('Ready time') }}</th>
                    <th>{{ __('Action') }}</th>
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
                        <td>{{ __($entry['stage']) }}</td>
                        <td class="status">{{ $entry['capture_processing_failed'] ? __('DICOM processing failed') : __($entry['state']) }}</td>
                        <td><time datetime="{{ $entry['ready_at'] }}">{{ $entry['ready_at'] }}</time></td>
                        <td>
                            @if ($entry['capture_processing_failed'])
                                <a href="{{ route('operator.xray-capture.show', $entry['admission_id']) }}">{{ __('Retry DICOM processing') }}</a>
                            @elseif ($entry['claimed_by_current_operator'])
                                @if ($entry['state'] === 'waiting')
                                    <form method="POST" action="{{ route('operator.xray-readiness-worklist.call', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">{{ __('Call') }}</button>
                                    </form>
                                    <span class="status">{{ __('Claimed by you') }}</span>
                                @else
                                    <a href="{{ route('operator.xray-capture.show', $entry['admission_id']) }}">{{ __('Submit radiograph capture') }}</a>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operator.xray-readiness-worklist.claim', $entry['admission_id']) }}">
                                    @csrf
                                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">{{ __('Claim') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('No radiography session tickets are ready.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
