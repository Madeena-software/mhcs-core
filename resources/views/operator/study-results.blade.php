@extends('operator.layout')

@section('title', __('DICOM results worklist'))

@section('content')
<section aria-labelledby="dicom-results-title">
    <h1 id="dicom-results-title">{{ __('DICOM results worklist') }}</h1>
    <p class="muted">{{ __('Accepted studies available to this active site and current shift.') }}</p>

    <div class="worklist-filter-bar" data-worklist-filter-bar data-target-table="#dicom-results-table">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="filter-query">{{ __('Cari Pasien / Referensi') }}</label>
                <input type="search" id="filter-query" data-filter-query placeholder="{{ __('Nama, No. RM, Tiket, Studi...') }}">
            </div>
            <div class="filter-group">
                <label for="filter-date-from">{{ __('Tanggal Diterima Mulai') }}</label>
                <input type="date" id="filter-date-from" data-filter-date-from>
            </div>
            <div class="filter-group">
                <label for="filter-date-to">{{ __('Tanggal Diterima Sampai') }}</label>
                <input type="date" id="filter-date-to" data-filter-date-to>
            </div>
            <div class="filter-group">
                <label for="filter-status">{{ __('Status Laporan AI') }}</label>
                <select id="filter-status" data-filter-status>
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="report_ready">{{ __('Laporan Siap') }}</option>
                    <option value="queued">{{ __('Menunggu antrean') }}</option>
                    <option value="processing">{{ __('Sedang dianalisis') }}</option>
                    <option value="retryable_failure">{{ __('Gagal dan dapat dicoba ulang') }}</option>
                    <option value="terminal_failure">{{ __('Gagal') }}</option>
                    <option value="not_queued">{{ __('AI belum diantrikan') }}</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="button" class="secondary" data-filter-reset>{{ __('Atur Ulang') }}</button>
            <span class="filter-count" data-filter-count hidden></span>
            <span class="error" data-filter-error role="alert" hidden></span>
        </div>
    </div>

    <form method="POST" action="{{ route('operator.study.batch-download') }}" data-study-selection>
        @csrf
        <div class="actions">
            <label><input type="checkbox" data-select-all id="select-all-studies"> {{ __('Select all displayed studies') }}</label>
            <button type="submit">{{ __('Download selected') }}</button>
        </div>
        <section class="card">
        <div class="table-wrap">
            <table id="dicom-results-table">
                <thead>
                <tr>
                    <th>{{ __('Select') }}</th>
                    <th>{{ __('Study') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Paper ticket') }}</th>
                    <th>{{ __('Medical record') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th>{{ __('Format') }}</th>
                    <th>{{ __('Accepted') }}</th>
                    <th>{{ __('Action') }}</th>
                    <th>{{ __('Unduh PNG') }}</th>
                    <th>{{ __('Laporan AI') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($studies as $study)
                    @php($rowTime = \Carbon\CarbonImmutable::parse($study['accepted_at'], 'UTC')->setTimezone(config('app.timezone')))
                    <tr data-worklist-row
                        data-search-text="{{ strtolower($study['member_name'].' '.$study['display_reference'].' '.$study['ticket_number'].' '.$study['medical_record_number'].' '.$study['schedule_display_reference']) }}"
                        data-row-date="{{ $rowTime->format('Y-m-d') }}"
                        data-row-status="{{ $study['ai_state'] ?? 'not_queued' }}">
                        <td><input type="checkbox" name="studies[]" value="{{ $study['study_id'] }}" aria-label="{{ __('Select study :reference', ['reference' => $study['display_reference']]) }}"></td>
                        <td><strong>{{ $study['display_reference'] }}</strong></td>
                        <td>{{ $study['member_name'] }}</td>
                        <td>{{ $study['ticket_number'] }}</td>
                        <td>{{ $study['medical_record_number'] }}</td>
                        <td>{{ $study['schedule_display_reference'] }}</td>
                        <td>{{ $study['format'] }}</td>
                        <td><time datetime="{{ $rowTime->toIso8601String() }}">{{ $rowTime->format('Y-m-d H:i:s') }}</time></td>
                        <td><a href="{{ route('operator.study.show', $study['study_id']) }}">{{ __('Open DICOM study') }}</a></td>
                        <td>
                            <button type="button"
                                    class="secondary btn-png-download"
                                    data-png-download
                                    data-png-messages="{{ json_encode(['processing' => __('Memproses...'), 'saving' => __('Menyimpan...'), 'done' => __('Selesai'), 'error' => __('Gagal mengunduh gambar PNG. Pastikan berkas studi tersedia.')]) }}"
                                    data-dicom-url="{{ route('operator.study.dicom', $study['study_id']) }}"
                                    data-reference="{{ $study['display_reference'] }}">
                                {{ __('Unduh PNG') }}
                            </button>
                        </td>
                        <td>
                            @if (($study['ai_state'] ?? null) === 'report_ready')
                                <div class="ai-report-action">
                                    <a href="{{ route('operator.study.ai-report.download', $study['study_id']) }}" class="primary-action" style="padding: 6px 12px; font-size: 13px;">{{ __('Unduh Laporan AI') }}</a>
                                    <p class="muted" style="margin: 4px 0 0; font-size: 11px;">{{ __('Keluaran AI — belum diverifikasi tenaga medis.') }}</p>
                                </div>
                            @elseif (($study['ai_state'] ?? null) === 'queued')
                                <span class="status muted">{{ __('Menunggu antrean') }}</span>
                                <p class="muted" style="margin: 4px 0 0; font-size: 11px;">{{ __('Keluaran AI — belum diverifikasi tenaga medis.') }}</p>
                            @elseif (($study['ai_state'] ?? null) === 'processing')
                                <span class="status muted">{{ __('Sedang dianalisis') }}</span>
                                <p class="muted" style="margin: 4px 0 0; font-size: 11px;">{{ __('Keluaran AI — belum diverifikasi tenaga medis.') }}</p>
                            @elseif (($study['ai_state'] ?? null) === 'retryable_failure')
                                <div class="ai-retry-action">
                                    <span class="error" style="display: block; font-size: 13px;">{{ __('Gagal dan dapat dicoba ulang') }}</span>
                                    @if ($study['ai_can_retry'] ?? true)
                                        <button type="submit" form="ai-retry-form-{{ $study['study_id'] }}" class="secondary" style="padding: 4px 10px; font-size: 12px; margin-top: 4px;">{{ __('Coba Lagi') }}</button>
                                    @endif
                                    <p class="muted" style="margin: 4px 0 0; font-size: 11px;">{{ __('Keluaran AI — belum diverifikasi tenaga medis.') }}</p>
                                </div>
                            @elseif (($study['ai_state'] ?? null) === 'terminal_failure')
                                <span class="error">{{ __('Gagal') }}</span>
                                <p class="muted" style="margin: 4px 0 0; font-size: 11px;">{{ __('Keluaran AI — belum diverifikasi tenaga medis.') }}</p>
                            @elseif (($study['ai_state'] ?? null) === 'not_queued')
                                <span class="muted">{{ __('AI belum diantrikan') }}</span>
                            @else
                                <span class="muted">{{ __('Tidak tersedia') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr data-empty-initial-row><td colspan="11" class="muted">{{ __('No accepted DICOM studies are available for this site and shift.') }}</td></tr>
                @endforelse
                <tr data-empty-filtered-row hidden><td colspan="11" class="muted">{{ __('Tidak ada data yang sesuai dengan filter.') }}</td></tr>
                </tbody>
            </table>
        </div>
        </section>
    </form>

    {{-- Unnested AI Retry Forms to keep HTML valid --}}
    @foreach ($studies as $study)
        @if (($study['ai_state'] ?? null) === 'retryable_failure' && ($study['ai_can_retry'] ?? true))
            <form id="ai-retry-form-{{ $study['study_id'] }}" method="POST" action="{{ route('operator.study.ai-report.retry', $study['study_id']) }}" hidden>
                @csrf
            </form>
        @endif
    @endforeach


</section>
@endsection
