<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain;

final readonly class AiPacsDerivedPdfResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $pdfBytes,
        public string $checksum,
        public int $bytes,
        public string $filename,
        public array $metadata = [],
    ) {}
}
