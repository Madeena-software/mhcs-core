@extends('operator.layout')

@section('title', __('One Stop MCU'))

@section('content')
<section aria-labelledby="mcu-title">
    <h1 id="mcu-title">{{ __('One Stop MCU screening') }}</h1>
    <p class="muted">{{ __('Screening uses the existing checked-in participant record and can finish without completing another workflow stage.') }}</p>
    <div class="card table-wrap">
        @if ($entries === [])
            <p>{{ __('No checked-in participants are available for MCU screening at this site.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Participant') }}</th><th>{{ __('MRN') }}</th><th>{{ __('Ticket') }}</th><th>{{ __('Shift') }}</th><th>{{ __('MCU screening') }}</th></tr></thead>
                <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry['member_name'] }}</td>
                        <td>{{ $entry['medical_record_number'] }}</td>
                        <td>{{ $entry['ticket_number'] }}</td>
                        <td>{{ $entry['schedule_reference'] }}</td>
                        <td>
                            @if ($entry['has_mcu_exam'])
                                <a href="{{ route('operator.one-stop-mcu.pdf', $entry['admission_id']) }}" target="_blank" rel="noopener">{{ __('Print / view MCU PDF') }}</a>
                                · <a href="{{ route('operator.one-stop-mcu.pdf', ['admission' => $entry['admission_id'], 'download' => 1]) }}">{{ __('Download MCU PDF') }}</a>
                            @else
                                <a href="{{ route('operator.one-stop-mcu.create', $entry['admission_id']) }}">{{ __('Enter MCU screening') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
@endsection
