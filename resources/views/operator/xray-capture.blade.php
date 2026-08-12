@extends('operator.layout')

@section('title', __('Submit synthetic capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit synthetic capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }} <code>{{ $admissionId }}</code>; {{ __('upload only the repository-owned synthetic pair.') }}</p>
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="synthetic-capture-form">
        @csrf
        <label for="radiographs">{{ __('Radiograph NPZ') }} ({{ $form['radiograph_name'] }})</label>
        <input id="radiographs" name="radiographs[]" type="file" accept=".npz,application/octet-stream" multiple required>
        <label for="gain">{{ __('Matching gain NPZ') }} ({{ $form['gain_name'] }})</label>
        <input id="gain" name="gain" type="file" accept=".npz,application/octet-stream" required>
        <input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::uuid() }}">
        <button type="submit">{{ __('Submit complete capture set') }}</button>
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
