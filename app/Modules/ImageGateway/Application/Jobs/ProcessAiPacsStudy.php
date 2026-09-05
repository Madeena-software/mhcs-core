<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Jobs;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function handle(Clock $clock, AuditStore $audit): void
    {
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

        // Bounded foundation for Slice 1: external PACS communication is out of scope.
        // The job remains safely claimed in 'processing' status.
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
    }

    private function queueLeaseSeconds(): int
    {
        return max(1, (int) config('queue.connections.database.retry_after', 300));
    }
}
