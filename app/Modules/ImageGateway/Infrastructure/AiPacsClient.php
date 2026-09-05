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
use GuzzleHttp\Psr7\MultipartStream;
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

    private int $connectTimeout;

    private int $uploadTimeout;

    private int $pollTimeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $username = null,
        ?string $password = null,
        ?int $timeout = null,
        ?int $connectTimeout = null,
        ?int $uploadTimeout = null,
        ?int $pollTimeout = null,
    ) {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('services.ai_pacs.base_url', 'http://124.225.183.175:8361')), '/');
        $this->username = (string) ($username ?? config('services.ai_pacs.username', ''));
        $this->password = (string) ($password ?? config('services.ai_pacs.password', ''));
        $this->timeout = (int) ($timeout ?? config('services.ai_pacs.timeout_seconds', 30));
        $this->connectTimeout = (int) ($connectTimeout ?? config('services.ai_pacs.connect_timeout_seconds', 10));
        $this->uploadTimeout = (int) ($uploadTimeout ?? config('services.ai_pacs.upload_timeout_seconds', 600));
        $this->pollTimeout = (int) ($pollTimeout ?? config('services.ai_pacs.poll_timeout_seconds', 30));
    }

    public function authenticate(): AiPacsSession
    {
        $this->assertConfigured();

        try {
            $response = $this->request($this->timeout)
                ->connectTimeout($this->connectTimeout)
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

    public function uploadStudy(
        mixed $dicomPayload,
        string $filename,
        ?AiPacsSession $session = null,
        ?string $accessionNumber = null,
    ): AiPacsUploadResult {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        $multipart = new MultipartStream([
            [
                'name' => 'files',
                'contents' => $dicomPayload,
                'filename' => $filename,
                'headers' => [
                    'Content-Type' => 'application/dicom',
                ],
            ],
        ]);

        $contentLength = $multipart->getSize();

        try {
            $request = $this->authorizedRequest($activeSession, $this->uploadTimeout)
                ->connectTimeout($this->connectTimeout)
                ->withBody($multipart, 'multipart/form-data; boundary='.$multipart->getBoundary())
                ->withHeaders(array_filter([
                    'Content-Length' => $contentLength !== null ? (string) $contentLength : null,
                ]));

            $response = $request->post("{$this->baseUrl}/api/v1/study/upload");
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

        $payload = $data['data'] ?? $data;
        $failNum = isset($payload['failNum']) ? (int) $payload['failNum'] : 0;
        if ($failNum > 0) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                'AI PACS rejected study upload with fail count.',
            );
        }

        $studyId = $payload['sid']
            ?? $payload['studyId']
            ?? $payload['id']
            ?? null;

        $aiCalcId = isset($payload['aiCalcId']) ? (int) $payload['aiCalcId'] : null;

        if ($studyId === null && $accessionNumber !== null) {
            $reconciled = $this->findStudyByAccession($accessionNumber, $activeSession);
            if ($reconciled !== null) {
                return $reconciled;
            }
        }

        if ($studyId === null) {
            $successNum = isset($payload['successNum']) ? (int) $payload['successNum'] : 0;
            if ($successNum > 0) {
                $latest = $this->queryLatestStudy($activeSession);
                if ($latest !== null) {
                    return $latest;
                }
            }

            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UPLOAD_FAILED,
                'AI PACS upload response was missing the study identifier.',
            );
        }

        return new AiPacsUploadResult(
            studyIdentifier: $studyId,
            aiCalcId: $aiCalcId,
            rawStatus: (string) ($payload['status'] ?? 'uploaded'),
            metadata: is_array($payload) ? $payload : [],
        );
    }

    public function findStudyByAccession(string $accessionNumber, ?AiPacsSession $session = null): ?AiPacsUploadResult
    {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $response = $this->authorizedRequest($activeSession, $this->pollTimeout)
                ->connectTimeout($this->connectTimeout)
                ->get("{$this->baseUrl}/api/v1/studies", [
                    'accessionNumber' => $accessionNumber,
                ]);
        } catch (ConnectionException $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_TIMEOUT,
                'Connection to AI PACS timed out during study lookup.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ImageGatewayException(
                AiErrorCode::AI_PACS_UNAVAILABLE,
                'AI PACS study lookup failed due to transport error.',
                $exception,
            );
        }

        $this->assertResponseStatus($response, 'study lookup');

        $data = $response->json();
        $list = $data['data']['list'] ?? [];
        if (! is_array($list) || $list === []) {
            return null;
        }

        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemAccession = (string) ($item['accessionNumber'] ?? '');
            if ($itemAccession === $accessionNumber) {
                $studyId = $item['studyId'] ?? $item['sid'] ?? null;
                if ($studyId === null) {
                    continue;
                }
                $aiCalcId = isset($item['aiCalcId']) ? (int) $item['aiCalcId'] : null;

                return new AiPacsUploadResult(
                    studyIdentifier: $studyId,
                    aiCalcId: $aiCalcId,
                    rawStatus: (string) ($item['aiCalcStatus'] ?? 'registered'),
                    metadata: $item,
                );
            }
        }

        return null;
    }

    public function pollCalculationStatus(string|int $studyIdentifier, ?AiPacsSession $session = null): AiPacsCalculationStatus
    {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $response = $this->authorizedRequest($activeSession, $this->pollTimeout)
                ->connectTimeout($this->connectTimeout)
                ->get("{$this->baseUrl}/api/v1/study/ai/calc", [
                    'sid' => (string) $studyIdentifier,
                ]);

            if ($response->status() === 405 || $response->status() === 404) {
                $response = $this->authorizedRequest($activeSession, $this->pollTimeout)
                    ->connectTimeout($this->connectTimeout)
                    ->get("{$this->baseUrl}/api/v1/studies", [
                        'studyId' => (string) $studyIdentifier,
                    ]);
            }
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
        if (isset($payload['list']) && is_array($payload['list'])) {
            $payload = $payload['list'][0] ?? [];
        }

        $statusStr = strtolower(trim((string) ($payload['status'] ?? $payload['state'] ?? $payload['aiCalcStatus'] ?? '')));
        $aiCalcId = isset($payload['aiCalcId']) ? (int) $payload['aiCalcId'] : (isset($payload['id']) ? (int) $payload['id'] : null);
        $progress = isset($payload['progress']) ? (int) $payload['progress'] : null;

        $isSuccess = in_array($statusStr, ['success', 'completed', 'finished', 'done', '已完成'], true)
            || ($aiCalcId !== null && $progress === 100);

        if ($isSuccess) {
            return AiPacsCalculationStatus::completed(
                aiCalcId: $aiCalcId ?? (int) $studyIdentifier,
                metadata: is_array($payload) ? $payload : [],
            );
        }

        if (in_array($statusStr, ['failed', 'error', '计算失败'], true)) {
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

    public function retrieveOriginalReport(
        string|int $studyIdentifier,
        ?AiPacsSession $session = null,
        ?int $aiCalcId = null,
    ): AiPacsReportResult {
        $this->assertConfigured();
        $activeSession = $session ?? $this->authenticate();

        try {
            $response = $this->authorizedRequest($activeSession, $this->pollTimeout)
                ->connectTimeout($this->connectTimeout)
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

    private function queryLatestStudy(AiPacsSession $session): ?AiPacsUploadResult
    {
        try {
            $response = $this->authorizedRequest($session, $this->pollTimeout)
                ->connectTimeout($this->connectTimeout)
                ->get("{$this->baseUrl}/api/v1/studies", [
                    'page' => 1,
                    'pageSize' => 1,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $list = $data['data']['list'] ?? [];
        if (! is_array($list) || $list === []) {
            return null;
        }

        $first = $list[0];
        if (! is_array($first)) {
            return null;
        }

        $studyId = $first['studyId'] ?? $first['sid'] ?? null;
        if ($studyId === null) {
            return null;
        }

        return new AiPacsUploadResult(
            studyIdentifier: $studyId,
            aiCalcId: isset($first['aiCalcId']) ? (int) $first['aiCalcId'] : null,
            rawStatus: (string) ($first['aiCalcStatus'] ?? 'registered'),
            metadata: $first,
        );
    }

    private function request(?int $timeout = null): PendingRequest
    {
        return Http::timeout($timeout ?? $this->timeout)
            ->acceptJson();
    }

    private function authorizedRequest(AiPacsSession $session, ?int $timeout = null): PendingRequest
    {
        $req = $this->request($timeout);

        if ($session->token !== null) {
            // Live vendor requirement: raw token header, MUST NOT prepend Bearer
            $req = $req->withHeaders([
                'Authorization' => $session->token,
            ]);
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
