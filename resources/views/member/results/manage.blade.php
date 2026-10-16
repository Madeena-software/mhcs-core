<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Publikasi hasil</title></head><body><main>
<h1>Publikasi hasil Member</h1>
<p>Finalisasi dokter tidak mempublikasikan hasil. Verifikasi kasus, penerima, dan versi sebelum keputusan publikasi.</p>
@if ($state['report'])
<dl><dt>Kasus</dt><dd>{{ $case }}</dd><dt>Penerima (Subjek Pelayanan)</dt><dd>{{ $state['recipient'] }}</dd><dt>Member</dt><dd>{{ $state['member'] }}</dd><dt>Versi laporan final</dt><dd>{{ $state['report']['report'] }}</dd><dt>Penulis / finalisator</dt><dd>{{ $state['report']['clinician'] }}</dd><dt>Waktu finalisasi (UTC)</dt><dd>{{ $state['report']['finalized_at'] }}</dd></dl>
<h2>Temuan</h2><p style="white-space: pre-wrap">{{ $state['report']['findings'] }}</p><h2>Kesimpulan</h2><p style="white-space: pre-wrap">{{ $state['report']['conclusion'] }}</p>
@else
<p role="alert">Pratinjau laporan tidak tersedia. Petugas berwenang tetap dapat menahan, menangguhkan, atau mencabut akses.</p>
@endif
<p role="status">Status: {{ $state['publication']->state ?? 'belum dipublikasikan' }}. Revisi keputusan: {{ $state['revision'] }}.</p>
@if (!empty($result['url']))<label for="result-url">Tautan hasil sementara (24 jam, tetap memerlukan login penerima)</label><input id="result-url" type="text" readonly value="{{ $result['url'] }}">@endif
@if ($state['report'])
<form method="POST" action="{{ route('member.result.manage', $case) }}">@csrf
<input type="hidden" name="action" value="publish"><input type="hidden" name="revision" value="{{ $state['revision'] }}"><input type="hidden" name="key" value="{{ $key }}"><input type="hidden" name="report" value="{{ $state['report']['report'] }}"><input type="hidden" name="member" value="{{ $state['member'] }}">
<label><input id="confirm-recipient" type="checkbox" name="confirm" value="1" required> Saya memverifikasi kasus, penerima dewasa terverifikasi, dan versi final ini.</label><button type="submit">Publikasikan versi final</button>
</form>
@endif
@if ($state['publication'])
@foreach (['hold' => 'Tahan akses', 'suspend' => 'Tangguhkan akses', 'revoke' => 'Cabut akses'] as $action => $label)
<form method="POST" action="{{ route('member.result.manage', $case) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="revision" value="{{ $state['revision'] }}"><input type="hidden" name="key" value="{{ $key }}-{{ $action }}"><button type="submit">{{ $label }}</button></form>
@endforeach
@endif
<a href="/admin">Kembali ke Admin</a>
</main></body></html>
