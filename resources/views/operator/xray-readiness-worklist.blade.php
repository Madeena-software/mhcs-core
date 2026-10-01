@extends('operator.layout')

@section('title', __('Radiography session readiness worklist'))

@section('content')
<section aria-labelledby="xray-readiness-worklist-title" data-worklist-auto-refresh>
    <h1 id="xray-readiness-worklist-title">{{ __('Radiography session readiness worklist') }}</h1>
    <p class="muted">{{ __('Radiography session tickets for the active site\'s assigned shifts, ordered by ready time.') }}</p>
    <p><a href="{{ route('operator.basic-examination-worklist') }}">{{ __('View basic-examination worklist') }}</a></p>

    <div class="worklist-filter-bar" data-worklist-filter-bar data-target-table="#xray-readiness-table">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="filter-query">{{ __('Cari Pasien / Referensi') }}</label>
                <input type="search" id="filter-query" data-filter-query placeholder="{{ __('Nama, No. RM, Tiket, Kode Sesi (0042)...') }}">
            </div>
            <div class="filter-group">
                <label for="filter-date-from">{{ __('Waktu Siap Mulai') }}</label>
                <input type="date" id="filter-date-from" data-filter-date-from>
            </div>
            <div class="filter-group">
                <label for="filter-date-to">{{ __('Waktu Siap Sampai') }}</label>
                <input type="date" id="filter-date-to" data-filter-date-to>
            </div>
            <div class="filter-group">
                <label for="filter-status">{{ __('Status Operasional') }}</label>
                <select id="filter-status" data-filter-status>
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="waiting">{{ __('Menunggu') }}</option>
                    <option value="called">{{ __('Dipanggil') }}</option>
                    <option value="in_service">{{ __('Sedang dilayani') }}</option>
                    <option value="completed">{{ __('Selesai') }}</option>
                    <option value="dicom_processing_failed">{{ __('DICOM processing failed') }}</option>
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
            <table id="xray-readiness-table">
                <thead>
                <tr>
                    <th>{{ __('Paper ticket') }}</th>
                    <th>{{ __('Session code') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Medical record') }}</th>
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
                    <tr data-worklist-row
                        data-search-text="{{ strtolower($entry['member_name'].' '.$entry['ticket_number'].' '.($entry['locator_code'] ?? '').' '.$entry['medical_record_number'].' '.$entry['site_name'].' '.$entry['schedule_display_reference']) }}"
                        data-row-date="{{ substr((string) $entry['ready_at'], 0, 10) }}"
                        data-row-status="{{ $entry['capture_processing_failed'] ? 'dicom_processing_failed' : $entry['state'] }}">
                        <td>{{ $entry['ticket_number'] }}</td>
                        <td><code>{{ $entry['locator_code'] ?: '—' }}</code></td>
                        <td>{{ $entry['member_name'] }}</td>
                        <td>{{ $entry['medical_record_number'] }}</td>
                        <td>{{ $entry['site_name'] }}</td>
                        <td>{{ $entry['schedule_display_reference'] }}</td>
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
                    <tr data-empty-initial-row><td colspan="10" class="muted">{{ __('No radiography session tickets are ready.') }}</td></tr>
                @endforelse
                <tr data-empty-filtered-row hidden><td colspan="10" class="muted">{{ __('Tidak ada data yang sesuai dengan filter.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
