<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

use InvalidArgumentException;

final readonly class UntrustedImagePolicy
{
    /**
     * @param  list<string>  $acceptedForms
     */
    public function __construct(
        public int $fileCount,
        public int $perFileBytes,
        public int $totalBytes,
        public int $decompressedBytes,
        public int $maxWidth,
        public int $maxHeight,
        public int $fieldCount,
        public int $cpuSeconds,
        public int $memoryBytes,
        public int $executionSeconds,
        public int $processCount,
        public int $temporaryStorageBytes,
        public array $acceptedForms,
        public int $recoveryWindowSeconds,
        public int $maxAttempts,
    ) {
        foreach ([
            $this->fileCount,
            $this->perFileBytes,
            $this->totalBytes,
            $this->decompressedBytes,
            $this->maxWidth,
            $this->maxHeight,
            $this->fieldCount,
            $this->cpuSeconds,
            $this->memoryBytes,
            $this->executionSeconds,
            $this->processCount,
            $this->temporaryStorageBytes,
            $this->recoveryWindowSeconds,
            $this->maxAttempts,
        ] as $limit) {
            if ($limit < 1) {
                throw new InvalidArgumentException('Untrusted-input limits must be positive.');
            }
        }

        if ($this->acceptedForms === []) {
            throw new InvalidArgumentException('At least one accepted input form is required.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        $forms = $config['accepted_forms'] ?? null;

        if (is_string($forms)) {
            $forms = array_values(array_filter(array_map('trim', explode(',', $forms))));
        }

        if (! is_array($forms)) {
            throw new UntrustedImagePolicyException('Untrusted-input policy is missing accepted forms.');
        }

        $values = [];

        foreach ([
            'file_count',
            'per_file_bytes',
            'total_bytes',
            'decompressed_bytes',
            'max_width',
            'max_height',
            'field_count',
            'cpu_seconds',
            'memory_bytes',
            'execution_seconds',
            'process_count',
            'temporary_storage_bytes',
            'recovery_window_seconds',
            'max_attempts',
        ] as $key) {
            $value = $config[$key] ?? null;

            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }

            if (! is_int($value) || $value < 1) {
                throw new UntrustedImagePolicyException("Untrusted-input policy value [{$key}] is missing or invalid.");
            }

            $values[] = $value;
        }

        return new self(
            fileCount: $values[0],
            perFileBytes: $values[1],
            totalBytes: $values[2],
            decompressedBytes: $values[3],
            maxWidth: $values[4],
            maxHeight: $values[5],
            fieldCount: $values[6],
            cpuSeconds: $values[7],
            memoryBytes: $values[8],
            executionSeconds: $values[9],
            processCount: $values[10],
            temporaryStorageBytes: $values[11],
            acceptedForms: array_values(array_filter($forms, 'is_string')),
            recoveryWindowSeconds: $values[12],
            maxAttempts: $values[13],
        );
    }

    public function assertWithin(UntrustedImageInput $input): void
    {
        $checks = [
            [$input->fileCount, $this->fileCount],
            [$input->perFileBytes, $this->perFileBytes],
            [$input->totalBytes, $this->totalBytes],
            [$input->decompressedBytes, $this->decompressedBytes],
            [$input->width, $this->maxWidth],
            [$input->height, $this->maxHeight],
            [$input->fieldCount, $this->fieldCount],
            [$input->cpuSeconds, $this->cpuSeconds],
            [$input->memoryBytes, $this->memoryBytes],
            [$input->executionSeconds, $this->executionSeconds],
            [$input->processCount, $this->processCount],
            [$input->temporaryStorageBytes, $this->temporaryStorageBytes],
            [$input->recoveryWindowSeconds, $this->recoveryWindowSeconds],
            [$input->attempts, $this->maxAttempts],
        ];

        foreach ($checks as [$actual, $limit]) {
            if ($actual < 0 || $actual > $limit) {
                throw new UntrustedImagePolicyException('Untrusted input exceeds a configured bound.');
            }
        }

        if (! in_array($input->form, $this->acceptedForms, true)) {
            throw new UntrustedImagePolicyException('The input container form is not accepted.');
        }
    }
}
