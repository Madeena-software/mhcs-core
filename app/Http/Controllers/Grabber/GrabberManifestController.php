<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grabber;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\GrabberManifestService;
use App\Modules\Operator\Domain\Models\GrabberClient;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class GrabberManifestController extends Controller
{
    private const int MAX_ATTEMPTS_PER_MINUTE = 60;

    private const int MAX_FAILED_ATTEMPTS_PER_MINUTE = 10;

    private const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly GrabberManifestService $manifestService,
    ) {}

    public function manifestByCode(Request $request, string $code): JsonResponse
    {
        /** @var GrabberClient $client */
        $client = $request->attributes->get('grabber_client');

        $siteId = $request->header('X-Site-ID') ?? $request->query('site_id');
        $shiftId = $request->header('X-Shift-ID') ?? $request->query('shift_id');

        return $this->handleLookup(
            $client,
            $code,
            is_string($siteId) ? $siteId : null,
            is_string($shiftId) ? $shiftId : null,
        );
    }

    public function lookupManifest(Request $request): JsonResponse
    {
        /** @var GrabberClient $client */
        $client = $request->attributes->get('grabber_client');

        $code = (string) ($request->input('locator_code') ?? $request->query('locator_code') ?? '');
        $siteId = $request->input('site_id') ?? $request->header('X-Site-ID') ?? $request->query('site_id');
        $shiftId = $request->input('shift_id') ?? $request->header('X-Shift-ID') ?? $request->query('shift_id');

        return $this->handleLookup(
            $client,
            $code,
            is_string($siteId) ? $siteId : null,
            is_string($shiftId) ? $shiftId : null,
        );
    }

    private function handleLookup(
        GrabberClient $client,
        string $code,
        ?string $siteId,
        ?string $shiftId,
    ): JsonResponse {
        $totalKey = 'grabber:manifest:total:'.$client->id;
        $failedKey = 'grabber:manifest:failed:'.$client->id;

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

        try {
            $manifest = $this->manifestService->resolve(
                $client,
                $code,
                $siteId,
                $shiftId,
            );

            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::clear($failedKey);

            return response()->json($manifest, Response::HTTP_OK);
        } catch (OperatorException $exception) {
            RateLimiter::hit($totalKey, self::DECAY_SECONDS);
            RateLimiter::hit($failedKey, self::DECAY_SECONDS);

            if ($exception->category === 'cross_site_denied') {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }

            // Generic anti-enumeration response for invalid code, wrong shift, expired, or completed session
            return response()->json(['message' => 'Radiography session not found.'], Response::HTTP_NOT_FOUND);
        }
    }
}
