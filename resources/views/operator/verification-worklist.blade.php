@extends('operator.layout')

@section('title', __('Verification worklist'))

@section('content')
<section aria-labelledby="worklist-title">
    <h1 id="worklist-title">{{ __('Verification worklist') }}</h1>
    <p class="muted">{{ __('Arrivals recorded at the active site and awaiting the next verification slice.') }}</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Medical record') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Recorded by') }}</th><th>{{ __('Occurrence') }}</th><th>{{ __('Status') }}</th><th>{{ __('Verification') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse ($arrivals as $arrival)
                    <tr>
                        <td>{{ $arrival['member_name'] }}</td>
                        <td>{{ $arrival['medical_record_number'] ?? __('Withheld') }}</td>
                        <td><code>{{ $arrival['booking_id'] }}</code></td>
                        <td>{{ $arrival['operator_name'] }}</td>
                        <td>{{ $arrival['occurrence_at'] }}</td>
                        <td class="status">{{ __($arrival['status']) }}</td>
                        <td>{{ __($arrival['verification_state']) }}</td>
                        <td>
                            @if ($canVerify && $arrival['verification_state'] === 'unclaimed')
                                <form method="POST" action="{{ route('operator.identity-verification.start') }}">
                                    @csrf
                                    <input type="hidden" name="arrival_id" value="{{ $arrival['arrival_id'] }}">
                                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                                <button type="submit">{{ __('Start verification') }}</button>
                                </form>
                            @elseif ($arrival['can_open_verification'])
                                <a href="{{ route('operator.identity-verification.show', $arrival['verification_case_id']) }}">{{ __('Open case') }}</a>
                            @else
                                <span class="muted">{{ __('Unavailable') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">{{ __('No arrivals await verification.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
