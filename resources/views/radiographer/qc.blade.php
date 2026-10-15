<h2>Metadata dan QC teknis</h2>
<p class="workspace-note">Identitas dan UID telah direkonsiliasi. Autofill tetap perlu diverifikasi pada gambar. Bukan laporan atau persetujuan klinis.</p>
<p class="workspace-note">Protokol: {{ $protocol['reference'] ?? 'Tidak diketahui' }} · revisi {{ $protocol['revision'] ?? '?' }} · {{ $protocol['projection'] ?? 'Tidak diketahui' }}. Kelengkapan terverifikasi: {{ ($protocol['complete'] ?? false) ? 'Ya' : 'Tidak' }}.</p>
<dl><dt>MRN / nama (sumber pemilik)</dt><dd>{{ $identity['patientId'] }} / {{ $identity['patientName'] }}</dd><dt>Study / Series / SOP (DICOM aktual)</dt><dd>{{ $actual['studyInstanceUid'] }} / {{ $actual['seriesInstanceUid'] }} / {{ $actual['sopInstanceUid'] }}</dd><dt>Waktu akuisisi</dt><dd>Tidak diketahui — waktu penerimaan bukan waktu akuisisi</dd></dl>
@if($source_changed)<p role="alert">Sumber berubah. QC sebelumnya tidak berlaku untuk versi ini.</p>@endif
@if($read_only)
<p class="workspace-status" role="status">Menunggu tinjauan radiolog — hanya baca</p>
<dl>@foreach($overlay ?? [] as $field => $item)<dt>{{ $field }} — {{ $item['authority'] }}</dt><dd>{{ $item['value'] ?? 'Tidak diketahui' }}</dd>@endforeach</dl>
<p>{{ $qc['outcome'] ?? '' }}</p>
@else
<form method="post" action="{{ route('radiographer.save', $workspace) }}" id="workspace-save">@csrf
<input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="binding" value="{{ $binding }}">
@foreach(['studyDescription' => 'Deskripsi studi', 'bodyPart' => 'Bagian tubuh', 'projection' => 'Proyeksi', 'laterality' => 'Lateralitas / tidak berlaku', 'detectorType' => 'Jenis detektor (opsional)'] as $field => $label)
<label for="overlay-{{ $field }}">{{ $label }}</label>
@php($current = $source_changed ? ($autofill[$field]['value'] ?? '') : ($overlay[$field]['value'] ?? $autofill[$field]['value'] ?? ''))
@if(in_array($field, ['projection', 'laterality', 'bodyPart', 'detectorType']))
<select id="overlay-{{ $field }}" name="overlay[{{ $field }}]"><option value="">Tidak diketahui</option>@foreach(match($field) { 'projection' => ['PA','AP'], 'bodyPart' => ['CHEST'], 'detectorType' => ['BED','TRX','THORAX'], default => ['not_applicable','R','L','B','unknown'] } as $option)<option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>@endforeach</select>
@else<input id="overlay-{{ $field }}" name="overlay[{{ $field }}]" maxlength="64" value="{{ $current }}">@endif
<p class="workspace-note">{{ $overlay[$field]['authority'] ?? $autofill[$field]['authority'] }} — DICOM: {{ $autofill[$field]['value'] ?? 'Tidak diketahui' }} · snapshot: {{ $autofill[$field]['source_value'] ?? 'Tidak diketahui' }} · {{ $autofill[$field]['authority'] }}</p>
@endforeach
<label for="overlay-reason">Alasan verifikasi / koreksi</label><input id="overlay-reason" name="overlay[reason]" maxlength="500" required>
<label for="qc-outcome">Hasil QC</label><select id="qc-outcome" name="qc[outcome]">@foreach(['Layak','Tidak Layak','Perlu Eskalasi'] as $outcome)<option>{{ $outcome }}</option>@endforeach</select>
@foreach(['identity' => 'Identitas dan pemeriksaan sesuai', 'positioning' => 'Posisi sesuai', 'coverage' => 'Cakupan anatomi lengkap', 'orientation' => 'Orientasi sesuai', 'exposure' => 'Eksposur / visibilitas memadai', 'artifacts' => 'Artefak dapat diterima', 'completeness' => 'Seluruh gambar yang diwajibkan protokol tersedia'] as $key => $label)
<input type="hidden" name="qc[checks][{{ $key }}]" value="0"><label><input type="checkbox" name="qc[checks][{{ $key }}]" value="1">{{ $label }}</label>
@endforeach
<label for="qc-reason">Catatan / alasan (wajib untuk Tidak Layak atau Perlu Eskalasi)</label><textarea id="qc-reason" name="qc[reason]" maxlength="1000"></textarea>
<input type="hidden" id="workspace-rendered" name="qc[rendered]" value="0">
<p role="status" id="workspace-render-status">Tunggu sampai gambar berhasil ditampilkan sebelum menyatakan Layak.</p>
<button type="submit" id="workspace-save-button">Simpan metadata dan QC</button>
</form>
@if($qc && !$source_changed)
<p role="status">QC tersimpan: {{ $qc['outcome'] }} — revisi {{ $revision }}</p>
<form method="post" action="{{ route('radiographer.handoff', $workspace) }}">@csrf<input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="binding" value="{{ $binding }}"><input type="hidden" name="key" value="{{ $handoff_key }}"><button type="submit" id="workspace-handoff">Serahkan untuk tinjauan radiolog</button></form>
@endif
@endif
<p class="workspace-note">Berlaku sampai {{ $expires_at }} UTC. Tidak ada unduh DICOM, hasil AI atau laporan klinis dalam ruang kerja ini.</p>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
