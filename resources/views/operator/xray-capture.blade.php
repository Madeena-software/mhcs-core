@extends('operator.layout')

@section('title', 'Submit synthetic capture')

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">Submit synthetic capture</h1>
    <p class="muted">X-ray admission <code>{{ $admissionId }}</code>; upload only the repository-owned synthetic pair.</p>
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="synthetic-capture-form">
        @csrf
        <label for="radiographs">Radiograph NPZ ({{ $form['radiograph_name'] }})</label>
        <input id="radiographs" name="radiographs[]" type="file" accept=".npz,application/octet-stream" multiple required>
        <label for="gain">Matching gain NPZ ({{ $form['gain_name'] }})</label>
        <input id="gain" name="gain" type="file" accept=".npz,application/octet-stream" required>
        <input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::uuid() }}">
        <button type="submit">Submit complete capture set</button>
    </form>
</section>
<script>
    (() => {
        const form = document.getElementById('synthetic-capture-form');
        const inputs = [...form.querySelectorAll('input[type="file"]')];
        const hasFiles = () => inputs.some((input) => input.files.length > 0);
        form.addEventListener('submit', () => { window.__mhcsCaptureSubmitted = true; });
        window.addEventListener('beforeunload', (event) => {
            if (!window.__mhcsCaptureSubmitted && hasFiles()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    })();
</script>
@endsection
