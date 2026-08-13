@extends('operator.layout')

@section('title', __('Attendance list'))

@section('content')
<section aria-labelledby="attendance-title">
    <h1 id="attendance-title">{{ __('Attendance list') }}</h1>
    <p class="muted">{{ $site->display_name }} · {{ __('schedule') }} <code>{{ $scheduleId }}</code> · {{ __(':count eligible members', ['count' => count($rows)]) }}</p>
    <section class="card" style="margin-top: 18px">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Medical record') }}</th><th>{{ __('Service') }}</th><th>{{ __('Operational state') }}</th><th>{{ __('Next safe action') }}</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['member_name'] }}<br><span class="muted">{{ __('NIK:') }} {{ $row['nik'] ?? __('Identifier withheld') }}</span></td>
                        <td>{{ $row['medical_record_number'] }}</td>
                        <td>{{ $row['service_name'] }} ({{ $row['service_code'] }})</td>
                        <td class="status">{{ __($row['booking_status']) }}</td>
                        <td>
                            @if ($row['booking_status'] === 'confirmed')
                                <form method="POST" action="{{ route('operator.arrivals.confirm') }}">
                                    @csrf
                                    <input type="hidden" name="booking_id" value="{{ $row['booking_id'] }}">
                                    <input type="hidden" name="occurrence_at" value="{{ $at }}">
                                    <button type="submit">{{ __($row['next_action']) }}</button>
                                </form>
                            @elseif ($row['booking_status'] === 'arrived')
                                <a href="{{ route('operator.verification-worklist') }}">{{ __($row['next_action']) }}</a>
                            @elseif (in_array($row['booking_status'], ['checked_in', 'in_progress'], true))
                                @if (($row['returned_study_count'] ?? 0) === 1)
                                    <a href="{{ route('operator.study.show', $row['returned_study_id']) }}">{{ __($row['next_action']) }}</a>
                                @elseif (($row['returned_study_count'] ?? 0) > 1)
                                    <a href="{{ route('operator.study.results') }}">{{ __($row['next_action']) }}</a>
                                @else
                                    <a href="{{ route('operator.basic-examination-worklist') }}">{{ __($row['next_action']) }}</a>
                                @endif
                            @else
                                <span class="muted">{{ __($row['next_action']) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('No eligible clinic-day bookings were found for this attendance window.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
