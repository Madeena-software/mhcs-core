<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsLaravelDerivedPdfGenerator;
use ReflectionClass;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

final class AiPacsLaravelDerivedPdfGeneratorTest extends TestCase
{
    private string $tempDir;

    private string $validOriginalPdf;

    private string $syntheticRadiographPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/derived_test_'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);

        // Generate a valid fixture PDF using pure PHP Mpdf
        $this->validOriginalPdf = $this->tempDir.'/valid_original.pdf';
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML('<p>Patient Name: Purnomo</p><p>MRN: MRN-1787808860329</p>');
        $mpdf->Output($this->validOriginalPdf, \Mpdf\Output\Destination::FILE);

        // Create a synthetic radiograph test image
        $this->syntheticRadiographPath = $this->tempDir.'/synthetic_radiograph.png';
        $im = imagecreatetruecolor(200, 250);
        $bgColor = imagecolorallocate($im, 10, 10, 10);
        imagefilledrectangle($im, 0, 0, 199, 249, (int) $bgColor);
        imagepng($im, $this->syntheticRadiographPath);
        imagedestroy($im);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*") ?: []);
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_generator_succeeds_using_only_composer_php_dependencies_with_no_python(): void
    {
        $generator = new AiPacsLaravelDerivedPdfGenerator(
            logoPath: resource_path('images/branding/rumah-skrining-logo.png'),
        );

        $dest = $this->tempDir.'/derived_output.pdf';
        $provenance = [
            'facilityName' => 'Rumah Skrining CV Prestige',
            'organizationSubtitle' => 'oleh PT Madeena',
            'patientName' => 'Purnomo',
            'patientDobAge' => '15 Januari 1981 (45 tahun)',
            'patientGender' => 'Laki-laki',
            'patientMrn' => 'MRN-1787808860329',
            'examinationDate' => '27 Agustus 2026',
            'examinationArea' => 'Toraks',
            'findings' => 'Toraks simetris, mediastinum di garis tengah. Tidak tampak kelainan nyata pada struktur tulang.',
            'impression' => 'Tidak tampak kelainan pada foto polos toraks.',
            'radiographerName' => 'Ratih Hanjar Dewanti, A.Md.Rad.',
            'aiReviewer' => 'Madeena Intelligence (AI)',
            'reportDate' => '1 September 2026',
            'disclaimerText' => 'Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)',
            'footerNote' => 'Laporan ini hanya sebagai acuan klinis.',
            'radiographImagePath' => $this->syntheticRadiographPath,
        ];

        $result = $generator->generateDerivedPdf($this->validOriginalPdf, $provenance, $dest);

        $this->assertFileExists($dest);
        $this->assertSame(hash('sha256', $result->pdfBytes), $result->checksum);
        $this->assertSame(strlen($result->pdfBytes), $result->bytes);
        $this->assertStringStartsWith('%PDF-', $result->pdfBytes);
        $this->assertStringContainsString('%%EOF', substr($result->pdfBytes, -4096));

        // Verify text extraction via pure PHP parser
        $parser = new Parser();
        $pdf = $parser->parseFile($dest);
        $extractedText = $pdf->getText();

        $this->assertStringContainsString('Rumah Skrining', $extractedText);
        $this->assertStringContainsString('Purnomo', $extractedText);
        $this->assertStringContainsString('MRN-1787808860329', $extractedText);
        $this->assertStringContainsString('Toraks simetris', $extractedText);
        $this->assertStringContainsString('Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)', $extractedText);
        $this->assertStringContainsString('Ratih Hanjar Dewanti, A.Md.Rad.', $extractedText);
    }

    public function test_generator_proves_no_python_or_process_in_production_class(): void
    {
        $ref = new ReflectionClass(AiPacsLaravelDerivedPdfGenerator::class);
        $fileName = $ref->getFileName();
        $this->assertIsString($fileName);
        $code = file_get_contents($fileName);

        $this->assertStringNotContainsString('python', strtolower($code));
        $this->assertStringNotContainsString('Symfony\Component\Process', $code);
        $this->assertStringNotContainsString('shell_exec', $code);
        $this->assertStringNotContainsString('exec(', $code);
        $this->assertStringNotContainsString('proc_open', $code);
    }

    public function test_generator_fails_safely_when_required_metadata_is_absent_without_defaults(): void
    {
        $generator = new AiPacsLaravelDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $baseProvenance = [
            'patientName' => 'Purnomo',
            'patientDobAge' => '15 Januari 1981 (45 tahun)',
            'patientGender' => 'Laki-laki',
            'patientMrn' => 'MRN-1787808860329',
            'examinationDate' => '27 Agustus 2026',
            'examinationArea' => 'Toraks',
            'findings' => 'Normal',
            'impression' => 'Normal',
            'radiographerName' => 'Ratih Hanjar Dewanti, A.Md.Rad.',
            'aiReviewer' => 'Madeena Intelligence (AI)',
            'reportDate' => '1 September 2026',
            'radiographImagePath' => $this->syntheticRadiographPath,
        ];

        // 1. Missing patientName fails with safe error, no default fallback
        $provWithoutName = $baseProvenance;
        unset($provWithoutName['patientName']);
        try {
            $generator->generateDerivedPdf($this->validOriginalPdf, $provWithoutName, $dest);
            $this->fail('Expected ImageGatewayException for missing patientName');
        } catch (ImageGatewayException $e) {
            $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $e->category);
            $this->assertStringContainsString('patientName', $e->getMessage());
        }

