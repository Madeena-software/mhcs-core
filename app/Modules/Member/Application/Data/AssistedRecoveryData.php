<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

final readonly class AssistedRecoveryData
{
    public function __construct(
        public string $operationId,
        public string $nik,
        public string $kk,
        public string $identityAssetId,
        public string $profilePhotoAssetId,
    ) {}
}
