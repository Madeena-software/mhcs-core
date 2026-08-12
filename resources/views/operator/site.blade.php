@extends('operator.layout')

@section('title', __('Active site'))

@section('content')
<section aria-labelledby="site-title">
    <h1 id="site-title">{{ __('Active site') }}</h1>
    <p class="muted">{{ __('Attendance and arrival actions are scoped to the selected site.') }}</p>
    <section class="card">
        @if ($sites)
            <form method="POST" action="{{ route('operator.site.select') }}">
                @csrf
                <label for="site_id">{{ __('Authorized site') }}</label>
                <select id="site_id" name="site_id" required>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected($activeSite && $activeSite->id === $site->id)>{{ $site->display_name }} ({{ $site->code }}) — {{ $site->timezone }}</option>
                    @endforeach
                </select>
                <div class="actions"><button type="submit">{{ __('Set active site') }}</button></div>
            </form>
        @else
            <p class="muted">{{ __('No active site assignment is available. Contact an administrator.') }}</p>
        @endif
    </section>
</section>
@endsection
