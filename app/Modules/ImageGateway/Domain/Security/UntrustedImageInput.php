<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

final readonly class UntrustedImageInput
{
    public function __construct(
        public int $fileCount,
        public int $perFileBytes,
        public int $totalBytes,
        public int $decompressedBytes,
        public int $width,
        public int $height,
        public int $fieldCount,
        public int $cpuSeconds,
        public int $memoryBytes,
        public int $executionSeconds,
        public int $processCount,
        public int $temporaryStorageBytes,
        public string $form,
        public int $recoveryWindowSeconds,
        public int $attempts,
    ) {}
}
