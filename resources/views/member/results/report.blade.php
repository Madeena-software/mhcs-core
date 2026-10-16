<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hasil pemeriksaan</title></head>
<body><main>
<h1>Hasil pemeriksaan final</h1>
<dl><dt>Pemeriksaan</dt><dd>{{ $metadata['examination'] }}</dd><dt>Fasilitas</dt><dd>{{ $metadata['facility'] }}</dd><dt>Jadwal pemeriksaan (UTC)</dt><dd>{{ $metadata['date'] }}</dd><dt>Penulis / finalisator</dt><dd>{{ $clinician }}</dd><dt>Waktu finalisasi (UTC)</dt><dd>{{ $finalized_at }}</dd></dl>
<h2>Temuan</h2><p style="white-space: pre-wrap">{{ $findings }}</p>
<h2>Kesimpulan</h2><p style="white-space: pre-wrap">{{ $conclusion }}</p>
@if ($recommendations !== '')<h2>Rekomendasi</h2><p style="white-space: pre-wrap">{{ $recommendations }}</p>@endif
<p role="status">Versi final yang dipublikasikan secara manual. Akses sementara berakhir 24 jam setelah publikasi dan dapat ditahan atau dicabut.</p>
</main></body></html>
