@extends('operator.layout')

@section('title', __('Submit radiograph capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit radiograph capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }} <code>{{ $admissionId }}</code>; {{ __('Upload one radiograph NPZ and its matching gain NPZ.') }}</p>
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="capture-form">
        @csrf
        <label for="radiograph_npz">{{ __('Radiograph NPZ') }}</label>
        <input id="radiograph_npz" name="radiograph_npz" type="file" accept=".npz,application/octet-stream" required>
        <label for="gain_npz">{{ __('Matching gain NPZ') }}</label>
        <input id="gain_npz" name="gain_npz" type="file" accept=".npz,application/octet-stream" required>
        <input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::uuid() }}">
        <button type="submit">{{ __('Submit capture set') }}</button>
    </form>
</section>
<script>
    (() => {
        const form = document.getElementById('capture-form');
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
