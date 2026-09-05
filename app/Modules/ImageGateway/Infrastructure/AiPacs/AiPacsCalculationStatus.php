<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

final readonly class AiPacsCalculationStatus
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $isCompleted,
        public bool $isFailed,
        public bool $isPending,
        public ?int $aiCalcId = null,
        public ?int $progressPercent = null,
        public ?string $errorCode = null,
        public array $metadata = [],
    ) {}

    public static function completed(int $aiCalcId, array $metadata = []): self
    {
        return new self(
            isCompleted: true,
            isFailed: false,
            isPending: false,
            aiCalcId: $aiCalcId,
            progressPercent: 100,
            metadata: $metadata,
        );
    }

    public static function pending(?int $progress = null, ?int $aiCalcId = null): self
    {
        return new self(
            isCompleted: false,
            isFailed: false,
            isPending: true,
            aiCalcId: $aiCalcId,
            progressPercent: $progress,
        );
    }

    public static function failed(string $errorCode, array $metadata = []): self
    {
        return new self(
            isCompleted: false,
            isFailed: true,
            isPending: false,
            errorCode: $errorCode,
            metadata: $metadata,
        );
    }
}
