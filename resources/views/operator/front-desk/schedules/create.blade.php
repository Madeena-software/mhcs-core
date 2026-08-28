@extends('operator.layout')

@section('title', __('Buat Jadwal'))

@section('content')
<section aria-labelledby="create-schedule-title">
    <p class="eyebrow">{{ __('Front desk') }}</p>
    <h1 id="create-schedule-title">{{ __('Buat Jadwal') }}</h1>
    <p class="muted">{{ $site['display_name'] }} · {{ __('Zona waktu') }}: {{ $site['timezone'] }}</p>
    <section class="card">
        <form method="POST" action="{{ route('operator.schedules.store') }}">
            @csrf
            <label for="service-offering">{{ __('Layanan aktif') }}</label>
            <select id="service-offering" name="service_offering_id" required>
                <option value="">{{ __('Pilih layanan') }}</option>
                @foreach ($services as $service)
                    <option value="{{ $service['id'] }}" @selected(old('service_offering_id') === $service['id'])>{{ $service['name'] }} ({{ $service['code'] }})</option>
                @endforeach
            </select>
            <label for="starts-at">{{ __('Mulai') }} ({{ $site['timezone'] }})</label>
            <input id="starts-at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
            <label for="ends-at">{{ __('Selesai') }} ({{ $site['timezone'] }})</label>
            <input id="ends-at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required>
            <label for="quota">{{ __('Kuota (1–500)') }}</label>
            <input id="quota" type="number" name="quota" min="1" max="500" value="{{ old('quota', 1) }}" required>
            <div class="actions">
                <button type="submit">{{ __('Simpan jadwal') }}</button>
                <a href="{{ route('operator.schedules.index') }}">{{ __('Batal') }}</a>
            </div>
        </form>
    </section>
</section>
@endsection
