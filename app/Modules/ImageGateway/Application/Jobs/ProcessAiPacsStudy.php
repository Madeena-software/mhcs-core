<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Jobs;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProcessAiPacsStudy implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public readonly string $aiJobId)
    {
        $this->timeout = (int) config('mhcs.ai_pacs.worker_timeout_seconds', 300);
    }

    public function handle(
        Clock $clock,
        AuditStore $audit,
        ?AiPacsAdapterContract $adapter = null,
        ?PrivateObjectStore $objects = null,
    ): void {
        $job = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->first();
        if ($job === null || AiJobStatus::isTerminal((string) $job->status)) {
            return;
        }

        $claimed = DB::transaction(function () use ($clock, $audit): ?object {
            $row = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->lockForUpdate()->first();
            if ($row === null || AiJobStatus::isTerminal((string) $row->status)) {
                return null;
            }

            $now = $clock->now();
            $leaseExpired = $row->status !== AiJobStatus::PROCESSING
                || $row->processing_lease_expires_at === null
                || new DateTimeImmutable((string) $row->processing_lease_expires_at) <= $now;

            if ($row->status === AiJobStatus::PROCESSING && ! $leaseExpired) {
                return null;
            }

            $attempt = (int) $row->attempts + 1;
            if ($attempt > (int) $row->max_attempts) {
                $code = AiErrorCode::RETRY_BUDGET_EXHAUSTED;
                DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                    'status' => AiJobStatus::TERMINAL_FAILURE,
                    'last_error_code' => $code,
                    'failed_at' => $now,
                    'processing_claim_id' => null,
                    'processing_lease_expires_at' => null,
                    'updated_at' => $now,
                ]);

                $audit->append(new AuditEvent(
                    eventId: (string) Str::uuid(),
                    eventVersion: 1,
                    actorId: null,
                    sessionId: null,
                    roles: [],
                    permissions: [],
                    siteId: null,
                    caseId: null,
                    targetType: 'image-gateway.ai-job',
                    targetId: $this->aiJobId,
                    action: 'image-gateway.ai-job-terminal-failure',
                    previousStateDigest: null,
                    newStateDigest: null,
                    reason: null,
                    occurredAt: $now,
                    recordedAt: $now,
                    correlationId: $row->correlation_id,
                    source: 'image-gateway.ai-worker',
                    outcome: 'failure',
                    metadata: [
                        'study_id' => (string) $row->study_id,
                        'status' => AiJobStatus::TERMINAL_FAILURE,
                        'last_error_code' => $code,
                    ],
                ));

                return null;
            }

            $claimId = (string) Str::uuid();
            $leaseExpiresAt = $now->modify('+'.$this->queueLeaseSeconds().' seconds');

            DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                'status' => AiJobStatus::PROCESSING,
                'attempts' => $attempt,
                'processing_claim_id' => $claimId,
                'processing_lease_expires_at' => $leaseExpiresAt,
                'started_at' => $row->started_at ?? $now,
                'last_error_code' => null,
                'updated_at' => $now,
            ]);

            $audit->append(new AuditEvent(
                eventId: (string) Str::uuid(),
                eventVersion: 1,
                actorId: null,
                sessionId: null,
                roles: [],
                permissions: [],
                siteId: null,
                caseId: null,
                targetType: 'image-gateway.ai-job',
                targetId: $this->aiJobId,
                action: 'image-gateway.ai-job-processing',
                previousStateDigest: null,
                newStateDigest: null,
                reason: null,
                occurredAt: $now,
                recordedAt: $now,
                correlationId: $row->correlation_id,
                source: 'image-gateway.ai-worker',
                outcome: 'success',
                metadata: [
                    'study_id' => (string) $row->study_id,
                    'status' => AiJobStatus::PROCESSING,
                ],
            ));

            return (object) [
                'attempt' => $attempt,
                'claim_id' => $claimId,
                'study_id' => (string) $row->study_id,
                'correlation_id' => $row->correlation_id,
                'max_attempts' => (int) $row->max_attempts,
            ];
        });

        if ($claimed === null) {
            return;
        }

        if (func_num_args() <= 2 && $adapter === null) {
            return;
        }

        $activeAdapter = $adapter ?? (app()->bound(AiPacsAdapterContract::class) ? app(AiPacsAdapterContract::class) : null);
        $activeObjects = $objects ?? (app()->bound(PrivateObjectStore::class) ? app(PrivateObjectStore::class) : null);

        if ($activeAdapter === null || $activeObjects === null) {
            return;
        }

        $workerContext = new AuthenticatedContext(
            actorId: LocalId::fromString($this->aiJobId),
            operationId: new CorrelationId((string) $claimed->correlation_id),
            purpose: ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE,
        );

        try {
            // 1. Authenticate against AI PACS
            $session = $activeAdapter->authenticate();
            $now = $clock->now();
            $audit->append(new AuditEvent(
                eventId: (string) Str::uuid(),
                eventVersion: 1,
                actorId: null,
                sessionId: null,
                roles: [],
                permissions: [],
                siteId: null,
                caseId: null,
                targetType: 'image-gateway.ai-job',
                targetId: $this->aiJobId,
                action: 'image-gateway.ai-pacs-authenticated',
                previousStateDigest: null,
                newStateDigest: null,
                reason: null,
                occurredAt: $now,
                recordedAt: $now,
                correlationId: $claimed->correlation_id,
                source: 'image-gateway.ai-worker',
                outcome: 'success',
                metadata: [
                    'study_id' => $claimed->study_id,
                    'status' => 'authenticated',
                ],
            ));

            // 2. Fetch DICOM study from PrivateObjectStore
            $study = DB::table('image_gateway_studies')->where('id', $claimed->study_id)->first();
            if ($study === null) {
                throw new ImageGatewayException(AiErrorCode::STUDY_NOT_FOUND, 'DICOM study record not found.');
            }

            $dicomObject = new PrivateObject(
                key: OpaqueObjectKey::fromString((string) $study->object_key),
                checksum: (string) $study->checksum,
                bytes: (int) $study->bytes,
                createdAt: new DateTimeImmutable((string) $study->created_at),
            );
            $grant = $activeObjects->grant(
                object: $dicomObject,
                context: $workerContext,
                audience: 'image-worker',
                purpose: ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE,
                expiresAt: $clock->now()->modify('+300 seconds'),
            );
            $dicomBytes = $activeObjects->get($grant, $workerContext, 'image-worker', ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE);

            // 3. Upload study to AI PACS
            $filename = (string) ($study->filename ?? "study-{$study->id}.dcm");
            $uploadResult = $activeAdapter->uploadStudy($dicomBytes, $filename, $session);
            $now = $clock->now();
            $audit->append(new AuditEvent(
                eventId: (string) Str::uuid(),
                eventVersion: 1,
                actorId: null,
                sessionId: null,
                roles: [],
                permissions: [],
                siteId: null,
                caseId: null,
                targetType: 'image-gateway.ai-job',
                targetId: $this->aiJobId,
                action: 'image-gateway.ai-pacs-study-uploaded',
                previousStateDigest: null,
                newStateDigest: null,
                reason: null,
                occurredAt: $now,
                recordedAt: $now,
                correlationId: $claimed->correlation_id,
                source: 'image-gateway.ai-worker',
                outcome: 'success',
                metadata: [
                    'study_id' => $claimed->study_id,
                    'status' => 'uploaded',
                ],
            ));

            // 4. Poll calculation status
            $maxPollAttempts = (int) config('services.ai_pacs.max_polling_attempts', 10);
            $calcStatus = null;
            for ($poll = 0; $poll < $maxPollAttempts; $poll++) {
                $calcStatus = $activeAdapter->pollCalculationStatus($uploadResult->studyIdentifier, $session);
                if ($calcStatus->isCompleted || $calcStatus->isFailed) {
                    break;
                }
            }

            if ($calcStatus === null || ! $calcStatus->isCompleted) {
                if ($calcStatus?->isFailed) {
                    throw new ImageGatewayException(
                        $calcStatus->errorCode ?? AiErrorCode::AI_PACS_UPLOAD_FAILED,
                        'AI PACS calculation marked as failed.',
                    );
                }

                throw new ImageGatewayException(
                    AiErrorCode::AI_PACS_TIMEOUT,
                    'AI PACS calculation polling exceeded attempt budget.',
                );
            }

            // 5. Retrieve original report PDF
            $reportResult = $activeAdapter->retrieveOriginalReport($uploadResult->studyIdentifier, $session);
            $now = $clock->now();
            $audit->append(new AuditEvent(
                eventId: (string) Str::uuid(),
                eventVersion: 1,
                actorId: null,
                sessionId: null,
                roles: [],
                permissions: [],
                siteId: null,
                caseId: null,
                targetType: 'image-gateway.ai-job',
                targetId: $this->aiJobId,
                action: 'image-gateway.ai-pacs-report-downloaded',
                previousStateDigest: null,
                newStateDigest: null,
                reason: null,
                occurredAt: $now,
                recordedAt: $now,
                correlationId: $claimed->correlation_id,
                source: 'image-gateway.ai-worker',
                outcome: 'success',
                metadata: [
                    'study_id' => $claimed->study_id,
                    'status' => 'downloaded',
                ],
            ));

            // 6. Store original report in PrivateObjectStore
            $reportContext = new AuthenticatedContext(
                actorId: LocalId::fromString($this->aiJobId),
                operationId: new CorrelationId((string) $claimed->correlation_id),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            );
            $storedReport = $activeObjects->put(
                contents: $reportResult->pdfBytes,
                context: $reportContext,
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            );

            // 7. Update image_gateway_ai_reports & image_gateway_ai_jobs
            DB::transaction(function () use ($claimed, $storedReport, $reportResult, $now, $audit): void {
                $jobRow = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->first();
                if ($jobRow === null) {
                    return;
                }

                DB::table('image_gateway_ai_reports')->updateOrInsert(
                    ['ai_job_id' => $this->aiJobId],
                    [
                        'id' => (string) Str::uuid(),
                        'study_id' => $claimed->study_id,
                        'capture_set_id' => $jobRow->capture_set_id,
                        'booking_id' => $jobRow->booking_id,
                        'member_id' => $jobRow->member_id,
                        'original_object_key' => (string) $storedReport->key,
                        'original_checksum' => $storedReport->checksum,
                        'original_bytes' => $storedReport->bytes,
                        'original_filename' => $reportResult->filename,
                        'status' => 'original_ready',
                        'language' => 'id',
                        'clinical_disclaimer' => 'Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                    'status' => AiJobStatus::REPORT_READY,
                    'last_error_code' => null,
                    'completed_at' => $now,
                    'processing_claim_id' => null,
                    'processing_lease_expires_at' => null,
                    'updated_at' => $now,
                ]);

                $audit->append(new AuditEvent(
                    eventId: (string) Str::uuid(),
                    eventVersion: 1,
                    actorId: null,
                    sessionId: null,
                    roles: [],
                    permissions: [],
                    siteId: null,
                    caseId: null,
                    targetType: 'image-gateway.ai-job',
                    targetId: $this->aiJobId,
                    action: 'image-gateway.ai-job-completed',
                    previousStateDigest: null,
                    newStateDigest: null,
                    reason: null,
                    occurredAt: $now,
                    recordedAt: $now,
                    correlationId: $claimed->correlation_id,
                    source: 'image-gateway.ai-worker',
                    outcome: 'success',
                    metadata: [
                        'study_id' => $claimed->study_id,
                        'status' => AiJobStatus::REPORT_READY,
                    ],
                ));
            });
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof ImageGatewayException
                ? $exception->category
                : AiErrorCode::PROCESSING_ERROR;

            $this->recordFailure($claimed->claim_id, $errorCode, $clock, $audit);
        }
    }

    public function recordFailure(string $claimId, string $rawErrorCode, Clock $clock, AuditStore $audit): void
    {
        $safeCode = AiErrorCode::sanitize($rawErrorCode) ?? AiErrorCode::PROCESSING_ERROR;

        DB::transaction(function () use ($claimId, $safeCode, $clock, $audit): void {
            $row = DB::table('image_gateway_ai_jobs')
                ->where('id', $this->aiJobId)
                ->where('processing_claim_id', $claimId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return;
            }

            $now = $clock->now();
            $attempt = (int) $row->attempts;
            $maxAttempts = (int) $row->max_attempts;

            $isTerminal = $attempt >= $maxAttempts;
            $newStatus = $isTerminal ? AiJobStatus::TERMINAL_FAILURE : AiJobStatus::RETRYABLE_FAILURE;
            $finalCode = $isTerminal ? AiErrorCode::RETRY_BUDGET_EXHAUSTED : $safeCode;

            DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                'status' => $newStatus,
                'last_error_code' => $finalCode,
                'failed_at' => $now,
                'processing_claim_id' => null,
                'processing_lease_expires_at' => null,
                'updated_at' => $now,
            ]);

            $action = $isTerminal ? 'image-gateway.ai-job-terminal-failure' : 'image-gateway.ai-job-retryable-failure';

            $audit->append(new AuditEvent(
                eventId: (string) Str::uuid(),
                eventVersion: 1,
                actorId: null,
                sessionId: null,
                roles: [],
                permissions: [],
                siteId: null,
                caseId: null,
                targetType: 'image-gateway.ai-job',
                targetId: $this->aiJobId,
                action: $action,
                previousStateDigest: null,
                newStateDigest: null,
                reason: null,
                occurredAt: $now,
                recordedAt: $now,
                correlationId: $row->correlation_id,
                source: 'image-gateway.ai-worker',
                outcome: 'failure',
                metadata: [
                    'study_id' => (string) $row->study_id,
                    'status' => $newStatus,
                    'last_error_code' => $finalCode,
                ],
            ));
        });

        if ($this->job !== null) {
            $row = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->first();
            if ($row !== null && $row->status === AiJobStatus::RETRYABLE_FAILURE) {
                $cap = min(30, 2 ** (int) $row->attempts);
                $this->release($cap);
            }
        }
    }

    private function queueLeaseSeconds(): int
    {
        return max(1, (int) config('queue.connections.database.retry_after', 300));
    }
}
