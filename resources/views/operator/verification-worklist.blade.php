@extends('operator.layout')

@section('title', __('Verification worklist'))

@section('content')
<section aria-labelledby="worklist-title" data-worklist-auto-refresh>
    <h1 id="worklist-title">{{ __('Verification worklist') }}</h1>
    <p class="muted">{{ __('Arrivals recorded at the active site and awaiting the next verification slice.') }}</p>

    <div class="worklist-filter-bar" data-worklist-filter-bar data-target-table="#verification-table">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="filter-query">{{ __('Cari Pasien / Referensi') }}</label>
                <input type="search" id="filter-query" data-filter-query placeholder="{{ __('Nama, No. RM, ID Booking...') }}">
            </div>
            <div class="filter-group">
                <label for="filter-date-from">{{ __('Tanggal Kedatangan Mulai') }}</label>
                <input type="date" id="filter-date-from" data-filter-date-from>
            </div>
            <div class="filter-group">
                <label for="filter-date-to">{{ __('Tanggal Kedatangan Sampai') }}</label>
                <input type="date" id="filter-date-to" data-filter-date-to>
            </div>
            <div class="filter-group">
                <label for="filter-status">{{ __('Status Verifikasi') }}</label>
                <select id="filter-status" data-filter-status>
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="unclaimed">{{ __('Belum diklaim') }}</option>
                    <option value="open">{{ __('Terbuka') }}</option>
                    <option value="verified">{{ __('Terverifikasi') }}</option>
                    <option value="pending_verification">{{ __('Menunggu verifikasi') }}</option>
                    <option value="refused">{{ __('Menolak') }}</option>
                    <option value="cancelled">{{ __('Dibatalkan') }}</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="button" class="secondary" data-filter-reset>{{ __('Atur Ulang') }}</button>
            <span class="filter-count" data-filter-count hidden></span>
        </div>
    </div>

    <section class="card">
        <div class="table-wrap">
            <table id="verification-table">
                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Medical record') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Recorded by') }}</th><th>{{ __('Occurrence') }}</th><th>{{ __('Status') }}</th><th>{{ __('Verification') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse ($arrivals as $arrival)
                    <tr data-worklist-row
                        data-search-text="{{ strtolower($arrival['member_name'].' '.($arrival['medical_record_number'] ?? '').' '.$arrival['booking_id'].' '.$arrival['operator_name']) }}"
                        data-row-date="{{ substr((string) $arrival['occurrence_at'], 0, 10) }}"
                        data-row-status="{{ $arrival['verification_state'] }}">
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
                    <tr data-empty-initial-row><td colspan="8" class="muted">{{ __('No arrivals await verification.') }}</td></tr>
                @endforelse
                <tr data-empty-filtered-row hidden><td colspan="8" class="muted">{{ __('Tidak ada data yang sesuai dengan filter.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
