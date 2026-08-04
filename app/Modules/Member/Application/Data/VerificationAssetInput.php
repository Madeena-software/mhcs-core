<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Shared\Storage\PrivateObject;

final readonly class VerificationAssetInput
{
    public function __construct(
        public VerificationAssetType $type,
        public PrivateObject $object,
        public ?string $format = null,
        public ?string $replacesId = null,
    ) {}
}
