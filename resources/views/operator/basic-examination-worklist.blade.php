@extends('operator.layout')

@section('title', __('Basic-examination worklist'))

@section('content')
<section aria-labelledby="basic-examination-worklist-title">
    <h1 id="basic-examination-worklist-title">{{ __('Basic-examination worklist') }}</h1>
    <p class="muted">{{ __('Advance-booking paper tickets admitted to the active site\'s assigned shifts, ordered by ready time.') }}</p>
    <p><a href="{{ route('operator.xray-readiness-worklist') }}">{{ __('View radiography session readiness worklist') }}</a></p>
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
                        <td>{{ in_array($entry['state'], ['called', 'in_service'], true) ? __('Current claimed admission') : $entry['ticket_number'] }}</td>
                        <td>{{ $entry['site_name'] }}</td>
                        <td>
                            <time datetime="{{ $entry['schedule_starts_at'] }}">{{ $entry['schedule_starts_at'] }}</time>
                            –
                            <time datetime="{{ $entry['schedule_ends_at'] }}">{{ $entry['schedule_ends_at'] }}</time>
                        </td>
                        <td>{{ __($entry['stage']) }}</td>
                        <td class="status">{{ __($entry['state']) }}</td>
                        <td><time datetime="{{ $entry['ready_at'] }}">{{ $entry['ready_at'] }}</time></td>
                        <td>
                            @if ($entry['claimed_by_current_operator'])
                                <span class="status">{{ __('Claimed by you') }}</span>
                                @if ($entry['state'] === 'in_service')
                                    @if (! $entry['has_vital_signs_execution'])
                                        <a href="{{ route('operator.basic-examination-worklist.vital-signs', $entry['admission_id']) }}">{{ __('Record vital signs') }}</a>
                                    @endif
                                    @if (! $entry['has_questionnaire'])
                                        <a href="{{ route('operator.basic-examination-worklist.questionnaire', $entry['admission_id']) }}">{{ __('Upload paper questionnaire') }}</a>
                                    @endif
                                    @if ($entry['can_complete'])
                                        <form method="POST" action="{{ route('operator.basic-examination-worklist.complete', $entry['admission_id']) }}">
                                            @csrf
                                            <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <button type="submit">{{ __('Complete basic examination') }}</button>
                                        </form>
                                    @endif
                                @elseif ($entry['state'] === 'called')
                                    <form method="POST" action="{{ route('operator.basic-examination-worklist.start', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">{{ __('Start') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('operator.basic-examination-worklist.call', $entry['admission_id']) }}">
                                        @csrf
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">{{ __('Call') }}</button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operator.basic-examination-worklist.claim', $entry['admission_id']) }}">
                                    @csrf
                                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">{{ __('Claim') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('No advance-booking tickets are waiting for basic examination.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
