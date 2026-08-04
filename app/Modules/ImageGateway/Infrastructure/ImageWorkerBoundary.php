<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure;

use RuntimeException;

final class ImageWorkerBoundary
{
    public const CALLER = 'image-worker';

    public static function assertCaller(string $caller): void
    {
        if ($caller !== self::CALLER) {
            throw new RuntimeException('Only the Image Gateway worker may cross the private conversion boundary.');
        }
    }

    /** @return array<string, mixed> */
    public static function limits(): array
    {
        return [
            'private_network' => true,
            'public_proxy' => false,
            'application_database_credentials' => false,
            'payment_credentials' => false,
            'user_session_credentials' => false,
            'temporary_storage_bounded' => true,
            'business_state' => false,
        ];
    }
}
