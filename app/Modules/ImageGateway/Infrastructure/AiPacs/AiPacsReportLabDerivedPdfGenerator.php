<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

use App\Modules\ImageGateway\Application\Contracts\AiPacsDerivedPdfGeneratorContract;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiPacsDerivedPdfResult;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use Symfony\Component\Process\Process;
use Throwable;

final class AiPacsReportLabDerivedPdfGenerator implements AiPacsDerivedPdfGeneratorContract
{
    public function __construct(
        public readonly string $pythonBinary = 'python3',
        public readonly ?string $scriptPath = null,
        public readonly ?string $logoPath = null,
        public readonly int $timeout = 60,
    ) {}

    /**
     * @param array<string, mixed> $provenanceData
     */
    public function generateDerivedPdf(
        string $originalPdfPath,
        array $provenanceData,
        string $destinationPath,
    ): AiPacsDerivedPdfResult {
        if (! file_exists($originalPdfPath)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                "Original vendor PDF does not exist at path: {$originalPdfPath}",
            );
        }

        $effectiveScript = $this->scriptPath
            ?? app_path('Modules/ImageGateway/Infrastructure/AiPacs/Report/pacs_derived_pdf_generator.py');

        $effectiveLogo = $this->logoPath
            ?? resource_path('images/branding/rumah-skrining-logo.png');

        $payload = array_merge($provenanceData, [
            'originalPdfPath' => $originalPdfPath,
            'destinationPath' => $destinationPath,
            'logoPath' => $effectiveLogo,
        ]);

        $inputJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $process = new Process([$this->pythonBinary, $effectiveScript]);
        $process->setInput($inputJson);
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                'Failed to execute ReportLab derived PDF generator process.',
                $exception,
            );
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        $resultData = null;
        if ($stdout !== '') {
            $resultData = json_decode($stdout, true);
        }

        if (! is_array($resultData)) {
            $safeError = $this->sanitizeOutput($stderr !== '' ? $stderr : $stdout);
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                "Derived PDF worker returned invalid output: {$safeError}",
            );
        }

        if (($resultData['success'] ?? false) !== true) {
            $rawCode = strtolower((string) ($resultData['errorCode'] ?? ''));
            $errorCode = AiErrorCode::sanitize($rawCode) ?? AiErrorCode::PROCESSING_ERROR;
            $safeMessage = $this->sanitizeOutput((string) ($resultData['errorMessage'] ?? 'Unknown derived PDF generation failure.'));

            throw new ImageGatewayException($errorCode, $safeMessage);
        }

        if (! file_exists($destinationPath)) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                'Derived PDF worker indicated success but output file does not exist.',
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
        $expectedChecksum = (string) ($resultData['sha256'] ?? '');
        if ($expectedChecksum !== '' && ! hash_equals($expectedChecksum, $checksum)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Generated derived PDF checksum mismatch.',
            );
        }

        $filename = basename($destinationPath);

        return new AiPacsDerivedPdfResult(
            pdfBytes: $pdfBytes,
            checksum: $checksum,
            bytes: strlen($pdfBytes),
            filename: $filename,
            metadata: $resultData,
        );
    }

    private function sanitizeOutput(string $output): string
    {
        $sanitized = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return mb_substr($sanitized, 0, 200);
    }
}
