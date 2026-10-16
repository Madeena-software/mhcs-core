@extends('radiographer.layout')
@section('content')
@if(!empty($state['handoff']))<p><a href="{{ route('radiologist.manage',$state['handoff']) }}">Penugasan Radiolog / laporan klinis</a></p>@endif
<h1>Penugasan Radiografer — {{ $reference }}</h1><a href="/admin/study-worklist">Kembali ke daftar studi</a>
@include('components.task-access-status')
@if(empty($state['candidates']))<p role="status">Tidak ada individu terverifikasi yang tersedia untuk penugasan ini. Hubungi pengelola kelayakan profesional.</p>@endif
<p>Status teknis: {{ $state['state'] }}</p>
@if(isset($result['url']))@include('components.task-link')@endif
<form method="post">@csrf<input type="hidden" name="action" value="{{ isset($state['id']) ? 'reassign' : 'issue' }}">
<label for="assigned-user">ID akun petugas terverifikasi</label><select id="assigned-user" name="user" required><option value="">Pilih petugas terverifikasi</option>@foreach($state['candidates'] ?? [] as $candidate)<option value="{{ $candidate['id'] }}">{{ $candidate['label'] }}</option>@endforeach</select>
<label for="lifetime">Masa berlaku</label><select id="lifetime" name="hours"><option value="24">24 jam</option><option value="72">3 hari</option><option value="168">7 hari</option></select><button type="submit">{{ isset($state['id']) ? 'Tugaskan ulang' : 'Terbitkan tautan' }}</button>
</form>
@if(isset($state['id']))<form method="post">@csrf<input type="hidden" name="action" value="revoke"><button type="submit">Cabut akses</button></form>
<form method="post">@csrf<input type="hidden" name="action" value="reopen"><label for="prior-handoff">Referensi handoff sebelumnya</label><input id="prior-handoff" name="handoff" value="{{ $state['handoff'] ?? '' }}" readonly required><label for="reopen-reason">Alasan buka kembali</label><input id="reopen-reason" name="reason" maxlength="500" required><button type="submit">Buka kembali dengan pemeriksaan status klinis</button></form>
<p>Status klinis tidak diketahui, final, kedaluwarsa atau tidak terverifikasi menolak buka kembali. Admin tidak dapat mengesampingkannya.</p>@endif
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" id="manage-logout">Keluar</button></form>
@endsection
