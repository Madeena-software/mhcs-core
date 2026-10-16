@extends('radiographer.layout')
@section('title','Ruang kerja Radiolog')
@section('content')
<h1>Ruang kerja Radiolog — {{ $display_reference }}</h1>
<p>{{ $identity['patientName'] }} · {{ $identity['patientId'] }} · Akses hingga {{ $expires_at }} UTC</p>
<p role="status">{{ $read_only ? 'Final internal — hanya baca' : ($quality === 'repeat_required' ? 'repeat_pending — finalisasi diblokir' : ($quality ?? 'Kualitas klinis belum dinilai')) }} · Revisi {{ $revision }}</p>
<p>Keputusan klinis dan laporan ditulis sendiri oleh Radiolog. Finalisasi merupakan atestasi internal, bukan tanda tangan tersertifikasi.</p>
<a href="{{ route('radiologist.viewer',$workspace) }}">Buka penampil DICOM asli</a>
<iframe title="Penampil DICOM asli terlindungi" src="{{ route('radiologist.viewer',$workspace) }}" style="width:100%;height:700px;border:1px solid #71808b"></iframe>
@if(!$read_only)
<form method="post" action="{{ route('radiologist.command',[$workspace,'quality']) }}">@csrf
@include('radiologist.binding')
<label for="decision">Keputusan kualitas klinis</label><select id="decision" name="decision"><option value="usable">usable</option><option value="repeat_required">repeat_required</option></select>
<label for="reason">Alasan pengulangan</label><select id="reason" name="reason"><option value="">—</option>@foreach(\App\Modules\Doctor\Application\Services\RadiologistWorkspace::REASONS as $reason)<option>{{ $reason }}</option>@endforeach</select>
<label for="explanation">Penjelasan (wajib untuk other)</label><textarea id="explanation" name="explanation" maxlength="1000"></textarea><button>Simpan keputusan klinis</button>
</form>
<form method="post" action="{{ route('radiologist.command',[$workspace,'draft']) }}">@csrf
@include('radiologist.binding')
<label for="findings">Temuan / Findings</label><textarea id="findings" name="findings" maxlength="20000">{{ $report?->findings }}</textarea>
<label for="conclusion">Kesimpulan / Impression</label><textarea id="conclusion" name="conclusion" maxlength="20000">{{ $report?->conclusion }}</textarea>
<label for="recommendations">Rekomendasi (opsional)</label><textarea id="recommendations" name="recommendations" maxlength="20000">{{ $report?->recommendations }}</textarea><button>Simpan revisi draf</button>
</form>
<form method="post" action="{{ route('radiologist.command',[$workspace,'finalize']) }}" autocomplete="off">@csrf
@include('radiologist.binding')
<input type="hidden" name="key" value="{{ $operation_key }}">
<label><input type="checkbox" name="attest" value="1" required> Saya mengonfirmasi temuan dan kesimpulan yang saya tulis pada revisi ini untuk finalisasi internal yang tidak dapat diubah.</label>
<label for="final-password">Verifikasi ulang kata sandi untuk finalisasi ini</label><input id="final-password" name="password" type="password" autocomplete="current-password" required><button>Finalisasi internal revisi {{ $revision }}</button>
</form>
@else
<h2>Laporan final internal</h2><p>Penulis {{ $finalization->author_id }} · Finalisator {{ $finalization->finalizer_id }} · {{ $finalization->finalized_at }} UTC</p><p>{{ $finalization->attestation }}</p><h3>Temuan</h3><p style="white-space:pre-wrap">{{ $report?->findings }}</p><h3>Kesimpulan</h3><p style="white-space:pre-wrap">{{ $report?->conclusion }}</p><h3>Rekomendasi</h3><p style="white-space:pre-wrap">{{ $report?->recommendations }}</p>
@endif
<h2>Riwayat revisi penulisan Anda</h2>
@foreach($history as $version)<details><summary>Revisi {{ $version->revision }} · {{ $version->created_at }} UTC · {{ $version->author_id }}</summary><p style="white-space:pre-wrap">{{ $version->findings }}</p><p style="white-space:pre-wrap">{{ $version->conclusion }}</p><p style="white-space:pre-wrap">{{ $version->recommendations }}</p></details>@endforeach
<form method="post" action="/logout">@csrf<button>Keluar</button></form>
@endsection
