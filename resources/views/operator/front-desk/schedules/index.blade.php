@extends('operator.layout')

@section('title', __('Kelola Jadwal'))

@section('content')
<section aria-labelledby="front-desk-schedules-title">
    <p class="eyebrow">{{ __('Front desk') }}</p>
    <h1 id="front-desk-schedules-title">{{ __('Kelola Jadwal') }}</h1>
    <p class="muted">{{ $site['display_name'] }} · {{ $site['code'] }} · {{ $site['timezone'] }}</p>
    <p><a class="primary-action" href="{{ route('operator.schedules.create') }}">{{ __('Tambah Jadwal') }}</a></p>

    <section class="card">
        <h2>{{ __('Jadwal di site aktif') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Referensi') }}</th><th>{{ __('Layanan') }}</th><th>{{ __('Waktu') }}</th><th>{{ __('Peserta') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td><code>{{ $schedule['display_reference'] }}</code></td>
                        <td>{{ $schedule['service_name'] }}<br><span class="muted">{{ $schedule['service_code'] }}</span></td>
                        <td>{{ $schedule['starts_at'] }}<br>{{ $schedule['ends_at'] }}</td>
                        <td>{{ $schedule['participant_count'] }} / {{ $schedule['quota'] }}</td>
                        <td class="status">{{ __($schedule['status']) }}</td>
                        <td><a href="{{ route('operator.schedules.show', $schedule['id']) }}">{{ __('Buka Peserta') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('Belum ada jadwal di site aktif.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
