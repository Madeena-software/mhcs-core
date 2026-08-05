@extends('member.layout')

@section('title', 'Dashboard Member')

@section('content')
<section aria-labelledby="dashboard-title">
    <h1 id="dashboard-title">Dashboard Member</h1>
    <p>Selamat datang, {{ $memberName }}.</p>

    <dl class="summary">
        <div><dt>Nomor rekam medis</dt><dd>{{ $medicalRecordNumber }}</dd></div>
        <div><dt>Kelengkapan profil</dt><dd>{{ $completionPercentage }}%</dd></div>
        <div><dt>Status identitas</dt><dd>{{ $identityStatus }}</dd></div>
        <div><dt>Status akun</dt><dd>{{ $accountStatus }}</dd></div>
    </dl>

    <section class="card" aria-labelledby="services-title">
        <h2 id="services-title">Layanan berikutnya</h2>
        <p class="muted">Jadwalkan Sesi Foto Radiografi dan lihat status sesi Anda.</p>
        <div class="actions">
            <a href="{{ route('member.services') }}">Jadwalkan Sesi Foto Radiografi</a>
            <a href="{{ route('member.bookings') }}">Lihat Sesi Saya</a>
        </div>
    </section>
</section>
@endsection
