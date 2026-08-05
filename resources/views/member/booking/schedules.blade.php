@extends('member.layout')

@section('title', 'Jadwal Sesi Foto Radiografi')

@section('content')
<section aria-labelledby="schedules-title">
    <h1 id="schedules-title">Jadwal Sesi Foto Radiografi</h1>
    @if ($schedules->isEmpty())
        <div class="card"><p class="muted">Belum ada Sesi Foto Radiografi yang dijadwalkan. Silakan pilih layanan terlebih dahulu.</p><a href="{{ route('member.services') }}">Pilih layanan</a></div>
    @else
        @foreach ($schedules as $schedule)
            <article class="card" style="margin: 12px 0">
                <h2>{{ $schedule->service->name }}</h2>
                <p>{{ $schedule->site->display_name }} · {{ $schedule->starts_at->setTimezone($schedule->site->timezone)->translatedFormat('l, j F Y') }} pukul {{ $schedule->starts_at->setTimezone($schedule->site->timezone)->format('H.i') }}</p>
                <p class="muted">Sisa kuota: {{ $schedule->remaining_capacity }} dari {{ $schedule->quota }}</p>
                <a href="{{ route('member.services.show', $schedule->service->getKey()) }}">Lihat detail layanan</a>
            </article>
        @endforeach
    @endif
</section>
@endsection
