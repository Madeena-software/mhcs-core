<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;

final readonly class AiPacsReportResult
{
    public string $checksum;

    public int $bytes;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $pdfBytes,
        public string $filename,
        public array $metadata = [],
    ) {
        $this->bytes = strlen($pdfBytes);
        $this->checksum = hash('sha256', $pdfBytes);

        if (! str_starts_with($pdfBytes, '%PDF-')) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Downloaded AI report is missing the standard PDF header marker.',
            );
        }

        if ($this->bytes < 100) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Downloaded AI report payload is suspiciously undersized.',
            );
        }

        if (! str_contains(substr($pdfBytes, -4096), '%%EOF')) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_INVALID_REPORT,
                'Downloaded AI report is missing the standard PDF EOF marker.',
            );
        }
    }
}