        // 2. Missing radiographerName fails with safe error, no hardcoded staff default
        $provWithoutRadiographer = $baseProvenance;
        unset($provWithoutRadiographer['radiographerName']);
        try {
            $generator->generateDerivedPdf($this->validOriginalPdf, $provWithoutRadiographer, $dest);
            $this->fail('Expected ImageGatewayException for missing radiographerName');
        } catch (ImageGatewayException $e) {
            $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $e->category);
            $this->assertStringContainsString('radiographerName', $e->getMessage());
        }

        // 3. Missing patientGender fails with safe error, no default 'Laki-laki' fallback
        $provWithoutGender = $baseProvenance;
        unset($provWithoutGender['patientGender']);
        try {
            $generator->generateDerivedPdf($this->validOriginalPdf, $provWithoutGender, $dest);
            $this->fail('Expected ImageGatewayException for missing patientGender');
        } catch (ImageGatewayException $e) {
            $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $e->category);
            $this->assertStringContainsString('patientGender', $e->getMessage());
        }
    }

    public function test_generator_fails_safely_on_metadata_mismatch_with_vendor_pdf(): void
    {
        // Create vendor PDF that has text mentioning "Patient Name: Budi"
        $mismatchPdfPath = $this->tempDir.'/mismatch_vendor.pdf';
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML('<p>Patient Name: Budi</p><p>MRN: MRN-1787808860329</p>');
        $mpdf->Output($mismatchPdfPath, \Mpdf\Output\Destination::FILE);

        $generator = new AiPacsLaravelDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $provenance = [
            'patientName' => 'Purnomo', // Mismatch! Vendor says Budi
            'patientDobAge' => '15 Januari 1981 (45 tahun)',
            'patientGender' => 'Laki-laki',
            'patientMrn' => 'MRN-1787808860329',
            'examinationDate' => '27 Agustus 2026',
            'examinationArea' => 'Toraks',
            'findings' => 'Normal',
            'impression' => 'Normal',
            'radiographerName' => 'Ratih Hanjar Dewanti, A.Md.Rad.',
            'aiReviewer' => 'Madeena Intelligence (AI)',
            'reportDate' => '1 September 2026',
            'radiographImagePath' => $this->syntheticRadiographPath,
        ];

        $this->expectException(ImageGatewayException::class);
        $this->expectExceptionMessage("Patient name mismatch");

        $generator->generateDerivedPdf($mismatchPdfPath, $provenance, $dest);
    }

    public function test_generator_fails_safely_when_source_radiograph_cannot_be_proven(): void
    {
        $generator = new AiPacsLaravelDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $provenance = [
            'patientName' => 'Purnomo',
            'patientDobAge' => '15 Januari 1981 (45 tahun)',
            'patientGender' => 'Laki-laki',
            'patientMrn' => 'MRN-1787808860329',
            'examinationDate' => '27 Agustus 2026',
            'examinationArea' => 'Toraks',
            'findings' => 'Normal',
            'impression' => 'Normal',
            'radiographerName' => 'Ratih Hanjar Dewanti, A.Md.Rad.',
            'aiReviewer' => 'Madeena Intelligence (AI)',
            'reportDate' => '1 September 2026',
            // No radiographImagePath provided, and validOriginalPdf has no images
        ];

        $this->expectException(ImageGatewayException::class);
        $this->expectExceptionMessage('Correct source radiograph image cannot be proven.');

        $generator->generateDerivedPdf($this->validOriginalPdf, $provenance, $dest);
    }

    public function test_generator_rejects_missing_original_pdf(): void
    {
        $generator = new AiPacsLaravelDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $this->expectException(ImageGatewayException::class);
        $this->expectExceptionMessage('Original vendor PDF does not exist');

        $generator->generateDerivedPdf($this->tempDir.'/nonexistent.pdf', [], $dest);
    }

    public function test_generator_rejects_malformed_original_pdf(): void
    {
        $corruptFile = $this->tempDir.'/corrupt.pdf';
        file_put_contents($corruptFile, 'NOT_A_VALID_PDF_FILE');

        $generator = new AiPacsLaravelDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $this->expectException(ImageGatewayException::class);

        try {
            $generator->generateDerivedPdf($corruptFile, [], $dest);
        } catch (ImageGatewayException $e) {
            $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $e->category);
            throw $e;
        }
    }
}
