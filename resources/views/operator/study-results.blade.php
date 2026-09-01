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
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">{{ __('No accepted DICOM studies are available for this site and shift.') }}</td></tr>
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
