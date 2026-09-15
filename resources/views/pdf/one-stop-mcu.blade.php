<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #172434; font-size: 10pt; }
        h1 { text-align: center; color: #31566e; font-size: 16pt; }
        h2 { color: #31566e; font-size: 12pt; border-bottom: 1px solid #b8c8d1; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px; border-bottom: 1px solid #dce4e8; }
        td:first-child { width: 48%; color: #465e6d; }
        .site { text-align: center; color: #465e6d; }
        .signatures { margin-top: 36px; }
        .signatures td { width: 50%; height: 70px; text-align: center; vertical-align: bottom; border: 0; }
        .line { border-top: 1px solid #172434; padding-top: 6px; }
        .disclaimer { margin-top: 20px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <div class="site"><strong>{{ $site_name }}</strong><br>{{ $site_address }}</div>
    <h1>HASIL SKRINING MCU</h1>
    <h2>Identitas Peserta</h2>
    <table>
        <tr><td>Nama</td><td>{{ $exam->member_name }}</td></tr>
        <tr><td>Tanggal lahir</td><td>{{ $birth_date }}</td></tr>
        <tr><td>Jenis kelamin</td><td>{{ $sex }}</td></tr>
        <tr><td>Nomor rekam medis</td><td>{{ $exam->medical_record_number }}</td></tr>
        <tr><td>Tanggal dan waktu pemeriksaan</td><td>{{ $examined_at_local }}</td></tr>
        <tr><td>Pemeriksa</td><td>{{ $examiner_name }}</td></tr>
    </table>
    <h2>Hasil Pemeriksaan</h2>
    <table>
        <tr><td>Tekanan darah sistolik</td><td>{{ $exam->systolic_bp_mmhg }} mmHg</td></tr>
        <tr><td>Tekanan darah diastolik</td><td>{{ $exam->diastolic_bp_mmhg }} mmHg</td></tr>
        <tr><td>Berat badan</td><td>{{ $exam->weight_kg }} kg</td></tr>
        <tr><td>Tinggi badan</td><td>{{ $exam->height_cm }} cm</td></tr>
        <tr><td>Alat ukur tinggi badan</td><td>Microtoise</td></tr>
        <tr><td>Suhu tubuh</td><td>{{ $exam->temperature_c }} °C</td></tr>
        <tr><td>BMI / IMT</td><td>{{ $exam->bmi }} kg/m²</td></tr>
        <tr><td>GCU glukosa</td><td>{{ $exam->glucose_mg_dl }} mg/dL</td></tr>
        <tr><td>Kolesterol total</td><td>{{ $exam->total_cholesterol_mg_dl }} mg/dL</td></tr>
        <tr><td>Asam urat</td><td>{{ $exam->uric_acid_mg_dl }} mg/dL</td></tr>
        <tr><td>Status puasa</td><td>{{ $exam->fasting_status === 'fasting' ? 'Puasa' : 'Tidak puasa' }}</td></tr>
        @if ($exam->fasting_duration_hours !== null)<tr><td>Durasi puasa</td><td>{{ $exam->fasting_duration_hours }} jam</td></tr>@endif
        @if ($exam->last_meal_at !== null)<tr><td>Waktu makan terakhir</td><td>{{ substr((string) $exam->last_meal_at, 0, 5) }}</td></tr>@endif
        <tr><td>PEF percobaan I</td><td>{{ $exam->pef_attempt_i ?? 'Tidak tercatat' }} L/min</td></tr>
        <tr><td>PEF percobaan II</td><td>{{ $exam->pef_attempt_ii ?? 'Tidak tercatat' }} L/min</td></tr>
        <tr><td>PEF percobaan III</td><td>{{ $exam->pef_attempt_iii ?? 'Tidak tercatat' }} L/min</td></tr>
        <tr><td>PEF tertinggi yang valid</td><td>{{ $exam->pef_highest_value ?? 'Tidak tercatat' }} L/min</td></tr>
        <tr><td>Catatan / tindak lanjut</td><td>{{ $exam->notes ?: '—' }}</td></tr>
    </table>
    <table class="signatures">
        <tr><td><div class="line">Tanda tangan pemeriksa<br>{{ $examiner_name }}</div></td><td><div class="line">Tanda tangan peserta</div></td></tr>
    </table>
    <p class="disclaimer">HASIL SKRINING MERUPAKAN PEMERIKSAAN AWAL DAN BUKAN PENETAPAN DIAGNOSIS MEDIS.</p>
</body>
</html>
