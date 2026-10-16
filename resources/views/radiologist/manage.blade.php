@extends('radiographer.layout')
@section('title','Penugasan Radiolog')
@section('content')
<h1>Penugasan Radiolog — {{ $state['case'] }}</h1>
<a href="/admin/study-worklist">Kembali ke daftar studi</a>
@include('components.task-access-status')
@if(empty($state['candidates']))<p role="status">Tidak ada individu terverifikasi yang tersedia untuk penugasan ini. Hubungi pengelola kelayakan profesional.</p>@endif
@if($state['finalized'])<p role="status">Final internal — hanya baca</p>@endif
@if($state['finalized'])<p><a href="{{ route('member.result.manage', $state['case']) }}">Publikasi hasil Member (keputusan Admin terpisah)</a></p>@endif
@if(isset($result['url']))@include('components.task-link')@endif
@if(!$state['finalized'])
<form method="post">@csrf<input type="hidden" name="action" value="{{ $state['id'] ? 'reassign' : 'issue' }}">@if($state['id'])<input type="hidden" name="generation" value="{{ $state['generation'] }}">@endif
<label for="assigned-user">Radiolog dengan kelayakan terverifikasi</label><select id="assigned-user" name="user" required>@foreach($state['candidates'] as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
<label for="hours">Durasi jam (bawaan 24; maksimum 72)</label><input id="hours" type="number" min="1" max="72" name="hours" value="24" required>
<label for="issue-password">Verifikasi kata sandi Admin</label><input id="issue-password" name="password" type="password" autocomplete="current-password" required><button>Terbitkan tautan</button></form>
@endif
@if($state['id'] && !$state['revoked'])<form method="post">@csrf<input type="hidden" name="action" value="revoke"><input type="hidden" name="generation" value="{{ $state['generation'] }}"><label for="revoke-password">Verifikasi kata sandi Admin</label><input id="revoke-password" name="password" type="password" autocomplete="current-password" required><button>Cabut akses</button></form>@endif
<details><summary>Verifikasi kelayakan profesional (kapabilitas khusus)</summary><p>Catatan sintetis tidak menetapkan lisensi atau persetujuan institusi.</p><form method="post">@csrf<input type="hidden" name="action" value="credential">
<label for="credential-user">ID individu Radiolog</label><input id="credential-user" name="user" required><label for="credential-status">Status</label><select id="credential-status" name="status"><option>verified</option></select>
<label for="evidence">Referensi bukti kredensial</label><input id="evidence" name="evidence" required maxlength="255"><label for="valid-until">Berlaku hingga (UTC)</label><input id="valid-until" name="valid_until" type="datetime-local" required>
<label for="credential-password">Verifikasi kata sandi verifikator</label><input id="credential-password" name="password" type="password" autocomplete="current-password" required><button>Rekam verifikasi kelayakan</button></form></details>
<details><summary>Cabut kelayakan profesional (kapabilitas khusus)</summary><form method="post">@csrf<input type="hidden" name="action" value="credential"><input type="hidden" name="status" value="revoked">
<label for="revoke-eligibility">Catatan kelayakan sesuai fasilitas dan layanan</label><select id="revoke-eligibility" name="eligibility" required>@foreach($state['eligibilities'] as $e)<option value="{{ $e->id }}">{{ $e->user_id }} · {{ $e->modality }} · revisi {{ $e->revision }} · {{ $e->status }}</option>@endforeach</select>
<label for="revoke-credential-user">ID individu Radiolog</label><input id="revoke-credential-user" name="user" required>
<label for="revoke-evidence">Referensi bukti pencabutan</label><input id="revoke-evidence" name="evidence" required maxlength="255">
<label for="revoke-credential-password">Verifikasi kata sandi verifikator</label><input id="revoke-credential-password" name="password" type="password" autocomplete="current-password" required><button>Cabut kelayakan</button></form></details>
<form method="post" action="/logout">@csrf<button id="manage-logout">Keluar</button></form>
@endsection
