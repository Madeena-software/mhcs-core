<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Jobs;

use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\PermanentAcceptanceGate;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Modules\ImageGateway\Domain\Security\ValidationEvidence;
use App\Modules\ImageGateway\Infrastructure\ImageWorkerBoundary;
use App\Modules\ImageGateway\Infrastructure\MpipsClient;
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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

final class ProcessCaptureSet implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout;

    public function __construct(public readonly string $captureSetId)
    {
        $this->timeout = (int) config('mhcs.mpips.worker_timeout_seconds', 390);
    }

    public function handle(
        MpipsClient $client,
        PrivateObjectStore $objects,
        ManifestSigner $signer,
        Clock $clock,
    ): void {
        ImageWorkerBoundary::assertCaller(ImageWorkerBoundary::CALLER);
        $capture = DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->first();

        if ($capture === null || $capture->processing_status === 'completed' || $capture->processing_status === 'failed') {
            return;
        }

        $claim = DB::transaction(function () use ($clock): ?array {
            $row = DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->lockForUpdate()->first();
            if ($row === null || in_array($row->processing_status, ['completed', 'failed'], true)) {
                return null;
            }

            $leaseExpired = $row->processing_status !== 'processing'
                || $row->processing_lease_expires_at === null
                || new DateTimeImmutable((string) $row->processing_lease_expires_at) <= $clock->now();
            if ($row->processing_status === 'processing' && ! $leaseExpired) {
                return null;
            }

            $attempt = (int) $row->attempts + 1;
            if ($attempt > (int) config('mhcs.mpips.max_attempts', 5)) {
                $now = $clock->now();
                DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->update([
                    'processing_status' => 'failed',
                    'last_error_code' => 'retry_budget_exhausted',
                    'failed_at' => $now,
                    'processing_claim_id' => null,
                    'processing_lease_expires_at' => null,
                    'updated_at' => $now,
                ]);

                return null;
            }

            $claimId = (string) Str::uuid();
            $now = $clock->now();
            DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->update([
                'processing_status' => 'processing',
                'attempts' => $attempt,
                'processing_claim_id' => $claimId,
                'processing_lease_expires_at' => $now->modify('+'.$this->queueLeaseSeconds().' seconds'),
                'updated_at' => $now,
            ]);

            return ['attempt' => $attempt, 'claim_id' => $claimId];
        });

        if ($claim === null) {
            return;
        }
        $attempt = $claim['attempt'];
        $claimId = $claim['claim_id'];

        $workerContext = new AuthenticatedContext(
            actorId: LocalId::fromString($this->captureSetId),
            operationId: new CorrelationId($this->captureSetId),
            purpose: ImageGatewayCaptureService::CAPTURE_PURPOSE,
        );
        $objectsByType = DB::table('image_gateway_capture_objects')->where('capture_set_id', $this->captureSetId)->get()->keyBy('object_type');

        try {
            $radiograph = $this->readObject($objects, $objectsByType->get('radiograph'), $workerContext, $clock);
            $gain = $this->readObject($objects, $objectsByType->get('gain'), $workerContext, $clock);
            $manifest = $this->readObject($objects, $objectsByType->get('manifest'), $workerContext, $clock);
            $signature = $this->readObject($objects, $objectsByType->get('manifest_signature'), $workerContext, $clock);
            $this->assertStoredBytes($capture, $objectsByType->get('radiograph'), $radiograph);
            $this->assertStoredBytes($capture, $objectsByType->get('gain'), $gain);
            $this->assertStoredBytes($capture, $objectsByType->get('manifest'), $manifest, 'manifest_checksum', 'manifest_bytes');
            $this->assertStoredBytes($capture, $objectsByType->get('manifest_signature'), $signature, 'signature_checksum', 'signature_bytes');

            $signed = SignedManifest::fromArray(json_decode($signature, true, 512, JSON_THROW_ON_ERROR));
            $response = $client->convert($radiograph, $gain, $manifest);

            if ($this->retryable($response)) {
                $this->retryOrFail($capture, $attempt, $claimId, $response->status(), $this->responseDetail($response), $clock);

                return;
            }

            if (! $response->successful()) {
                $this->fail($this->captureSetId, $claimId, $response->status(), $this->responseDetail($response), $clock);

                return;
            }

            $result = $this->validateResponse($response);
            $identifiers = $this->uids($result['job_id']);
            (new PermanentAcceptanceGate($signer))->accept(
                $signed,
                new ValidationEvidence(
                    valid: true,
                    conversionJobId: $signed->manifest->conversionJobId,
                    radiographChecksum: hash('sha256', $radiograph),
                    gainChecksum: hash('sha256', $gain),
                    metadataChecksum: hash('sha256', $manifest),
                    manifestSignature: $signed->signature,
                    identifiers: $identifiers,
                ),
                $identifiers,
            );

            $this->storeStudy($capture, $result, $identifiers, $claimId, $objects, $workerContext, $clock);
        } catch (ConnectionException $exception) {
            $this->retryOrFail($capture, $attempt, $claimId, null, 'transport_failure', $clock);
        } catch (Throwable $exception) {
            $this->fail($this->captureSetId, $claimId, null, $this->failureCode($exception), $clock);
        }
    }

    private function queueLeaseSeconds(): int
    {
        return max(1, (int) config('queue.connections.database.retry_after', 420));
    }

    private function readObject(PrivateObjectStore $objects, ?object $row, AuthenticatedContext $context, Clock $clock): string
    {
        if ($row === null) {
            throw new \RuntimeException('capture_object_missing');
        }

        $object = new PrivateObject(
            OpaqueObjectKey::fromString((string) $row->object_key),
            (string) $row->checksum,
            (int) $row->bytes,
            'AES-256-GCM',
            new DateTimeImmutable((string) $row->created_at),
        );
        $grant = $objects->grant($object, $context, 'image-worker', ImageGatewayCaptureService::CAPTURE_PURPOSE, $clock->now()->modify('+300 seconds'));

        return $objects->get($grant, $context, 'image-worker', ImageGatewayCaptureService::CAPTURE_PURPOSE);
    }

    private function assertStoredBytes(object $capture, ?object $row, string $contents, ?string $checksumField = null, ?string $bytesField = null): void
    {
        if ($row === null || ! hash_equals((string) $row->checksum, hash('sha256', $contents)) || (int) $row->bytes !== strlen($contents)) {
            throw new \RuntimeException('capture_object_integrity_failure');
        }
        if ($checksumField !== null && ($capture->{$checksumField} ?? null) !== null && ! hash_equals((string) $capture->{$checksumField}, hash('sha256', $contents))) {
            throw new \RuntimeException('capture_manifest_integrity_failure');
        }
        if ($bytesField !== null && ($capture->{$bytesField} ?? null) !== null && (int) $capture->{$bytesField} !== strlen($contents)) {
            throw new \RuntimeException('capture_manifest_size_failure');
        }
    }

    /** @return array{job_id: string, correlation_id: string, bytes: string, filename: string} */
    private function validateResponse(Response $response): array
    {
        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $bytes = $response->body();
        $jobId = (string) $response->header('X-Conversion-Job-ID');
        $correlationId = (string) $response->header('X-Correlation-ID');

        if ($contentType !== 'application/dicom' || $bytes === '' || strlen($bytes) < 132 || substr($bytes, 128, 4) !== 'DICM' || ! Str::isUuid($jobId) || ! Str::isUuid($correlationId)) {
            throw new \RuntimeException('invalid_transport_response');
        }

        return [
            'job_id' => $jobId,
            'correlation_id' => $correlationId,
            'bytes' => $bytes,
            'filename' => 'capture-'.str_replace('-', '', $jobId).'.dcm',
        ];
    }

    /** @return array{study: string, series: string, sop: string} */
    private function uids(string $jobId): array
    {
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        return [
            'study' => '2.25.'.Uuid::uuid5($namespace, 'mpips:study:'.$jobId)->getInteger()->toString(),
            'series' => '2.25.'.Uuid::uuid5($namespace, 'mpips:series:'.$jobId)->getInteger()->toString(),
            'sop' => '2.25.'.Uuid::uuid5($namespace, 'mpips:sop:'.$jobId)->getInteger()->toString(),
        ];
    }

    /** @param array{job_id: string, correlation_id: string, bytes: string, filename: string} $result */
    /** @param array{study: string, series: string, sop: string} $identifiers */
    private function storeStudy(object $capture, array $result, array $identifiers, string $claimId, PrivateObjectStore $objects, AuthenticatedContext $context, Clock $clock): void
    {
        $studyObject = $objects->put($result['bytes'], $context, ImageGatewayCaptureService::CAPTURE_PURPOSE);

        $inserted = false;
        try {
            DB::transaction(function () use ($result, $identifiers, $claimId, $studyObject, $clock): void {
                $row = DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->lockForUpdate()->first();
                if ($row === null || $row->processing_status === 'completed' || $row->processing_claim_id !== $claimId) {
                    return;
                }
                if (DB::table('image_gateway_studies')->where('capture_set_id', $this->captureSetId)->exists()) {
                    DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->update([
                        'processing_status' => 'completed',
                        'processing_claim_id' => null,
                        'processing_lease_expires_at' => null,
                        'completed_at' => $clock->now(),
                        'updated_at' => $clock->now(),
                    ]);

                    return;
                }

                $now = $clock->now();
                DB::table('image_gateway_studies')->insert([
                    'id' => (string) Str::uuid(),
                    'capture_set_id' => $this->captureSetId,
                    'object_key' => (string) $studyObject->key,
                    'checksum' => $studyObject->checksum,
                    'bytes' => $studyObject->bytes,
                    'format' => 'application/dicom',
                    'filename' => $result['filename'],
                    'study_instance_uid' => $identifiers['study'],
                    'series_instance_uid' => $identifiers['series'],
                    'sop_instance_uid' => $identifiers['sop'],
                    'transfer_syntax' => null,
                    'window_center' => null,
                    'window_width' => null,
                    'rows' => null,
                    'columns' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('image_gateway_capture_sets')->where('id', $this->captureSetId)->update([
                    'processing_status' => 'completed',
                    'processing_claim_id' => null,
                    'processing_lease_expires_at' => null,
                    'conversion_job_id' => $result['job_id'],
                    'correlation_id' => $result['correlation_id'],
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);
            });
            $inserted = DB::table('image_gateway_studies')->where('capture_set_id', $this->captureSetId)->where('object_key', (string) $studyObject->key)->exists();
        } catch (Throwable $exception) {
            $objects->delete($studyObject);
            throw $exception;
        }
        if (! $inserted) {
            $objects->delete($studyObject);
        }
    }

    private function retryable(Response $response): bool
    {
        return in_array($response->status(), [429, 502, 503, 504], true)
            || ($response->status() === 409 && $this->responseDetail($response) === 'IDEMPOTENCY_IN_PROGRESS');
    }

    private function retryOrFail(object $capture, int $attempt, string $claimId, ?int $status, string $code, Clock $clock): void
    {
        if ($attempt >= (int) config('mhcs.mpips.max_attempts', 5)) {
            $this->fail($this->captureSetId, $claimId, $status, 'retry_budget_exhausted', $clock);

            return;
        }

        $updated = DB::table('image_gateway_capture_sets')
            ->where('id', $this->captureSetId)
            ->where('processing_status', 'processing')
            ->where('processing_claim_id', $claimId)
            ->update([
                'processing_status' => 'retrying',
                'last_error_code' => $code,
                'last_response_status' => $status,
                'processing_claim_id' => null,
                'processing_lease_expires_at' => null,
                'updated_at' => $clock->now(),
            ]);
        if ($updated !== 1) {
            return;
        }
        $cap = min((int) config('mhcs.mpips.backoff_cap_seconds', 30), (int) config('mhcs.mpips.backoff_base_seconds', 2) ** $attempt);
        if ($this->job !== null) {
            $this->release(random_int(0, max(0, $cap)));
        }
    }

    private function fail(string $captureId, string $claimId, ?int $status, string $code, Clock $clock): void
    {
        DB::table('image_gateway_capture_sets')
            ->where('id', $captureId)
            ->where('processing_status', 'processing')
            ->where('processing_claim_id', $claimId)
            ->update([
                'processing_status' => 'failed',
                'last_error_code' => $code,
                'last_response_status' => $status,
                'failed_at' => $clock->now(),
                'processing_claim_id' => null,
                'processing_lease_expires_at' => null,
                'updated_at' => $clock->now(),
            ]);
    }

    private function responseDetail(Response $response): string
    {
        $detail = $response->json('detail');

        return is_string($detail) && in_array($detail, ['IDEMPOTENCY_IN_PROGRESS', 'IDEMPOTENCY_CONFLICT'], true) ? $detail : 'remote_failure';
    }

    private function failureCode(Throwable $exception): string
    {
        return match (true) {
            str_contains($exception->getMessage(), 'invalid_transport_response') => 'transport_invalid',
            str_contains($exception->getMessage(), 'capture_object') => 'object_integrity_failure',
            default => 'processing_failure',
        };
    }
}
