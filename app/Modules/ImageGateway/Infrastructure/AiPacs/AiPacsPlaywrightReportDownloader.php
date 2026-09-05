<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

use App\Modules\ImageGateway\Application\Contracts\AiPacsReportDownloaderContract;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use Symfony\Component\Process\Process;
use Throwable;

final class AiPacsPlaywrightReportDownloader implements AiPacsReportDownloaderContract
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    private string $pythonBinary;

    private string $scriptPath;

    public function __construct(
        ?string $baseUrl = null,
        ?string $username = null,
        ?string $password = null,
        ?int $timeout = null,
        ?string $pythonBinary = null,
        ?string $scriptPath = null,
    ) {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('services.ai_pacs.base_url', 'http://124.225.183.175:8361')), '/');
        $this->username = (string) ($username ?? config('services.ai_pacs.username', ''));
        $this->password = (string) ($password ?? config('services.ai_pacs.password', ''));
        $this->timeout = (int) ($timeout ?? config('services.ai_pacs.playwright_timeout_seconds', 120));
        $this->pythonBinary = $pythonBinary ?? config('services.ai_pacs.python_binary', 'python3');
        $this->scriptPath = $scriptPath ?? __DIR__.'/Playwright/pacs_report_downloader.py';
    }

    public function downloadImageReport(
        string|int $studyIdentifier,
        int $aiCalcId,
        string $destinationPath,
        ?string $correlationId = null,
        string $viewerType = 'CR',
        string $pacs = 'fei',
    ): AiPacsReportResult {
        $this->assertConfigured();

        $inputPayload = [
            'baseUrl' => $this->baseUrl,
            'username' => $this->username,
            'password' => $this->password,
            'sid' => (int) $studyIdentifier,
            'aiCalcId' => $aiCalcId,
            'viewerType' => $viewerType,
            'pacs' => $pacs,
            'destinationPath' => $destinationPath,
            'correlationId' => $correlationId ?? '',
        ];

        $inputJson = json_encode($inputPayload, JSON_THROW_ON_ERROR);

        $process = new Process([$this->pythonBinary, $this->scriptPath]);
        $process->setInput($inputJson);
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Failed to execute Playwright report downloader process.',
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
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                "Playwright report worker returned invalid output: {$safeError}",
            );
        }

        if (($resultData['success'] ?? false) !== true) {
            $errorCode = AiErrorCode::sanitize((string) ($resultData['errorCode'] ?? ''))
                ?? AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED;
            $safeMessage = $this->sanitizeOutput((string) ($resultData['errorMessage'] ?? 'Unknown report download failure.'));

            throw new ImageGatewayException($errorCode, $safeMessage);
        }

        if (($resultData['aiReportSelected'] ?? false) !== true) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Playwright worker did not confirm AI Report selection.',
            );
        }

        if (($resultData['imageReportSelected'] ?? false) !== true) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Playwright worker did not confirm Image Report selection.',
            );
        }

        if (($resultData['customReportInactive'] ?? true) !== true) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Custom Report must remain inactive per safety policy.',
            );
        }

        if (! file_exists($destinationPath) || ! is_readable($destinationPath)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Downloaded report PDF file does not exist at destination path.',
            );
        }

        $pdfBytes = file_get_contents($destinationPath);
        if ($pdfBytes === false || strlen($pdfBytes) === 0) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Downloaded report PDF file is empty.',
            );
        }

        $filename = basename($destinationPath);

        return new AiPacsReportResult(
            pdfBytes: $pdfBytes,
            filename: $filename,
            metadata: $resultData,
        );
    }

    private function assertConfigured(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                'AI PACS base URL is not configured.',
            );
        }

        if (trim($this->username) === '' || trim($this->password) === '') {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_AUTH_FAILED,
                'AI PACS credentials are not configured.',
            );
        }

        if (! file_exists($this->scriptPath)) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                "Playwright report downloader script not found at {$this->scriptPath}.",
            );
        }
    }

    private function sanitizeOutput(string $output): string
    {
        $sanitized = $output;
        if ($this->username !== '') {
            $sanitized = str_replace($this->username, '[REDACTED]', $sanitized);
        }
        if ($this->password !== '') {
            $sanitized = str_replace($this->password, '[REDACTED]', $sanitized);
        }

        // Limit length
        if (strlen($sanitized) > 255) {
            $sanitized = substr($sanitized, 0, 252).'...';
        }

        return $sanitized;
    }
}
