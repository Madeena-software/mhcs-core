@extends('operator.layout')

@section('title', 'DICOM results worklist')

@section('content')
<section aria-labelledby="dicom-results-title">
    <h1 id="dicom-results-title">DICOM results worklist</h1>
    <p class="muted">Accepted local synthetic studies available to this active site and current shift.</p>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Study</th>
                    <th>Format</th>
                    <th>Dimensions</th>
                    <th>Accepted</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($studies as $study)
                    <tr>
                        <td>{{ $study['study_id'] }}</td>
                        <td>{{ $study['format'] }}</td>
                        <td>{{ $study['columns'] }} × {{ $study['rows'] }}</td>
                        <td><time datetime="{{ $study['accepted_at'] }}">{{ $study['accepted_at'] }}</time></td>
                        <td><a href="{{ route('operator.study.show', $study['study_id']) }}">Open DICOM study</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No accepted DICOM studies are available for this site and shift.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
