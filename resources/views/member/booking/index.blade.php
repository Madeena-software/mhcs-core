@extends('member.layout')

@section('title', 'Riwayat Sesi Foto Radiografi')

@section('content')
<section aria-labelledby="bookings-title">
    <h1 id="bookings-title">Riwayat Sesi Foto Radiografi</h1>
    @if ($bookings->isEmpty())
        <div class="card"><p class="muted">Belum ada Sesi Foto Radiografi yang tercatat.</p><a href="{{ route('member.services') }}">Jadwalkan Sesi Foto Radiografi</a></div>
    @else
        <div class="summary">
            @foreach ($bookings as $booking)
                <article class="card">
                    <h2>{{ $booking->service_code_snapshot }}</h2>
                    <p>{{ $booking->site_name_snapshot }}</p>
                    <p>Status: <strong>{{ $booking->status === 'confirmed' ? 'Sesi Foto Radiografi Terjadwal' : $booking->status }}</strong></p>
                    <a href="{{ route('member.bookings.show', $booking->getKey()) }}">Lihat Detail Sesi</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
