<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #172434; font-size: 9pt; }
        h1 { text-align: center; color: #31566e; font-size: 14pt; margin: 5px 0; }
        h2 { color: #31566e; font-size: 10pt; border-bottom: 1px solid #b8c8d1; padding-bottom: 2px; margin: 7px 0 3px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 4px; border-bottom: 1px solid #dce4e8; }
        td:first-child { width: 48%; color: #465e6d; }
        .brand { text-align: center; color: #465e6d; }
        .brand strong { font-size: 12pt; color: #31566e; }
        .signatures { margin-top: 14px; }
        .signatures td { width: 50%; height: 48px; text-align: center; vertical-align: bottom; border: 0; }
        .line { border-top: 1px solid #172434; padding-top: 3px; }
        .follow-up, .disclaimer { margin: 6px 0 0; font-weight: bold; text-align: center; }
        .follow-up { font-weight: normal; }
    </style>
</head>
<body>
    <div class="brand"><strong>Rumah Skrining CV Prestige</strong><br>oleh PT Madeena<br>Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta,<br>Daerah Istimewa Yogyakarta 55162<br>Kontak Rumah Skrining: +62 897-7067-528</div>
    <h1>HASIL SKRINING MCU</h1>
    <h2>Identitas Peserta</h2>
    <table>
        <tr><td>Nama</td><td>{{ $exam->member_name }}</td></tr>
        <tr><td>Tanggal lahir</td><td>{{ $birth_date }}</td></tr>
        <tr><td>Jenis kelamin</td><td>{{ $sex }}</td></tr>
        <tr><td>NIK</td><td>{{ $participant_nik }}</td></tr>
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
        <tr><td>PEF percobaan I</td><td>{{ $exam->pef_attempt_i }} L/min</td></tr>
        <tr><td>PEF percobaan II</td><td>{{ $exam->pef_attempt_ii }} L/min</td></tr>
        <tr><td>PEF percobaan III</td><td>{{ $exam->pef_attempt_iii }} L/min</td></tr>
        <tr><td>PEF tertinggi (nilai tertinggi, bukan rata-rata)</td><td>{{ $exam->pef_highest_value }} L/min</td></tr>
        <tr><td>Catatan / tindak lanjut</td><td>{{ $exam->notes ?: '—' }}</td></tr>
    </table>
    <table class="signatures">
        <tr><td><div class="line">Tanda tangan pemeriksa<br>{{ $examiner_name }}</div></td><td><div class="line">Tanda tangan peserta</div></td></tr>
    </table>
    <p class="follow-up">Konsultasi hasil skrining via WhatsApp:<br>dr. Noor Istichawari, M.M. (dr. Nunung) · +62 822-3107-9219<br>Untuk konsultasi dan tindak lanjut setelah pemeriksaan.</p>
    <p class="disclaimer">HASIL SKRINING MERUPAKAN PEMERIKSAAN AWAL DAN BUKAN PENETAPAN DIAGNOSIS MEDIS.</p>
</body>
</html>
