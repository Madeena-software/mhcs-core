@extends('member.layout')

@section('title', $offering->name)

@section('content')
<section aria-labelledby="service-title">
    <h1 id="service-title">{{ $offering->name }}</h1>
    <dl class="summary">
        <div><dt>Kode layanan</dt><dd>{{ $offering->code }}</dd></div>
        <div><dt>Harga</dt><dd>{{ $offering->pointPrice() }} Madeena Points</dd></div>
        <div><dt>Bantuan interpretasi otomatis</dt><dd>{{ $offering->includes_ai ? 'Termasuk' : 'Tidak termasuk' }}</dd></div>
        <div><dt>Peninjauan dokter</dt><dd>{{ $offering->includes_doctor ? 'Termasuk' : 'Tidak termasuk' }}</dd></div>
    </dl>

    <h2>Pilih Jadwal</h2>
    @if ($schedules->isEmpty())
        <div class="card"><p class="muted">Belum ada jadwal terbuka untuk layanan ini.</p></div>
    @else
        @foreach ($schedules as $schedule)
            <article class="card" style="margin: 12px 0">
                <h3>{{ $schedule->site_name_snapshot ?? $schedule->site?->display_name }}</h3>
                <p>{{ $schedule->starts_at->setTimezone($schedule->site?->timezone ?? 'UTC')->translatedFormat('l, j F Y') }} pukul {{ $schedule->starts_at->setTimezone($schedule->site?->timezone ?? 'UTC')->format('H.i') }}–{{ $schedule->ends_at->setTimezone($schedule->site?->timezone ?? 'UTC')->format('H.i') }}</p>
                <p class="muted">Sisa kuota: {{ $schedule->remaining_capacity }} dari {{ $schedule->quota }}</p>
                @if ($schedule->remaining_capacity > 0)
                    <form method="POST" action="{{ route('member.bookings.store') }}">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $schedule->getKey() }}">
                        <input type="hidden" name="point_cost" value="{{ $offering->pointPrice() }}">
                        <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                        <label><input type="checkbox" name="confirmation" value="1" required> Saya memahami harga dan ingin menggunakan Madeena Points pribadi.</label>
                        <button type="submit">Konfirmasi Jadwal</button>
                    </form>
                @endif
            </article>
        @endforeach
    @endif
</section>
@endsection
