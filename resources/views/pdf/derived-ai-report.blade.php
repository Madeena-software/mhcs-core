<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Pemeriksaan Radiografi Digital (DR) Toraks</title>
    <style>
        @page {
            margin-left: 15mm;
            margin-right: 15mm;
            margin-top: 12mm;
            margin-bottom: 10mm;
            margin-header: 0;
            margin-footer: 0;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #172434;
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .facility-name {
            font-size: 16pt;
            font-weight: bold;
            color: #31566e;
            letter-spacing: 0.2px;
        }
        .facility-badge {
            display: inline-block;
            background-color: #31566e;
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .facility-addr {
            font-size: 7.5pt;
            color: #172434;
            line-height: 1.25;
            margin-top: 3px;
        }
        .divider {
            border-bottom: 1.5px solid #31566e;
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            color: #172434;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        .demographics-box {
            border: 1px solid #b8c8d1;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }
        .demo-table td {
            vertical-align: top;
            padding: 2px 4px;
        }
        .demo-label {
            font-size: 6.5pt;
            font-weight: bold;
            color: #465e6d;
            text-transform: uppercase;
        }
        .demo-val {
            font-size: 8.5pt;
            font-weight: normal;
            color: #172434;
            margin-top: 1px;
        }
        .section-banner {
            background-color: #e8f0f4;
            border-radius: 3px;
            padding: 4px 8px;
            margin-bottom: 6px;
        }
        .section-banner-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #31566e;
            letter-spacing: 0.2px;
        }
        .radiograph-table-wrapper {
            width: 100%;
            margin: 4px 0;
            border-collapse: collapse;
        }
        .radiograph-frame-table {
            width: 68mm;
            height: 86mm;
            border: 1px solid #b8c8d1;
            background-color: #000000;
            border-collapse: collapse;
            margin: 0 auto;
        }
        .radiograph-frame-cell {
            width: 68mm;
            height: 86mm;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }
        .radiograph-img {
            max-width: 66mm;
            max-height: 84mm;
            display: block;
            margin: 0 auto;
        }
        .findings-text {
            font-size: 8.5pt;
            line-height: 1.35;
            color: #172434;
            text-align: justify;
            margin-top: 6px;
        }
        .impression-text {
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.35;
            color: #172434;
            text-align: justify;
            margin-top: 4px;
        }
        .sig-table td {
            vertical-align: top;
            padding: 0 8px;
        }
        .sig-label {
            font-size: 7pt;
            font-weight: bold;
            color: #465e6d;
            text-transform: uppercase;
        }
        .sig-val {
            font-size: 7.5pt;
            color: #172434;
            margin-top: 3px;
            margin-bottom: 8px;
        }
        .sig-line {
            border-bottom: 1px solid #c4d0d7;
            width: 100%;
        }
        .disclaimer {
            font-size: 7pt;
            font-weight: bold;
            color: #ba1c1c;
            text-align: center;
            margin-top: 10px;
            margin-bottom: 6px;
            letter-spacing: 0.1px;
        }
        .footer-table td {
            font-size: 6.5pt;
            vertical-align: bottom;
        }
        .footer-note {
            font-style: italic;
            color: #637582;
        }
        .footer-fac {
            font-weight: bold;
            color: #31566e;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 85px;">
                <img src="{{ $logoPath }}" style="height: 58px; max-width: 85px;">
            </td>
            <td style="padding-left: 10px;">
                <div class="facility-name">{{ $facilityName }}</div>
                <div style="margin-top: 2px;"><span class="facility-badge">{{ $organizationSubtitle }}</span></div>
                <div class="facility-addr">{{ $facilityAddressLine1 }}<br>{{ $facilityAddressLine2 }}</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="doc-title">LAPORAN PEMERIKSAAN RADIOGRAFI DIGITAL (DR) TORAKS</div>

    <div class="demographics-box">
        <table class="demo-table">
            <tr>
                <td style="width: 35%;">
                    <div class="demo-label">NAMA</div>
                    <div class="demo-val">{{ $patientName }}</div>
                </td>
                <td style="width: 35%;">
                    <div class="demo-label">TANGGAL LAHIR / USIA</div>
                    <div class="demo-val">{{ $patientDobAge }}</div>
                </td>
                <td style="width: 30%;">
                    <div class="demo-label">JENIS KELAMIN</div>
                    <div class="demo-val">{{ $patientGender }}</div>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 4px;">
                    <div class="demo-label">ID PASIEN / MRN</div>
                    <div class="demo-val">{{ $patientMrn }}</div>
                </td>
                <td style="padding-top: 4px;">
                    <div class="demo-label">TANGGAL PEMERIKSAAN</div>
                    <div class="demo-val">{{ $examinationDate }}</div>
                </td>
                <td style="padding-top: 4px;">
                    <div class="demo-label">AREA PEMERIKSAAN</div>
                    <div class="demo-val">{{ $examinationArea }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-banner">
        <span class="section-banner-title">TEMUAN RADIOLOGIS</span>
    </div>

    @if (!empty($radiographImagePath))
    <table class="radiograph-table-wrapper">
        <tr>
            <td align="center" style="text-align: center; vertical-align: middle; padding: 0;">
                <table class="radiograph-frame-table">
                    <tr>
                        <td align="center" valign="middle" class="radiograph-frame-cell">
                            <img src="{{ $radiographImagePath }}" class="radiograph-img">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @endif

    <div class="findings-text">
        {{ $findings }}
    </div>

    <div class="section-banner" style="margin-top: 8px;">
        <span class="section-banner-title">KESAN</span>
    </div>

    <div class="impression-text">
        {{ $impression }}
    </div>

    <div style="margin-top: 14px;">
        <table class="sig-table">
            <tr>
                <td style="width: 33%;">
                    <div class="sig-label">RADIOGRAFER</div>
                    <div class="sig-val">{{ $radiographerName }}</div>
                    <div class="sig-line"></div>
                </td>
                <td style="width: 33%;">
                    <div class="sig-label">PENELAAH AI</div>
                    <div class="sig-val">{{ $aiReviewer }}</div>
                    <div class="sig-line"></div>
                </td>
                <td style="width: 34%;">
                    <div class="sig-label">TANGGAL LAPORAN</div>
                    <div class="sig-val">{{ $reportDate }}</div>
                    <div class="sig-line"></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="disclaimer">{{ $disclaimerText }}</div>

    <table class="footer-table" style="margin-top: 6px;">
        <tr>
            <td class="footer-note">{{ $footerNote }}</td>
            <td class="footer-fac">{{ $facilityName }}</td>
        </tr>
    </table>
</body>
</html>
