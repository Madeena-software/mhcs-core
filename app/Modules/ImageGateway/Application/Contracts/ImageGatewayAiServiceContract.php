<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Storage\AccessGrant;

interface ImageGatewayAiServiceContract
{
    public const AI_DISPATCH_PURPOSE = 'image-gateway.ai.dispatch';

    public const AI_STATUS_PURPOSE = 'image-gateway.ai.status';

    public const AI_REPORT_PURPOSE = 'image-gateway.ai.report.read';

    /**
     * Idempotently dispatch AI analysis for an authorized DICOM study.
     *
     * @return array{
     *     ai_job_id: string,
     *     study_id: string,
     *     status: string,
     *     can_retry: bool,
     *     last_error_code: ?string,
     *     correlation_id: string,
     * }
     */
    public function dispatchStudy(string $studyId, AuthenticatedContext $context): array;

    /**
     * Retrieve the actionable, redacted status model for an AI analysis job.
     *
     * @return array{
     *     ai_job_id: string,
     *     study_id: string,
     *     status: string,
     *     can_retry: bool,
     *     last_error_code: ?string,
     *     correlation_id: string,
     * }|null
     */
    public function getStatus(string $studyId, AuthenticatedContext $context): ?array;

    /**
     * Retry an eligible failed AI analysis job.
     *
     * @return array{
     *     ai_job_id: string,
     *     study_id: string,
     *     status: string,
     *     can_retry: bool,
     *     last_error_code: ?string,
     *     correlation_id: string,
     * }
     */
    public function retryStudy(string $studyId, AuthenticatedContext $context): array;

    /**
     * Protected accessor for report objects, returning a signed grant if ready.
     */
    public function getReportAccess(string $studyId, AuthenticatedContext $context, string $type = 'derived'): ?AccessGrant;
}
