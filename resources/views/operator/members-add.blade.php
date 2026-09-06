@extends('operator.layout')

@section('title', __('Add Member to Active Shift'))

@section('content')
<section aria-labelledby="add-member-title">
    <h1 id="add-member-title">{{ __('Add Member to Shift') }}</h1>
    <p class="muted">{{ $activeSite ? $activeSite->display_name.' · ' : '' }}{{ __('Shift Reference') }}: <strong>{{ $schedule->display_reference }}</strong></p>

    <div style="margin-bottom: 20px">
        <a href="{{ route('operator.shifts.members.register', $schedule->id) }}" class="btn">{{ __('+ On-the-Spot New Member Registration') }}</a>
        <a href="{{ route('operator.attendance', ['schedule' => $schedule->id, 'at' => now()->format(DATE_ATOM)]) }}" class="btn-secondary" style="margin-left: 10px">{{ __('Back to Attendance') }}</a>
    </div>

    <section class="card">
        <h2>{{ __('Search Existing Members') }}</h2>
        <p class="muted">{{ __('Search by 16-digit NIK, Medical Record Number (MRN), or full name.') }}</p>

        <form method="GET" action="{{ route('operator.shifts.members.search', $schedule->id) }}">
            <div style="display: flex; gap: 10px">
                <input type="text" name="q" value="{{ request('q', request('query', old('q'))) }}" placeholder="{{ __('Enter NIK, MRN, or Name...') }}" required style="flex: 1">
                <button type="submit">{{ __('Search') }}</button>
            </div>
        </form>

        @if (isset($results) && $results !== null)
            <div class="table-wrap" style="margin-top: 20px">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('MRN') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Birth Date') }}</th>
                            <th>{{ __('Gender') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $res)
                            <tr>
                                <td><strong>{{ $res['medical_record_number'] }}</strong></td>
                                <td>{{ $res['name'] }}</td>
                                <td>{{ $res['birth_date'] }}</td>
                                <td>{{ $res['administrative_gender'] }}</td>
                                <td>
                                    <form method="POST" action="{{ route('operator.shifts.members.add-existing', $schedule->id) }}">
                                        @csrf
                                        <input type="hidden" name="member_id" value="{{ $res['member_id'] }}">
                                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit">{{ __('Add to Shift') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">{{ __('No members found matching your search.') }} <a href="{{ route('operator.shifts.members.register', $schedule->id) }}">{{ __('Register this member now.') }}</a></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</section>
@endsection
