<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ConversionManifest
{
    public function __construct(
        public string $conversionJobId,
        public string $radiographChecksum,
        public string $gainChecksum,
        public string $metadataChecksum,
        public int $manifestVersion,
        public DateTimeImmutable $issuedAt,
        public string $correlationId,
        public string $keyId,
    ) {
        if (
            trim($this->conversionJobId) === ''
            || ! self::checksum($this->radiographChecksum)
            || ! self::checksum($this->gainChecksum)
            || ! self::checksum($this->metadataChecksum)
            || $this->manifestVersion < 1
            || trim($this->correlationId) === ''
            || trim($this->keyId) === ''
        ) {
            throw new InvalidArgumentException('Manifest identity and checksums are invalid.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        try {
            return new self(
                conversionJobId: (string) $data['conversion_job_id'],
                radiographChecksum: (string) $data['radiograph_checksum'],
                gainChecksum: (string) $data['gain_checksum'],
                metadataChecksum: (string) $data['metadata_checksum'],
                manifestVersion: (int) $data['manifest_version'],
                issuedAt: new DateTimeImmutable((string) $data['issued_at']),
                correlationId: (string) $data['correlation_id'],
                keyId: (string) $data['key_id'],
            );
        } catch (\Throwable $exception) {
            throw new ManifestVerificationException('Manifest data is malformed.', previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'conversion_job_id' => $this->conversionJobId,
            'radiograph_checksum' => $this->radiographChecksum,
            'gain_checksum' => $this->gainChecksum,
            'metadata_checksum' => $this->metadataChecksum,
            'manifest_version' => $this->manifestVersion,
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
            'key_id' => $this->keyId,
        ];
    }

    private static function checksum(string $checksum): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $checksum) === 1;
    }
}
