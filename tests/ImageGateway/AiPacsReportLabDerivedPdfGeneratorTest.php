<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsReportLabDerivedPdfGenerator;
use Tests\TestCase;

final class AiPacsReportLabDerivedPdfGeneratorTest extends TestCase
{
    private string $tempDir;

    private string $validOriginalPdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/derived_test_'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);

        // Generate a minimal valid PDF with a page
        $this->validOriginalPdf = $this->tempDir.'/valid_original.pdf';
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n180\n%%EOF";
        file_put_contents($this->validOriginalPdf, $content);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*") ?: []);
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_derived_generator_generates_valid_indonesian_pdf_with_logo(): void
    {
        $generator = new AiPacsReportLabDerivedPdfGenerator(
            pythonBinary: 'python3',
            scriptPath: app_path('Modules/ImageGateway/Infrastructure/AiPacs/Report/pacs_derived_pdf_generator.py'),
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
        ];

        $result = $generator->generateDerivedPdf($this->validOriginalPdf, $provenance, $dest);

        $this->assertFileExists($dest);
        $this->assertSame(hash('sha256', $result->pdfBytes), $result->checksum);
        $this->assertSame(strlen($result->pdfBytes), $result->bytes);
        $this->assertStringStartsWith('%PDF-', $result->pdfBytes);
        $this->assertStringContainsString('%%EOF', substr($result->pdfBytes, -4096));

        // Verify verbatim content is embedded by extracting text with pypdf
        $extractProcess = new \Symfony\Component\Process\Process([
            'python3',
            '-c',
            'import sys; from pypdf import PdfReader; print(PdfReader(sys.argv[1]).pages[0].extract_text())',
            $dest,
        ]);
        $extractProcess->run();
        $this->assertTrue($extractProcess->isSuccessful(), $extractProcess->getErrorOutput());
        $extractedText = $extractProcess->getOutput();

        $this->assertStringContainsString('Rumah Skrining', $extractedText);
        $this->assertStringContainsString('Purnomo', $extractedText);
        $this->assertStringContainsString('MRN-1787808860329', $extractedText);
        $this->assertStringContainsString('Toraks simetris', $extractedText);
        $this->assertStringContainsString('Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)', $extractedText);
    }

    public function test_derived_generator_rejects_missing_original_pdf(): void
    {
        $generator = new AiPacsReportLabDerivedPdfGenerator();
        $dest = $this->tempDir.'/fail_output.pdf';

        $this->expectException(ImageGatewayException::class);
        $this->expectExceptionMessage('Original vendor PDF does not exist');

        $generator->generateDerivedPdf($this->tempDir.'/nonexistent.pdf', [], $dest);
    }

    public function test_derived_generator_rejects_malformed_original_pdf(): void
    {
        $corruptFile = $this->tempDir.'/corrupt.pdf';
        file_put_contents($corruptFile, 'NOT_A_VALID_PDF_FILE');

        $generator = new AiPacsReportLabDerivedPdfGenerator();
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
