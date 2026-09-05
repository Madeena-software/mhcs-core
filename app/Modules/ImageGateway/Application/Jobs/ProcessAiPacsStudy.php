<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Jobs;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Application\Contracts\AiPacsReportDownloaderContract;
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
use Illuminate\Support\Facades\Http;
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
        ?AiPacsReportDownloaderContract $downloader = null,
    ): void {
        $job = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->first();
        if ($job === null) {
            return;
        }

        // Idempotency: If already report_ready and report exists in PrivateObjectStore, return idempotently
        if ($job->status === AiJobStatus::REPORT_READY) {
            $existingReport = DB::table('image_gateway_ai_reports')->where('ai_job_id', $this->aiJobId)->first();
            if ($existingReport !== null && $existingReport->original_object_key !== null && $existingReport->original_checksum !== null) {
                return;
            }
        }

        if (AiJobStatus::isTerminal((string) $job->status)) {
            return;
        }

        $claimed = DB::transaction(function () use ($clock, $audit): ?object {
            $row = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }

            if ($row->status === AiJobStatus::REPORT_READY) {
                return null;
            }

            if (AiJobStatus::isTerminal((string) $row->status)) {
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
                'pacs_sid' => $row->pacs_sid !== null ? (int) $row->pacs_sid : null,
                'pacs_ai_calc_id' => $row->pacs_ai_calc_id !== null ? (int) $row->pacs_ai_calc_id : null,
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
        $activeDownloader = $downloader;

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

            $study = DB::table('image_gateway_studies')->where('id', $claimed->study_id)->first();
            if ($study === null) {
                throw new ImageGatewayException(AiErrorCode::STUDY_NOT_FOUND, 'DICOM study record not found.');
            }

            $accession = (string) ($study->display_reference ?? $study->id);
            $sid = $claimed->pacs_sid;
            $aiCalcId = $claimed->pacs_ai_calc_id;

            // 2. Durable upload stage & reconciliation check
            if ($sid === null) {
                // Fetch DICOM study from PrivateObjectStore
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
                    expiresAt: $clock->now()->modify('+600 seconds'),
                );
                $dicomBytes = $activeObjects->get($grant, $workerContext, 'image-worker', ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE);
                $effectiveAccession = $this->extractAccessionNumber($dicomBytes) ?? $accession;

                // Check if study already exists on vendor (e.g. registered during a timed-out upload attempt)
                $reconciled = $activeAdapter->findStudyByAccession($effectiveAccession, $session);
                if ($reconciled !== null) {
                    $sid = (int) $reconciled->studyIdentifier;
                    $aiCalcId = $reconciled->aiCalcId;

                    DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                        'pacs_sid' => $sid,
                        'pacs_ai_calc_id' => $aiCalcId,
                        'updated_at' => $clock->now(),
                    ]);
                } else {
                    // 3. Upload study with fixed-length request
                    $filename = (string) ($study->filename ?? "study-{$study->id}.dcm");
                    try {
                        $uploadResult = $activeAdapter->uploadStudy($dicomBytes, $filename, $session, $effectiveAccession);
                        $sid = (int) $uploadResult->studyIdentifier;
                        $aiCalcId = $uploadResult->aiCalcId;
                    } catch (ImageGatewayException $uploadException) {
                        if ($uploadException->category === AiErrorCode::AI_PACS_TIMEOUT) {
                            // Ambiguous timeout: reconcile against studies list
                            $reconciled = $activeAdapter->findStudyByAccession($effectiveAccession, $session);
                            if ($reconciled !== null) {
                                $sid = (int) $reconciled->studyIdentifier;
                                $aiCalcId = $reconciled->aiCalcId;
                            } else {
                                throw $uploadException;
                            }
                        } else {
                            throw $uploadException;
                        }
                    }

                    DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                        'pacs_sid' => $sid,
                        'pacs_ai_calc_id' => $aiCalcId,
                        'updated_at' => $clock->now(),
                    ]);

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
                            'pacs_sid' => $sid,
                            'status' => 'uploaded',
                        ],
                    ));
                }
            }

            // 4. Poll calculation status if not already completed
            if ($aiCalcId === null) {
                $maxPollAttempts = (int) config('services.ai_pacs.max_polling_attempts', 30);
                $pollInterval = (int) config('services.ai_pacs.polling_interval_seconds', 2);
                $calcStatus = null;
                for ($poll = 0; $poll < $maxPollAttempts; $poll++) {
                    $calcStatus = $activeAdapter->pollCalculationStatus($sid, $session);
                    if ($calcStatus->isCompleted || $calcStatus->isFailed) {
                        break;
                    }
                    if ($poll < $maxPollAttempts - 1 && $pollInterval > 0) {
                        sleep($pollInterval);
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

                $aiCalcId = $calcStatus->aiCalcId ?? $sid;
                DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                    'pacs_ai_calc_id' => $aiCalcId,
                    'updated_at' => $clock->now(),
                ]);
            }

            // 5. Retrieve original Image Report PDF
            if ($activeDownloader !== null && $aiCalcId !== null) {
                $tempDest = sys_get_temp_dir().'/ai_pacs_report_'.$this->aiJobId.'_'.Str::uuid().'.pdf';
                try {
                    $reportResult = $activeDownloader->downloadImageReport(
                        studyIdentifier: $sid,
                        aiCalcId: $aiCalcId,
                        destinationPath: $tempDest,
                        correlationId: (string) $claimed->correlation_id,
                    );
                } finally {
                    if (file_exists($tempDest)) {
                        @unlink($tempDest);
                    }
                }
            } else {
                $reportResult = $activeAdapter->retrieveOriginalReport($sid, $session, $aiCalcId);
            }

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
                    'pacs_sid' => $sid,
                    'pacs_ai_calc_id' => $aiCalcId,
                    'checksum' => $reportResult->checksum,
                    'bytes' => $reportResult->bytes,
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
            DB::transaction(function () use ($claimed, $storedReport, $reportResult, $sid, $aiCalcId, $now, $audit): void {
                $jobRow = DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->first();
                if ($jobRow === null) {
                    return;
                }

                $findingsSummary = json_encode([
                    'pacs_sid' => $sid,
                    'pacs_ai_calc_id' => $aiCalcId,
                    'report_type' => 'image_report',
                    'original_checksum' => $storedReport->checksum,
                    'original_bytes' => $storedReport->bytes,
                ], JSON_THROW_ON_ERROR);

                DB::table('image_gateway_ai_reports')->updateOrInsert(
                    ['ai_job_id' => $this->aiJobId],
                    [
                        'id' => (string) Str::uuid(),
                        'study_id' => $claimed->study_id,
                        'capture_set_id' => $jobRow->capture_set_id,
                        'booking_id' => $jobRow->booking_id,
                        'member_id' => $jobRow->member_id,
                        'pacs_sid' => $sid,
                        'pacs_ai_calc_id' => $aiCalcId,
                        'original_object_key' => (string) $storedReport->key,
                        'original_checksum' => $storedReport->checksum,
                        'original_bytes' => $storedReport->bytes,
                        'original_filename' => $reportResult->filename,
                        'status' => 'original_ready',
                        'language' => 'id',
                        'clinical_disclaimer' => 'Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)',
                        'findings_summary' => $findingsSummary,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                DB::table('image_gateway_ai_jobs')->where('id', $this->aiJobId)->update([
                    'status' => AiJobStatus::REPORT_READY,
                    'pacs_sid' => $sid,
                    'pacs_ai_calc_id' => $aiCalcId,
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
                        'pacs_sid' => $sid,
                        'pacs_ai_calc_id' => $aiCalcId,
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

    private function extractAccessionNumber(string $dicomBytes): ?string
    {
        $pos = strpos($dicomBytes, "\x08\x00\x50\x00");
        if ($pos === false || $pos + 8 > strlen($dicomBytes)) {
            return null;
        }

        $vr = substr($dicomBytes, $pos + 4, 2);
        if ($vr === 'SH' || $vr === 'LO' || $vr === 'CS') {
            $len = unpack('v', substr($dicomBytes, $pos + 6, 2))[1] ?? 0;
            if ($len > 0 && $pos + 8 + $len <= strlen($dicomBytes)) {
                $val = trim(substr($dicomBytes, $pos + 8, $len), " \0");
                return $val !== '' ? $val : null;
            }
        }

        return null;
    }
}
