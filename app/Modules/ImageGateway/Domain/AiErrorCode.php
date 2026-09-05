<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain;

final readonly class AiErrorCode
{
    public const AI_PACS_AUTH_FAILED = 'ai_pacs_auth_failed';

    public const AI_PACS_TIMEOUT = 'ai_pacs_timeout';

    public const AI_PACS_RATE_LIMITED = 'ai_pacs_rate_limited';

    public const AI_PACS_UNAVAILABLE = 'ai_pacs_unavailable';

    public const AI_PACS_UPLOAD_FAILED = 'ai_pacs_upload_failed';

    public const AI_PACS_REPORT_DOWNLOAD_FAILED = 'ai_pacs_report_download_failed';

    public const AI_PACS_INVALID_REPORT = 'ai_pacs_invalid_report';

    public const RETRY_BUDGET_EXHAUSTED = 'retry_budget_exhausted';

    public const STUDY_NOT_FOUND = 'study_not_found';

    public const UNAUTHORIZED_STUDY = 'unauthorized_study';

    public const PROCESSING_ERROR = 'processing_error';

    public const ALL = [
        self::AI_PACS_AUTH_FAILED,
        self::AI_PACS_TIMEOUT,
        self::AI_PACS_RATE_LIMITED,
        self::AI_PACS_UNAVAILABLE,
        self::AI_PACS_UPLOAD_FAILED,
        self::AI_PACS_REPORT_DOWNLOAD_FAILED,
        self::AI_PACS_INVALID_REPORT,
        self::RETRY_BUDGET_EXHAUSTED,
        self::STUDY_NOT_FOUND,
        self::UNAUTHORIZED_STUDY,
        self::PROCESSING_ERROR,
    ];

    public static function isSafeCode(string $code): bool
    {
        return in_array($code, self::ALL, true);
    }

    public static function sanitize(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return self::isSafeCode($code) ? $code : self::PROCESSING_ERROR;
    }
}
