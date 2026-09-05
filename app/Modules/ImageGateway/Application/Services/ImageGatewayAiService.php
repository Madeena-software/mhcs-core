<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessAiPacsStudy;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Storage\AccessGrant;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ImageGatewayAiService implements ImageGatewayAiServiceContract
{
    public function __construct(
        private PrivateObjectStore $objects,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function dispatchStudy(string $studyId, AuthenticatedContext $context): array
    {
        $study = $this->findAuthorizedStudy($studyId);

        $outcome = $this->idempotency->run(
            "ai-dispatch:{$studyId}",
            'image-gateway.ai-dispatch',
            ['study_id' => $studyId],
            function () use ($study, $context): array {
                $existing = DB::table('image_gateway_ai_jobs')->where('study_id', $study->id)->first();
                if ($existing !== null) {
                    return $this->formatStatus($existing);
                }

                $aiJobId = (string) Str::uuid();
                $now = $this->clock->now();
                $correlationId = $context->operationId !== null
                    ? (string) $context->operationId
                    : (string) Str::uuid();

                try {
                    DB::transaction(function () use ($study, $aiJobId, $now, $correlationId, $context): void {
                        $concurrent = DB::table('image_gateway_ai_jobs')->where('study_id', $study->id)->first();
                        if ($concurrent !== null) {
                            return;
                        }

                        DB::table('image_gateway_ai_jobs')->insert([
                            'id' => $aiJobId,
                            'study_id' => $study->id,
                            'capture_set_id' => $study->capture_set_id,
                            'booking_id' => $study->booking_id,
                            'member_id' => $study->member_id,
                            'admission_id' => $study->admission_id,
                            'status' => AiJobStatus::QUEUED,
                            'attempts' => 0,
                            'max_attempts' => 3,
                            'correlation_id' => $correlationId,
                            'dispatched_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $this->audit->append(AuditEvent::fromContext(
                            context: $context,
                            action: 'image-gateway.ai-job-dispatched',
                            source: 'image-gateway',
                            outcome: 'success',
                            occurredAt: $now,
                            targetType: 'image-gateway.ai-job',
                            targetId: $aiJobId,
                            metadata: [
                                'study_id' => (string) $study->id,
                                'status' => AiJobStatus::QUEUED,
                            ],
                        ));

                        ProcessAiPacsStudy::dispatch($aiJobId)->onQueue('image-gateway')->afterCommit();
                    });
                } catch (QueryException $exception) {
                    $message = strtolower($exception->getMessage());
                    if (! str_contains($message, 'unique') && ! str_contains($message, 'duplicate')) {
                        throw $exception;
                    }
                }

                $persisted = DB::table('image_gateway_ai_jobs')->where('study_id', $study->id)->first();
                if ($persisted === null) {
                    throw new ImageGatewayException('dispatch_failed', 'Failed to persist AI job identity.');
                }

                return $this->formatStatus($persisted);
            },
        );

        /** @var array{ai_job_id: string, study_id: string, status: string, can_retry: bool, last_error_code: ?string, correlation_id: string} */
        return $outcome->result;
    }

    public function getStatus(string $studyId, AuthenticatedContext $context): ?array
    {
        $job = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        if ($job === null) {
            return null;
        }

        return $this->formatStatus($job);
    }

    public function retryStudy(string $studyId, AuthenticatedContext $context): array
    {
        $job = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        if ($job === null) {
            throw new ImageGatewayException('study_not_found', 'AI job not found for this study.');
        }

        if ($job->status !== AiJobStatus::RETRYABLE_FAILURE) {
            throw new ImageGatewayException('cannot_retry', 'AI job is not in a retryable failure state.');
        }

        if ((int) $job->attempts >= (int) $job->max_attempts) {
            throw new ImageGatewayException('retry_budget_exhausted', 'AI job retry budget has been exhausted.');
        }

        $now = $this->clock->now();

        DB::transaction(function () use ($job, $now, $context): void {
            DB::table('image_gateway_ai_jobs')->where('id', $job->id)->update([
                'status' => AiJobStatus::QUEUED,
                'last_error_code' => null,
                'processing_claim_id' => null,
                'processing_lease_expires_at' => null,
                'failed_at' => null,
                'updated_at' => $now,
            ]);

            $this->audit->append(AuditEvent::fromContext(
                context: $context,
                action: 'image-gateway.ai-job-retried',
                source: 'image-gateway',
                outcome: 'success',
                occurredAt: $now,
                targetType: 'image-gateway.ai-job',
                targetId: (string) $job->id,
                metadata: [
                    'study_id' => (string) $job->study_id,
                    'status' => AiJobStatus::QUEUED,
                ],
            ));

            ProcessAiPacsStudy::dispatch((string) $job->id)->onQueue('image-gateway')->afterCommit();
        });

        $updated = DB::table('image_gateway_ai_jobs')->where('id', $job->id)->first();

        return $this->formatStatus($updated ?? $job);
    }

    public function getReportAccess(string $studyId, AuthenticatedContext $context, string $type = 'derived'): ?AccessGrant
    {
        $job = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        if ($job === null || $job->status !== AiJobStatus::REPORT_READY) {
            return null;
        }

        $report = DB::table('image_gateway_ai_reports')->where('ai_job_id', $job->id)->first();
        if ($report === null) {
            return null;
        }

        $objectKey = $type === 'original' ? $report->original_object_key : $report->derived_object_key;
        $checksum = $type === 'original' ? $report->original_checksum : $report->derived_checksum;
        $bytes = $type === 'original' ? $report->original_bytes : $report->derived_bytes;

        if ($objectKey === null || $checksum === null || $bytes === null) {
            return null;
        }

        $privateObject = new PrivateObject(
            key: OpaqueObjectKey::fromString((string) $objectKey),
            checksum: (string) $checksum,
            bytes: (int) $bytes,
            createdAt: new DateTimeImmutable((string) $report->created_at),
        );

        return $this->objects->grant(
            object: $privateObject,
            context: $context,
            audience: 'image-gateway-ai',
            purpose: self::AI_REPORT_PURPOSE,
            expiresAt: $this->clock->now()->modify('+300 seconds'),
        );
    }

    private function findAuthorizedStudy(string $studyId): object
    {
        $study = DB::table('image_gateway_studies as studies')
            ->join('image_gateway_capture_sets as captures', 'captures.id', '=', 'studies.capture_set_id')
            ->join('bookings', 'bookings.id', '=', 'captures.booking_id')
            ->where('studies.id', $studyId)
            ->select([
                'studies.id',
                'studies.capture_set_id',
                'captures.booking_id',
                'captures.admission_id',
                'bookings.member_id',
            ])
            ->first();

        if ($study === null) {
            throw new ImageGatewayException('study_not_found', 'The specified DICOM study does not exist or has incomplete provenance.');
        }

        return $study;
    }

    /**
     * Format the public actionable status model exposing only non-sensitive, approved fields.
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
    private function formatStatus(object $job): array
    {
        $status = (string) $job->status;
        $attempts = (int) $job->attempts;
        $maxAttempts = (int) $job->max_attempts;

        $canRetry = $status === AiJobStatus::RETRYABLE_FAILURE && $attempts < $maxAttempts;
        $lastErrorCode = in_array($status, [AiJobStatus::RETRYABLE_FAILURE, AiJobStatus::TERMINAL_FAILURE], true)
            ? AiErrorCode::sanitize($job->last_error_code)
            : null;

        return [
            'ai_job_id' => (string) $job->id,
            'study_id' => (string) $job->study_id,
            'status' => $status,
            'can_retry' => $canRetry,
            'last_error_code' => $lastErrorCode,
            'correlation_id' => (string) ($job->correlation_id ?? ''),
        ];
    }
}
