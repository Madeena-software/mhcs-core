<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

final readonly class SignedManifest
{
    public function __construct(
        public ConversionManifest $manifest,
        public string $signature,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'manifest' => $this->manifest->toArray(),
            'signature' => $this->signature,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! is_array($data['manifest'] ?? null) || ! is_string($data['signature'] ?? null)) {
            throw new ManifestVerificationException('Signed manifest is malformed.');
        }

        return new self(ConversionManifest::fromArray($data['manifest']), $data['signature']);
    }
}
