@extends('operator.layout')

@section('title', __('DICOM results worklist'))

@section('content')
<section aria-labelledby="dicom-results-title">
    <h1 id="dicom-results-title">{{ __('DICOM results worklist') }}</h1>
    <p class="muted">{{ __('Accepted studies available to this active site and current shift.') }}</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Study') }}</th>
                    <th>{{ __('Format') }}</th>
                    <th>{{ __('Dimensions') }}</th>
                    <th>{{ __('Accepted') }}</th>
                    <th>{{ __('Action') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($studies as $study)
                    <tr>
                        <td>{{ $study['study_id'] }}</td>
                        <td>{{ $study['format'] }}</td>
                        <td>{{ $study['columns'] }} × {{ $study['rows'] }}</td>
                        <td><time datetime="{{ $study['accepted_at'] }}">{{ $study['accepted_at'] }}</time></td>
                        <td><a href="{{ route('operator.study.show', $study['study_id']) }}">{{ __('Open DICOM study') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('No accepted DICOM studies are available for this site and shift.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
