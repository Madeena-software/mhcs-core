<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum VerificationAssetType: string
{
    case Ktp = 'ktp';
    case Kia = 'kia';
    case ProfilePhoto = 'profile_photo';

    public function isIdentityDocument(): bool
    {
        return $this !== self::ProfilePhoto;
    }
}
