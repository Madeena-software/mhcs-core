@extends('member.layout')

@section('title', 'Sesi Foto Radiografi Baru')

@section('content')
<section aria-labelledby="services-title">
    <h1 id="services-title">Sesi Foto Radiografi Baru</h1>
    <p>Pilih layanan yang sesuai dengan kebutuhan Anda. Harga menggunakan Madeena Points pribadi.</p>

    @if ($offerings->isEmpty())
        <div class="card"><p class="muted">Belum ada layanan Sesi Foto Radiografi yang tersedia.</p></div>
    @else
        <div class="summary">
            @foreach ($offerings as $offering)
                <article class="card">
                    <h2>{{ $offering->name }}</h2>
                    <p>{{ $offering->pointPrice() }} Madeena Points</p>
                    <p class="muted">
                        @if ($offering->includes_ai) Bantuan interpretasi otomatis tersedia. @endif
                        @if ($offering->includes_doctor) Peninjauan dokter termasuk. @endif
                    </p>
                    <a href="{{ route('member.services.show', $offering->getKey()) }}">Lihat jadwal</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
