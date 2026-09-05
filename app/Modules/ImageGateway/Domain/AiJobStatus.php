<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain;

final readonly class AiJobStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const RETRYABLE_FAILURE = 'retryable_failure';

    public const TERMINAL_FAILURE = 'terminal_failure';

    public const REPORT_READY = 'report_ready';

    public const ALL = [
        self::QUEUED,
        self::PROCESSING,
        self::RETRYABLE_FAILURE,
        self::TERMINAL_FAILURE,
        self::REPORT_READY,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::TERMINAL_FAILURE, self::REPORT_READY], true);
    }
}
