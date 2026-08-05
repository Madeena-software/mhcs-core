@extends('member.layout')

@section('title', 'Detail Sesi Foto Radiografi')

@section('content')
<section aria-labelledby="booking-title">
    <h1 id="booking-title">Detail Sesi Foto Radiografi</h1>
    <div class="card">
        <dl class="summary">
            <div><dt>Layanan</dt><dd>{{ $booking->service_code_snapshot }}</dd></div>
            <div><dt>Lokasi</dt><dd>{{ $booking->site_name_snapshot }}</dd></div>
            <div><dt>Jadwal</dt><dd>{{ $booking->schedule->starts_at->setTimezone($booking->site_timezone_snapshot)->translatedFormat('l, j F Y') }} pukul {{ $booking->schedule->starts_at->setTimezone($booking->site_timezone_snapshot)->format('H.i') }}</dd></div>
            <div><dt>Status</dt><dd>{{ $booking->status === 'confirmed' ? 'Sesi Foto Radiografi Terjadwal' : $booking->status }}</dd></div>
            <div><dt>Harga</dt><dd>{{ $booking->pointCost() }} Madeena Points</dd></div>
            <div><dt>Sumber pembayaran</dt><dd>Madeena Points pribadi</dd></div>
            <div><dt>Bantuan interpretasi otomatis</dt><dd>{{ $booking->includes_ai_snapshot ? 'Termasuk' : 'Tidak termasuk' }}</dd></div>
            <div><dt>Peninjauan dokter</dt><dd>{{ $booking->includes_doctor_snapshot ? 'Termasuk' : 'Tidak termasuk' }}</dd></div>
        </dl>
        <p class="muted">Nomor referensi lokal: {{ $booking->imagingOrder?->id }}</p>
    </div>
</section>
@endsection
