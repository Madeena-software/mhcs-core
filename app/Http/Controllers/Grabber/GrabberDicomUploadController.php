<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grabber;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\GrabberDicomIngestionService;
use App\Modules\Operator\Domain\Models\GrabberClient;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class GrabberDicomUploadController extends Controller
{
    private const int MAX_ATTEMPTS_PER_MINUTE = 60;

    private const int MAX_FAILED_ATTEMPTS_PER_MINUTE = 10;

    private const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly GrabberDicomIngestionService $ingestionService,
    ) {}

    public function upload(Request $request, string $code): JsonResponse
    {
        return $this->handleUpload($request, $code);
    }

    public function uploadByBody(Request $request): JsonResponse
    {
        $code = (string) (
            $request->input('locator_code')
            ?? $request->header('X-Locator-Code')
            ?? $request->header('X-Session-Code')
            ?? $request->query('locator_code')
            ?? ''
        );

        return $this->handleUpload($request, $code);
    }

    private function handleUpload(Request $request, string $code): JsonResponse
    {
        /** @var GrabberClient $client */
        $client = $request->attributes->get('grabber_client');

        $totalKey = 'grabber:dicom:total:'.$client->id;
        $failedKey = 'grabber:dicom:failed:'.$client->id;

        if (
            RateLimiter::tooManyAttempts($totalKey, self::MAX_ATTEMPTS_PER_MINUTE)
            || RateLimiter::tooManyAttempts($failedKey, self::MAX_FAILED_ATTEMPTS_PER_MINUTE)
        ) {
            $retryAfter = max(
                RateLimiter::availableIn($totalKey),
                RateLimiter::availableIn($failedKey),
                1,
            );

            return response()->json(
                ['message' => 'Too many attempts. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter],
            );
        }

        $code = trim($code);
        if ($code === '' || preg_match('/^[0-9]{4}$/', $code) !== 1) {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            return response()->json(
                ['message' => 'Radiography session not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $submissionId = (string) (
            $request->header('X-Submission-ID')
            ?? $request->header('X-Client-Submission-ID')
            ?? $request->header('Idempotency-Key')
            ?? $request->input('submission_id')
            ?? $request->input('idempotency_key')
            ?? ''
        );

        if (trim($submissionId) === '') {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            return response()->json(
                ['message' => 'Client submission identity is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $siteId = $request->header('X-Site-ID') ?? $request->input('site_id') ?? $request->query('site_id');
        $shiftId = $request->header('X-Shift-ID') ?? $request->input('shift_id') ?? $request->query('shift_id');
        $checksum = $request->header('X-Checksum-SHA256') ?? $request->header('X-SHA256') ?? $request->input('checksum') ?? $request->input('sha256');
        $patientMrn = $request->header('X-Patient-MRN') ?? $request->input('medical_record_number') ?? $request->input('patient_id');

        // Extract DICOM file bytes
        /** @var UploadedFile|null $file */
        $file = $request->file('file') ?? $request->file('dicom') ?? $request->file('dicom_file');

        if ($file instanceof UploadedFile) {
            if (! $file->isValid()) {
                RateLimiter::hit($totalKey, self::DECAY_SECONDS);
                RateLimiter::hit($failedKey, self::DECAY_SECONDS);

                return response()->json(
                    ['message' => 'Uploaded file is invalid or corrupted.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $realPath = $file->getRealPath();
            $dicomBytes = $realPath !== false ? file_get_contents($realPath) : false;
        } else {
            $content = $request->getContent();
            $dicomBytes = is_string($content) && $content !== '' ? $content : false;
        }

        if ($dicomBytes === false || $dicomBytes === '') {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            return response()->json(
                ['message' => 'No DICOM file or payload provided.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $result = $this->ingestionService->ingest(
                client: $client,
                locatorCode: $code,
                submissionId: $submissionId,
                dicomBytes: $dicomBytes,
                requestedSiteId: is_string($siteId) ? $siteId : null,
                requestedShiftId: is_string($shiftId) ? $shiftId : null,
                clientChecksum: is_string($checksum) ? $checksum : null,
                patientMrn: is_string($patientMrn) ? $patientMrn : null,
            );

            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::clear($failedKey);

            $httpStatus = ($result['replayed'] ?? false)
                ? Response::HTTP_OK
                : Response::HTTP_CREATED;

            return response()->json($result, $httpStatus);
        } catch (OperatorException $exception) {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            return match ($exception->category) {
                'cross_site_denied' => response()->json(
                    ['message' => 'Forbidden.'],
                    Response::HTTP_FORBIDDEN,
                ),
                'session_not_found' => response()->json(
                    ['message' => 'Radiography session not found.'],
                    Response::HTTP_NOT_FOUND,
                ),
                'session_conflict' => response()->json(
                    ['message' => $exception->getMessage()],
                    Response::HTTP_CONFLICT,
                ),
                default => response()->json(
                    ['message' => $exception->getMessage()],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                ),
            };
        } catch (IdempotencyConflict $exception) {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            return response()->json(
                ['message' => 'Idempotency conflict for submission ID.'],
                Response::HTTP_CONFLICT,
            );
        }
    }
}
