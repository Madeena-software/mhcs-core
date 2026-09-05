<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

final readonly class AiPacsUploadResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string|int $studyIdentifier,
        public ?int $aiCalcId = null,
        public ?string $seriesUid = null,
        public string $rawStatus = 'uploaded',
        public array $metadata = [],
    ) {}
}
