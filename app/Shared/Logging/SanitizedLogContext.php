<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Security\SensitiveDataSanitizer;

final class SanitizedLogContext
{
    /** @param array<string, mixed> $context */
    public static function sanitize(array $context): array
    {
        return SensitiveDataSanitizer::sanitize($context);
    }
}
