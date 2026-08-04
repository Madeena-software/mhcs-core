<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

final readonly class ValidationEvidence
{
    /**
     * @param  array<string, string>  $identifiers
     */
    public function __construct(
        public bool $valid,
        public string $conversionJobId,
        public string $radiographChecksum,
        public string $gainChecksum,
        public string $metadataChecksum,
        public string $manifestSignature,
        public array $identifiers,
    ) {}
}
