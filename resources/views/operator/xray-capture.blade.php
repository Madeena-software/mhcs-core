@extends('operator.layout')

@section('title', __('Submit radiograph capture'))

@section('content')
<section aria-labelledby="xray-capture-title">
    <h1 id="xray-capture-title">{{ __('Submit radiograph capture') }}</h1>
    <p class="muted">{{ __('Radiography admission') }} <code>{{ $admissionId }}</code>; {{ __('Upload one radiograph NPZ and its matching gain NPZ.') }}</p>
    <p class="warning" role="alert">{{ __('Keep this page open until the upload status is complete.') }}</p>
    <p id="capture-status" role="status" data-progress="{{ __('Uploading radiograph and gain. Keep this page open.') }}" data-success="{{ __('Capture upload completed.') }}" data-error="{{ __('The capture upload failed. Retry only the missing file.') }}"></p>
    @if ($form['missing'] !== [])
        <p class="muted">{{ __('Missing capture files:') }} {{ implode(', ', array_map(static fn (string $type): string => __($type === 'radiograph' ? 'Radiograph NPZ' : 'Matching gain NPZ'), $form['missing'])) }}</p>
    @else
        <p class="muted">{{ __('This capture is complete.') }}</p>
    @endif
    <form method="POST" action="{{ route('operator.xray-capture.store', $admissionId) }}" enctype="multipart/form-data" id="capture-form" data-missing="{{ implode(',', $form['missing']) }}">
        @csrf
        @if (in_array('radiograph', $form['missing'], true))
            <label for="radiograph_npz">{{ __('Radiograph NPZ') }}</label>
            <input id="radiograph_npz" name="radiograph_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        @if (in_array('gain', $form['missing'], true))
            <label for="gain_npz">{{ __('Matching gain NPZ') }}</label>
            <input id="gain_npz" name="gain_npz" type="file" accept=".npz,application/octet-stream" required>
        @endif
        <input type="hidden" name="submission_id" value="{{ $form['submission_id'] }}">
        @if ($form['missing'] !== [])
            <button type="submit">{{ __('Submit capture set') }}</button>
        @endif
    </form>
</section>
<script>
    (() => {
        const form = document.getElementById('capture-form');
        if (!form) return;
        const status = document.getElementById('capture-status');
        let active = false;
        let terminal = false;
        window.addEventListener('beforeunload', (event) => {
            if (active && !terminal) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm(@json(__('Keep this page open until the upload status is complete.')))) return;
            active = true;
            status.textContent = status.dataset.progress;
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const result = await response.json();
                terminal = true;
                status.textContent = result.missing?.length ? status.dataset.error : status.dataset.success;
                if (result.missing?.length) window.location.reload();
            } catch (error) {
                terminal = true;
                status.textContent = status.dataset.error;
            } finally {
                active = false;
                if (button) button.disabled = false;
            }
        });
    })();
</script>
@endsection
