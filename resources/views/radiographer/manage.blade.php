@extends('radiographer.layout')
@section('content')
@if(!empty($state['handoff']))<p><a href="{{ route('radiologist.manage',$state['handoff']) }}">Penugasan Radiolog / laporan klinis</a></p>@endif
<h1>Penugasan Radiografer — {{ $reference }}</h1><a href="/admin/study-worklist">Kembali ke daftar studi</a>
<p role="status">{{ $state['state'] }} @if(isset($state['expires_at'])) — {{ $state['expires_at'] }} UTC @endif @if($state['revoked'] ?? false) — Dicabut @endif</p>
@if(isset($result['url']))<label for="assignment-url">Tautan penugasan (hanya untuk petugas yang dipilih)</label><input readonly id="assignment-url" value="{{ $result['url'] }}"><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('assignment-url').value)">Salin tautan</button>@endif
<form method="post">@csrf<input type="hidden" name="action" value="{{ isset($state['id']) ? 'reassign' : 'issue' }}">
<label for="assigned-user">ID akun petugas terverifikasi</label><select id="assigned-user" name="user" required><option value="">Pilih petugas terverifikasi</option>@foreach($state['candidates'] ?? [] as $candidate)<option value="{{ $candidate['id'] }}">{{ $candidate['label'] }}</option>@endforeach</select>
<label for="lifetime">Masa berlaku</label><select id="lifetime" name="hours"><option value="24">24 jam</option><option value="72">3 hari</option><option value="168">7 hari</option></select><button type="submit">{{ isset($state['id']) ? 'Tugaskan ulang' : 'Terbitkan tautan' }}</button>
</form>
@if(isset($state['id']))<form method="post">@csrf<input type="hidden" name="action" value="revoke"><button type="submit">Cabut akses</button></form>
<form method="post">@csrf<input type="hidden" name="action" value="reopen"><label for="prior-handoff">Referensi handoff sebelumnya</label><input id="prior-handoff" name="handoff" value="{{ $state['handoff'] ?? '' }}" readonly required><label for="reopen-reason">Alasan buka kembali</label><input id="reopen-reason" name="reason" maxlength="500" required><button type="submit">Buka kembali dengan pemeriksaan status klinis</button></form>
<p>Status klinis tidak diketahui, final, kedaluwarsa atau tidak terverifikasi menolak buka kembali. Admin tidak dapat mengesampingkannya.</p>@endif
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" id="manage-logout">Keluar</button></form>
@endsection
