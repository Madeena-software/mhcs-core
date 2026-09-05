<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsCalculationStatus;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsReportResult;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsSession;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsUploadResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AiPacsClient implements AiPacsAdapterContract
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $username = null,
        ?string $password = null,
        ?int $timeout = null,
    ) {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('services.ai_pacs.base_url', 'http://124.225.183.175:8361')), '/');
        $this->username = (string) ($username ?? config('services.ai_pacs.username', ''));
        $this->password = (string) ($password ?? config('services.ai_pacs.password', ''));
        $this->timeout = (int) ($timeout ?? config('services.ai_pacs.timeout_seconds', 30));
    }

    public function authenticate(): AiPacsSession
    {
        $this->assertConfigured();

        try {
            $response = $this->request()
                ->post("{$this->baseUrl}/api/v1/login", [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);
        } catch (ConnectionException $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_TIMEOUT,
                'Connection to AI PACS timed out during authentication.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UNAVAILABLE,
                'AI PACS authentication request failed due to transport error.',
                $exception,
            );
        }

        $this->assertResponseStatus($response, 'authentication');

        $data = $response->json();
        $code = $data['code'] ?? null;

        // Yizhun returns code 0 or 200 on success, and specific error codes (e.g. 1001, 1002, 1003) on failure
        if ($code !== null && ! in_array($code, [0, 200], true)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_AUTH_FAILED,
                'AI PACS authentication rejected credentials.',
            );
        }

        $token = $data['data']['token']
            ?? $data['token']
            ?? $response->header('Authorization')
            ?? $response->header('X-Token');

        if (is_string($token)) {
            $token = preg_replace('/\ABearer\s+/i', '', trim($token)) ?? trim($token);
        }

        $cookies = [];
        foreach ($response->cookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        if ((! is_string($token) || trim($token) === '') && $cookies === []) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_AUTH_FAILED,
                'AI PACS response did not yield a valid authentication token or session cookie.',
            );
        }

        return new AiPacsSession(
            token: is_string($token) && trim($token) !== '' ? trim($token) : null,
            cookies: $cookies,
        );
    }

    public function uploadStudy(mixed $dicomPayload, string $filename, ?AiPacsSession $session = null): AiPacsUploadResult
    {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $request = $this->authorizedRequest($activeSession);

            if (is_resource($dicomPayload)) {
                $request->attach('file', $dicomPayload, $filename);
            } else {
                $request->attach('file', (string) $dicomPayload, $filename);
            }

            $response = $request->post("{$this->baseUrl}/api/v1/studies");
        } catch (ConnectionException $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_TIMEOUT,
                'Connection to AI PACS timed out during study upload.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                'AI PACS study upload failed due to network or transport error.',
                $exception,
            );
        }

        $this->assertResponseStatus($response, 'upload');

        $data = $response->json();
        $code = $data['code'] ?? null;
        if ($code !== null && ! in_array($code, [0, 200], true)) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                'AI PACS rejected study upload.',
            );
        }

        $studyId = $data['data']['sid']
            ?? $data['data']['studyId']
            ?? $data['data']['id']
            ?? $data['sid']
            ?? $data['studyId']
            ?? null;

        if ($studyId === null) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                'AI PACS upload response was missing the study identifier.',
            );
        }

        $aiCalcId = isset($data['data']['aiCalcId']) ? (int) $data['data']['aiCalcId'] : null;

        return new AiPacsUploadResult(
            studyIdentifier: $studyId,
            aiCalcId: $aiCalcId,
            rawStatus: (string) ($data['data']['status'] ?? 'uploaded'),
            metadata: is_array($data['data'] ?? null) ? $data['data'] : [],
        );
    }

    public function pollCalculationStatus(string|int $studyIdentifier, ?AiPacsSession $session = null): AiPacsCalculationStatus
    {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $response = $this->authorizedRequest($activeSession)
                ->get("{$this->baseUrl}/api/v1/study/ai/calc", [
                    'sid' => (string) $studyIdentifier,
                ]);
        } catch (ConnectionException $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_TIMEOUT,
                'Connection to AI PACS timed out during calculation status check.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UNAVAILABLE,
                'AI PACS calculation polling failed due to transport error.',
                $exception,
            );
        }

        $this->assertResponseStatus($response, 'calculation status polling');

        $data = $response->json();
        $code = $data['code'] ?? null;
        if ($code !== null && ! in_array($code, [0, 200], true)) {
            return AiPacsCalculationStatus::failed(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                is_array($data) ? $data : [],
            );
        }

        $payload = $data['data'] ?? $data;
        $statusStr = strtolower(trim((string) ($payload['status'] ?? $payload['state'] ?? '')));
        $aiCalcId = isset($payload['aiCalcId']) ? (int) $payload['aiCalcId'] : (isset($payload['id']) ? (int) $payload['id'] : null);
        $progress = isset($payload['progress']) ? (int) $payload['progress'] : null;

        if (in_array($statusStr, ['success', 'completed', 'finished', 'done'], true) || ($aiCalcId !== null && $progress === 100)) {
            return AiPacsCalculationStatus::completed(
                aiCalcId: $aiCalcId ?? (int) $studyIdentifier,
                metadata: is_array($payload) ? $payload : [],
            );
        }

        if (in_array($statusStr, ['failed', 'error'], true)) {
            return AiPacsCalculationStatus::failed(
                errorCode: AiErrorCode::AI_PACS_UPLOAD_FAILED,
                metadata: is_array($payload) ? $payload : [],
            );
        }

        return AiPacsCalculationStatus::pending(
            progress: $progress,
            aiCalcId: $aiCalcId,
        );
    }

    public function retrieveOriginalReport(string|int $studyIdentifier, ?AiPacsSession $session = null): AiPacsReportResult
    {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $response = $this->authorizedRequest($activeSession)
                ->get("{$this->baseUrl}/api/v1/view-report/download", [
                    'sid' => (string) $studyIdentifier,
                ]);
        } catch (ConnectionException $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_TIMEOUT,
                'Connection to AI PACS timed out during original report retrieval.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED,
                'Failed to retrieve original AI report from AI PACS due to transport error.',
                $exception,
            );
        }

        $this->assertResponseStatus($response, 'report download');

        $body = $response->body();
        $filename = "original-ai-report-{$studyIdentifier}.pdf";

        return new AiPacsReportResult(
            pdfBytes: $body,
            filename: $filename,
        );
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson();
    }

    private function authorizedRequest(AiPacsSession $session): PendingRequest
    {
        $req = $this->request();

        if ($session->token !== null) {
            $req = $req->withToken($session->token);
        }

        if ($session->cookies !== []) {
            $req = $req->withCookies($session->cookies, parse_url($this->baseUrl, PHP_URL_HOST) ?? 'localhost');
        }

        return $req;
    }

    private function assertConfigured(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                'AI PACS base URL is not configured.',
            );
        }

        if (trim($this->username) === '' || trim($this->password) === '') {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_AUTH_FAILED,
                'AI PACS authentication credentials are not configured.',
            );
        }
    }

    private function assertResponseStatus(Response $response, string $operation): void
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_AUTH_FAILED,
                "AI PACS {$operation} failed: unauthorized.",
            );
        }

        if ($response->status() === 429) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_RATE_LIMITED,
                "AI PACS {$operation} failed: rate limit exceeded.",
            );
        }

        if ($response->serverError()) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UNAVAILABLE,
                "AI PACS {$operation} failed: remote server error ({$response->status()}).",
            );
        }

        if (! $response->successful()) {
            throw new ImageGatewayException(
                AiErrorCode::PROCESSING_ERROR,
                "AI PACS {$operation} returned unexpected HTTP status {$response->status()}.",
            );
        }
    }
}
