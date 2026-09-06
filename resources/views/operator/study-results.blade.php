@extends('operator.layout')

@section('title', __('DICOM results worklist'))

@section('content')
<section aria-labelledby="dicom-results-title">
    <h1 id="dicom-results-title">{{ __('DICOM results worklist') }}</h1>
    <p class="muted">{{ __('Accepted studies available to this active site and current shift.') }}</p>
    <form method="POST" action="{{ route('operator.study.batch-download') }}" data-study-selection>
        @csrf
        <div class="actions">
            <label><input type="checkbox" data-select-all id="select-all-studies"> {{ __('Select all displayed studies') }}</label>
            <button type="submit">{{ __('Download selected') }}</button>
        </div>
        <section class="card">
        <div class="table-wrap">
            <table>
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
                    <th>{{ __('Laporan AI') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($studies as $study)
                    <tr>
                        <td><input type="checkbox" name="studies[]" value="{{ $study['study_id'] }}" aria-label="{{ __('Select study :reference', ['reference' => $study['display_reference']]) }}"></td>
                        <td><strong>{{ $study['display_reference'] }}</strong></td>
                        <td>{{ $study['member_name'] }}</td>
                        <td>{{ $study['ticket_number'] }}</td>
                        <td>{{ $study['medical_record_number'] }}</td>
                        <td>{{ $study['schedule_display_reference'] }}</td>
                        <td>{{ $study['format'] }}</td>
                        <td><time datetime="{{ $study['accepted_at'] }}">{{ $study['accepted_at'] }}</time></td>
                        <td><a href="{{ route('operator.study.show', $study['study_id']) }}">{{ __('Open DICOM study') }}</a></td>
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
                                        <form method="POST" action="{{ route('operator.study.ai-report.retry', $study['study_id']) }}" style="margin-top: 4px;">
                                            @csrf
                                            <button type="submit" class="secondary" style="padding: 4px 10px; font-size: 12px;">{{ __('Coba Lagi') }}</button>
                                        </form>
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
                    <tr><td colspan="10" class="muted">{{ __('No accepted DICOM studies are available for this site and shift.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </section>
    </form>
    <script>
    (() => {
        const form = document.querySelector('[data-study-selection]');
        if (!form) return;
        const all = form.querySelector('[data-select-all]');
        const studies = () => [...form.querySelectorAll('input[name="studies[]"]')];
        all.addEventListener('change', () => studies().forEach((study) => { study.checked = all.checked; }));
        form.addEventListener('submit', (event) => { if (!studies().some((study) => study.checked)) event.preventDefault(); });
    })();
    </script>
</section>
@endsection
