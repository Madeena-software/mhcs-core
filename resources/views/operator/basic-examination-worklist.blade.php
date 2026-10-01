@extends('operator.layout')

@section('title', __('Basic-examination worklist'))

@section('content')
<section aria-labelledby="basic-examination-worklist-title" data-worklist-auto-refresh>
    <h1 id="basic-examination-worklist-title">{{ __('Basic-examination worklist') }}</h1>
    <p class="muted">{{ __('Advance-booking paper tickets admitted to the active site\'s assigned shifts, ordered by ready time.') }}</p>
    <p><a href="{{ route('operator.xray-readiness-worklist') }}">{{ __('View radiography session readiness worklist') }}</a></p>

    <div class="worklist-filter-bar" data-worklist-filter-bar data-target-table="#basic-examination-table">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="filter-query">{{ __('Cari Pasien / Referensi') }}</label>
                <input type="search" id="filter-query" data-filter-query placeholder="{{ __('Nama, No. RM, Tiket...') }}">
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
            <table id="basic-examination-table">
                <thead>
                <tr>
                    <th>{{ __('Paper ticket') }}</th>
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
                        data-search-text="{{ strtolower($entry['member_name'].' '.$entry['ticket_number'].' '.$entry['medical_record_number'].' '.$entry['site_name'].' '.$entry['schedule_display_reference']) }}"
                        data-row-date="{{ substr((string) $entry['ready_at'], 0, 10) }}"
                        data-row-status="{{ $entry['state'] }}">
                        <td>{{ in_array($entry['state'], ['called', 'in_service'], true) ? __('Current claimed admission') : $entry['ticket_number'] }}</td>
                        <td>{{ $entry['member_name'] }}</td>
                        <td>{{ $entry['medical_record_number'] }}</td>
                        <td>{{ $entry['site_name'] }}</td>
                        <td>{{ $entry['schedule_display_reference'] }}</td>
                        <td>{{ __($entry['stage']) }}</td>
                        <td class="status">{{ __($entry['state']) }}</td>
                        <td><time datetime="{{ $entry['ready_at'] }}">{{ $entry['ready_at'] }}</time></td>
                        <td>
                            @if ($entry['claimed_by_current_operator'])
                                <span class="status">{{ __('Claimed by you') }}</span>
                                @if ($entry['state'] === 'in_service')
                                    @if ($entry['is_nonclinical_validation'])
                                        @if ($entry['can_complete_nonclinical_validation'])
                                            <form method="POST" action="{{ route('operator.basic-examination-worklist.complete-nonclinical', $entry['admission_id']) }}">
                                                @csrf
                                                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                <button type="submit">{{ __('Complete nonclinical validation stage') }}</button>
                                            </form>
                                        @endif
                                    @else
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
                            <form method="POST" action="{{ route('operator.basic-examination-worklist.bypass', $entry['admission_id']) }}" style="margin-top: 4px;">
                                @csrf
                                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="secondary">{{ __('Bypass to X-ray') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr data-empty-initial-row><td colspan="9" class="muted">{{ __('No advance-booking tickets are waiting for basic examination.') }}</td></tr>
                @endforelse
                <tr data-empty-filtered-row hidden><td colspan="9" class="muted">{{ __('Tidak ada data yang sesuai dengan filter.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
