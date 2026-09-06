<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

use App\Modules\ImageGateway\Application\Contracts\AiPacsDerivedPdfGeneratorContract;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiPacsDerivedPdfResult;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\XObject\Image as SmalotImage;
use Throwable;

final class AiPacsLaravelDerivedPdfGenerator implements AiPacsDerivedPdfGeneratorContract
{
    private const array REQUIRED_FIELDS = [
        'patientName',
        'patientDobAge',
        'patientGender',
        'patientMrn',
        'examinationDate',
        'examinationArea',
        'radiographerName',
        'aiReviewer',
        'reportDate',
        'findings',
        'impression',
    ];

    public function __construct(
        public readonly ?string $logoPath = null,
    ) {}

    /**
     * @param array<string, mixed> $provenanceData
     */
    public function generateDerivedPdf(
        string $originalPdfPath,
        array $provenanceData,
        string $destinationPath,
    ): AiPacsDerivedPdfResult {
        $this->validateOriginalPdf($originalPdfPath);

        // Parse original vendor PDF using pure PHP parser
        $parser = new Parser();
        try {
            $parsedPdf = $parser->parseFile($originalPdfPath);
            $pages = $parsedPdf->getPages();
            if (count($pages) < 1) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Original vendor PDF contains zero pages.',
                );
            }
        } catch (ImageGatewayException $ige) {
            throw $ige;
        } catch (Throwable $e) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Failed to parse original vendor PDF: '.$this->sanitizeOutput($e->getMessage()),
                $e,
            );
        }

        // If findings or impression were not supplied in provenance, extract verbatim from vendor PDF text
        $vendorText = $parsedPdf->getText();
        if (empty($provenanceData['findings']) || empty($provenanceData['impression'])) {
            [$extractedFindings, $extractedImpression] = $this->extractVerbatimTextFromVendorPdf($vendorText);
            if (! empty($extractedFindings) && empty($provenanceData['findings'])) {
                $provenanceData['findings'] = $extractedFindings;
            }
            if (! empty($extractedImpression) && empty($provenanceData['impression'])) {
                $provenanceData['impression'] = $extractedImpression;
            }
        }

        // Strict metadata verification and equality checks
        $this->verifyMetadataAndEquality($provenanceData, $vendorText);

        // Source radiograph verification: preserve aspect ratio, no crop, no heuristic largest image
        $tempRadiographPath = null;
        $effectiveRadiographPath = $this->resolveProvenRadiograph($provenanceData, $pages[0], $tempRadiographPath);

        // Logo verification
        $effectiveLogo = $this->logoPath
            ?? ($provenanceData['logoPath'] ?? null)
            ?? resource_path('images/branding/rumah-skrining-logo.png');

        if (! file_exists($effectiveLogo)) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                "Rumah Skrining logo does not exist at path: {$effectiveLogo}",
            );
        }

        $viewData = [
            'facilityName' => (string) ($provenanceData['facilityName'] ?? 'Rumah Skrining CV Prestige'),
            'organizationSubtitle' => (string) ($provenanceData['organizationSubtitle'] ?? 'oleh PT Madeena'),
            'facilityAddressLine1' => (string) ($provenanceData['facilityAddressLine1'] ?? 'Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta,'),
            'facilityAddressLine2' => (string) ($provenanceData['facilityAddressLine2'] ?? 'Daerah Istimewa Yogyakarta 55162'),
            'patientName' => (string) $provenanceData['patientName'],
            'patientDobAge' => (string) $provenanceData['patientDobAge'],
            'patientGender' => (string) $provenanceData['patientGender'],
            'patientMrn' => (string) $provenanceData['patientMrn'],
            'examinationDate' => (string) $provenanceData['examinationDate'],
            'examinationArea' => (string) $provenanceData['examinationArea'],
            'radiographerName' => (string) $provenanceData['radiographerName'],
            'aiReviewer' => (string) $provenanceData['aiReviewer'],
            'reportDate' => (string) $provenanceData['reportDate'],
            'findings' => (string) $provenanceData['findings'],
            'impression' => (string) $provenanceData['impression'],
            'disclaimerText' => (string) ($provenanceData['disclaimerText'] ?? 'Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)'),
            'footerNote' => (string) ($provenanceData['footerNote'] ?? 'Laporan ini hanya sebagai acuan klinis.'),
            'logoPath' => $effectiveLogo,
            'radiographImagePath' => $effectiveRadiographPath,
        ];

        try {
            $html = view('pdf.derived-ai-report', $viewData)->render();

            $destDir = dirname($destinationPath);
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 12,
                'margin_bottom' => 10,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font' => 'Helvetica',
            ]);

            $mpdf->WriteHTML($html);
            $mpdf->Output($destinationPath, Destination::FILE);

            if (! file_exists($destinationPath)) {
                throw new ImageGatewayException(
                    AiErrorCode::PROCESSING_ERROR,
                    'Derived PDF generator failed to create output file.',
                );
            }

            $pdfBytes = file_get_contents($destinationPath);
            if ($pdfBytes === false || strlen($pdfBytes) < 100) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Generated derived PDF is empty or suspiciously small.',
                );
            }

            if (! str_starts_with($pdfBytes, '%PDF-')) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Generated derived PDF missing %PDF- header.',
                );
            }

            $checksum = hash('sha256', $pdfBytes);
            $filename = basename($destinationPath);

            $discrepancies = (array) ($provenanceData['discrepancies'] ?? []);
            if (isset($provenanceData['dicomPatientSex'])) {
                $dSex = strtoupper(trim((string) $provenanceData['dicomPatientSex']));
                if ($dSex === '' || in_array($dSex, ['O', 'OTHER', 'UNKNOWN', 'U'], true)) {
                    if (! empty($provenanceData['patientGender']) && ! in_array('patient_sex_missing_in_dicom_vendor_value_present', $discrepancies, true)) {
                        $discrepancies[] = 'patient_sex_missing_in_dicom_vendor_value_present';
                    }
                }
            }

            $genderSource = (string) ($provenanceData['genderSource'] ?? (in_array('patient_sex_missing_in_dicom_vendor_value_present', $discrepancies, true) ? 'vendor_report' : 'mhcs_record'));

            return new AiPacsDerivedPdfResult(
                pdfBytes: $pdfBytes,
                checksum: $checksum,
                bytes: strlen($pdfBytes),
                filename: $filename,
                metadata: [
                    'generator' => 'AiPacsLaravelDerivedPdfGenerator',
                    'engine' => 'mpdf',
                    'sha256' => $checksum,
                    'byteSize' => strlen($pdfBytes),
                    'discrepancies' => $discrepancies,
                    'genderSource' => $genderSource,
                ],
            );
        } catch (ImageGatewayException $ige) {
            throw $ige;
        } catch (Throwable $e) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                'Derived PDF rendering failed: '.$this->sanitizeOutput($e->getMessage()),
                $e,
            );
        } finally {
            if ($tempRadiographPath !== null && file_exists($tempRadiographPath)) {
                @unlink($tempRadiographPath);
            }
        }
    }

    private function validateOriginalPdf(string $path): void
    {
        if (! file_exists($path) || ! is_file($path)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                "Original vendor PDF does not exist at path: {$path}",
            );
        }

        $size = filesize($path);
        if ($size === false || $size < 50) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                "Original vendor PDF is suspiciously small ({$size} bytes).",
            );
        }

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                "Cannot open original vendor PDF at path: {$path}",
            );
        }

        try {
            $header = fread($fp, 1024);
            if ($header === false || ! str_starts_with($header, '%PDF-')) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Original vendor PDF missing %PDF- magic bytes header.',
                );
            }

            fseek($fp, max(0, $size - 4096));
            $tail = fread($fp, 4096);
            if ($tail === false || ! str_contains($tail, '%%EOF')) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Original vendor PDF missing %%EOF trailer marker.',
                );
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function verifyMetadataAndEquality(array $data, string $vendorText): void
    {
        // 1. Check all required dynamic fields are present and non-empty
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    "Missing required metadata field: {$field}",
                );
            }
        }

        // 2. Strict equality checks against vendor text if vendor text is present
        if (trim($vendorText) !== '') {
            $patientName = (string) $data['patientName'];
            $patientMrn = (string) $data['patientMrn'];

            // If vendor text contains a patient name label, verify match
            if (preg_match('/(?:nama|name|patient name)[\s:]+([^\r\n]+)/i', $vendorText, $nameMatch)) {
                $vendorName = trim($nameMatch[1]);
                if ($vendorName !== '' && ! str_contains(strtolower($vendorName), strtolower($patientName)) && ! str_contains(strtolower($patientName), strtolower($vendorName))) {
                    throw new ImageGatewayException(
                        AiErrorCode::AI_PACS_INVALID_REPORT,
                        "Patient name mismatch: MHCS record '{$patientName}' does not match vendor report '{$vendorName}'.",
                    );
                }
            }

            // If vendor text contains MRN label, verify match
            if (preg_match('/(?:id pasien|mrn|patient id)[\s:]+([^\r\n]+)/i', $vendorText, $mrnMatch)) {
                $vendorMrn = trim($mrnMatch[1]);
                if ($vendorMrn !== '' && ! str_contains(strtolower($vendorMrn), strtolower($patientMrn)) && ! str_contains(strtolower($patientMrn), strtolower($vendorMrn))) {
                    throw new ImageGatewayException(
                        AiErrorCode::AI_PACS_INVALID_REPORT,
                        "MRN mismatch: MHCS record '{$patientMrn}' does not match vendor report '{$vendorMrn}'.",
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $provenanceData
     */
    private function resolveProvenRadiograph(array $provenanceData, object $page, ?string &$tempRadiographPath): ?string
    {
        // Case A: Radiograph image path explicitly provided in provenance
        if (! empty($provenanceData['radiographImagePath'])) {
            $imagePath = (string) $provenanceData['radiographImagePath'];
            if (! file_exists($imagePath) || ! is_file($imagePath)) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    "Specified radiograph image does not exist: {$imagePath}",
                );
            }

            $imgInfo = @getimagesize($imagePath);
            if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Specified radiograph image is corrupted or invalid.',
                );
            }

            return $imagePath;
        }

        // Case B: Extract proven image from vendor PDF XObjects
        if (method_exists($page, 'getXObjects')) {
            $xobjects = $page->getXObjects();
            $imageObjects = [];
            foreach ($xobjects as $key => $xobj) {
                if ($xobj instanceof SmalotImage) {
                    $imageObjects[$key] = $xobj;
                }
            }

            // If there are multiple ambiguous images, heuristic selection and cropping are strictly forbidden
            if (count($imageObjects) > 1) {
                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_INVALID_REPORT,
                    'Multiple ambiguous images found in vendor PDF; source radiograph image cannot be heuristically selected or cropped.',
                );
            }

            // If exactly one image XObject is present, it is proven
            if (count($imageObjects) === 1) {
                $firstImage = reset($imageObjects);
                $rawContent = $firstImage->getContent();
                if ($rawContent !== '') {
                    $tempRadiographPath = sys_get_temp_dir().'/radiograph_'.bin2hex(random_bytes(8)).'.png';
                    file_put_contents($tempRadiographPath, $rawContent);

                    $imgInfo = @getimagesize($tempRadiographPath);
                    if ($imgInfo !== false && $imgInfo[0] > 0 && $imgInfo[1] > 0) {
                        return $tempRadiographPath;
                    }

                    @unlink($tempRadiographPath);
                    $tempRadiographPath = null;
                }
            }
        }

        // If radiograph cannot be proven, do not create derived PDF
        throw new ImageGatewayException(
            AiErrorCode::AI_PACS_INVALID_REPORT,
            'Correct source radiograph image cannot be proven.',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractVerbatimTextFromVendorPdf(string $vendorText): array
    {
        if (trim($vendorText) === '') {
            return ['', ''];
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $vendorText))));
        $inFindings = false;
        $inImpression = false;
        $findingsLines = [];
        $impressionLines = [];

        foreach ($lines as $line) {
            $lower = strtolower($line);
            if (str_contains($lower, 'temuan radiologis') || str_contains($lower, 'findings')) {
                $inFindings = true;
                $inImpression = false;
                continue;
            }
            if (str_contains($lower, 'kesan') || str_contains($lower, 'impression') || str_contains($lower, 'conclusion')) {
                $inFindings = false;
                $inImpression = true;
                continue;
            }
            if (str_contains($lower, 'radiografer') || str_contains($lower, 'penelaah') || str_contains($lower, 'laporan ini')) {
                $inFindings = false;
                $inImpression = false;
                continue;
            }

            if ($inFindings) {
                $findingsLines[] = $line;
            } elseif ($inImpression) {
                $impressionLines[] = $line;
            }
        }

        return [
            trim(implode(' ', $findingsLines)),
            trim(implode(' ', $impressionLines)),
        ];
    }

    private function sanitizeOutput(string $output): string
    {
        $sanitized = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return mb_substr($sanitized, 0, 200);
    }
}
