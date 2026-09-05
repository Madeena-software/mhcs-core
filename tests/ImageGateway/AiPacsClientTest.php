<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsSession;
use App\Modules\ImageGateway\Infrastructure\AiPacsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AiPacsClientTest extends TestCase
{
    private string $baseUrl = 'http://124.225.183.175:8361';

    private AiPacsClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai_pacs.base_url' => $this->baseUrl,
            'services.ai_pacs.username' => 'test_user',
            'services.ai_pacs.password' => 'test_password',
            'services.ai_pacs.timeout_seconds' => 5,
        ]);
        $this->client = new AiPacsClient(
            baseUrl: $this->baseUrl,
            username: 'test_user',
            password: 'test_password',
            timeout: 5,
        );
    }

    public function test_authenticate_success(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'token' => 'mocked-jwt-token-12345',
                    'username' => 'test_user',
                ],
            ], 200, ['Set-Cookie' => 'session_id=mocked_cookie_abc; Path=/']),
        ]);

        $session = $this->client->authenticate();

        $this->assertInstanceOf(AiPacsSession::class, $session);
        $this->assertSame('mocked-jwt-token-12345', $session->token);
        $this->assertTrue($session->isAuthenticated());
        $this->assertArrayHasKey('session_id', $session->cookies);
        $this->assertSame('mocked_cookie_abc', $session->cookies['session_id']);
    }

    public function test_authenticate_invalid_credentials_failure(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 1002,
                'message' => '账号不存在',
            ], 200),
        ]);

        try {
            $this->client->authenticate();
            $this->fail('Expected ImageGatewayException on invalid credentials');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_AUTH_FAILED, $exception->category);
            $this->assertStringNotContainsString('test_password', $exception->getMessage());
        }
    }

    public function test_authenticate_timeout(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => fn () => throw new ConnectionException('Connection timed out after 5000ms'),
        ]);

        try {
            $this->client->authenticate();
            $this->fail('Expected ImageGatewayException on timeout');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_TIMEOUT, $exception->category);
        }
    }

    public function test_authenticate_rate_limited(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response(['message' => 'Too Many Requests'], 429),
        ]);

        try {
            $this->client->authenticate();
            $this->fail('Expected ImageGatewayException on 429');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_RATE_LIMITED, $exception->category);
        }
    }

    public function test_authenticate_server_unavailable(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response(['message' => 'Bad Gateway'], 502),
        ]);

        try {
            $this->client->authenticate();
            $this->fail('Expected ImageGatewayException on 502');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_UNAVAILABLE, $exception->category);
        }
    }

    public function test_raw_authorization_header_without_bearer(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/studies*" => Http::response([
                'code' => 0,
                'data' => ['list' => []],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'raw-secret-jwt-token-999');
        $this->client->findStudyByAccession('ACC-1234', $session);

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            return $authHeader === 'raw-secret-jwt-token-999'
                && ! str_starts_with($authHeader, 'Bearer ');
        });
    }

    public function test_upload_study_targets_correct_route(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'message' => '请求成功',
                'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 121, 'aiCalcId' => 124],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $this->client->uploadStudy('fake-dicom-bytes', 'bypass.dcm', $session);

        Http::assertSent(function ($request) {
            return $request->url() === "{$this->baseUrl}/api/v1/study/upload";
        });
        Http::assertNotSent(function ($request) {
            return $request->url() === "{$this->baseUrl}/api/v1/studies";
        });
    }

    public function test_upload_study_uses_multipart_field_files(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 121, 'aiCalcId' => 124],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $this->client->uploadStudy('DICOM_PAYLOAD_123', 'bypass.dcm', $session);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();
            return str_contains($body, 'name="files"')
                && str_contains($body, 'filename="bypass.dcm"')
                && str_contains($body, 'Content-Type: application/dicom')
                && ! str_contains($body, 'name="file";');
        });
    }

    public function test_upload_study_has_fixed_content_length(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 121, 'aiCalcId' => 124],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $this->client->uploadStudy('DICOM_PAYLOAD_123456789', 'bypass.dcm', $session);

        Http::assertSent(function ($request) {
            $hasLength = $request->hasHeader('Content-Length');
            $length = (int) ($request->header('Content-Length')[0] ?? 0);
            $hasChunked = in_array('chunked', $request->header('Transfer-Encoding') ?? [], true);
            return $hasLength && $length > 0 && ! $hasChunked;
        });
    }

    public function test_upload_study_success_with_success_num_one(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'message' => '请求成功',
                'data' => [
                    'failNum' => 0,
                    'successNum' => 1,
                    'sid' => 121,
                    'aiCalcId' => 124,
                ],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $result = $this->client->uploadStudy('fake-dicom-bytes', 'bypass.dcm', $session);

        $this->assertSame(121, $result->studyIdentifier);
        $this->assertSame(124, $result->aiCalcId);
        $this->assertSame('uploaded', $result->rawStatus);
    }

    public function test_upload_study_rejection_when_fail_num_greater_than_zero(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'message' => '部分失败',
                'data' => [
                    'failNum' => 1,
                    'successNum' => 0,
                ],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');

        try {
            $this->client->uploadStudy('invalid-bytes', 'test.dcm', $session);
            $this->fail('Expected ImageGatewayException on failNum > 0');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_UPLOAD_FAILED, $exception->category);
        }
    }

    public function test_upload_study_timeout_is_retryable(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => fn () => throw new ConnectionException('Upload connection timed out'),
        ]);

        $session = new AiPacsSession(token: 'mock-token');

        try {
            $this->client->uploadStudy('bytes', 'test.dcm', $session);
            $this->fail('Expected ImageGatewayException on upload timeout');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_TIMEOUT, $exception->category);
        }
    }

    public function test_reconciliation_after_ambiguous_timeout(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/studies*" => Http::response([
                'code' => 0,
                'data' => [
                    'total' => 1,
                    'list' => [
                        [
                            'studyId' => 121,
                            'aiCalcId' => 124,
                            'aiCalcStatus' => '已完成',
                            'accessionNumber' => 'ACC-RECON-001',
                            'patientName' => 'Reconciled Patient',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $result = $this->client->findStudyByAccession('ACC-RECON-001', $session);

        $this->assertNotNull($result);
        $this->assertSame(121, $result->studyIdentifier);
        $this->assertSame(124, $result->aiCalcId);
        $this->assertSame('已完成', $result->rawStatus);
    }

    public function test_no_duplicate_upload_if_study_already_exists(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/studies*" => Http::response([
                'code' => 0,
                'data' => [
                    'total' => 1,
                    'list' => [
                        [
                            'studyId' => 121,
                            'aiCalcId' => 124,
                            'aiCalcStatus' => '已完成',
                            'accessionNumber' => 'ACC-EXISTING-999',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $existing = $this->client->findStudyByAccession('ACC-EXISTING-999', $session);

        $this->assertNotNull($existing);
        $this->assertSame(121, $existing->studyIdentifier);

        // Assert upload route was never called
        Http::assertNotSent(function ($request) {
            return $request->url() === "{$this->baseUrl}/api/v1/study/upload";
        });
    }

    public function test_sanitized_exceptions_do_not_leak_credentials_tokens_or_bytes(): void
    {
        $sensitivePassword = 'super-secret-password-xyz';
        $sensitiveToken = 'confidential-raw-bearer-token';
        $sensitiveDicom = 'BINARY_DICOM_DATA_\x00\x01\x02';

        Http::fake([
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 500,
                'message' => "Internal failure with {$sensitivePassword} and {$sensitiveToken}",
            ], 500),
        ]);

        $client = new AiPacsClient(
            baseUrl: $this->baseUrl,
            username: 'test_user',
            password: $sensitivePassword,
            timeout: 5,
        );

        $session = new AiPacsSession(token: $sensitiveToken);

        try {
            $client->uploadStudy($sensitiveDicom, 'bypass.dcm', $session);
            $this->fail('Expected ImageGatewayException');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_UNAVAILABLE, $exception->category);
            $this->assertStringNotContainsString($sensitivePassword, $exception->getMessage());
            $this->assertStringNotContainsString($sensitiveToken, $exception->getMessage());
            $this->assertStringNotContainsString($sensitiveDicom, $exception->getMessage());
        }
    }

    public function test_poll_calculation_status_pending_and_completed_and_failed(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::sequence()
                ->push([
                    'code' => 0,
                    'data' => [
                        'status' => 'calculating',
                        'progress' => 45,
                        'aiCalcId' => 55,
                    ],
                ], 200)
                ->push([
                    'code' => 0,
                    'data' => [
                        'status' => 'success',
                        'progress' => 100,
                        'aiCalcId' => 55,
                    ],
                ], 200)
                ->push([
                    'code' => 0,
                    'data' => [
                        'status' => 'failed',
                        'error' => 'algorithm failed to converge',
                    ],
                ], 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');

        // 1. Pending
        $statusPending = $this->client->pollCalculationStatus(12345, $session);
        $this->assertTrue($statusPending->isPending);
        $this->assertFalse($statusPending->isCompleted);
        $this->assertSame(45, $statusPending->progressPercent);

        // 2. Completed
        $statusCompleted = $this->client->pollCalculationStatus(12345, $session);
        $this->assertTrue($statusCompleted->isCompleted);
        $this->assertFalse($statusCompleted->isPending);
        $this->assertSame(55, $statusCompleted->aiCalcId);

        // 3. Failed
        $statusFailed = $this->client->pollCalculationStatus(12345, $session);
        $this->assertTrue($statusFailed->isFailed);
        $this->assertFalse($statusFailed->isCompleted);
    }

    public function test_retrieve_original_report_success(): void
    {
        $validPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\nxref\n0 1\n0000000000 65535 f\ntrailer<</Size 1>>\nstartxref\n50\n%%EOF";
        // Pad to > 100 bytes
        $validPdf = str_pad($validPdf, 256, "\n");
        $validPdf .= "%%EOF";

        Http::fake([
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response($validPdf, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $session = new AiPacsSession(token: 'mock-token');
        $report = $this->client->retrieveOriginalReport(12345, $session);

        $this->assertSame(strlen($validPdf), $report->bytes);
        $this->assertSame(hash('sha256', $validPdf), $report->checksum);
        $this->assertSame('original-ai-report-12345.pdf', $report->filename);
    }

    public function test_retrieve_original_report_invalid_pdf_bytes(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response('NOT-A-PDF-CONTENT', 200),
        ]);

        $session = new AiPacsSession(token: 'mock-token');

        try {
            $this->client->retrieveOriginalReport(12345, $session);
            $this->fail('Expected ImageGatewayException on invalid PDF');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $exception->category);
        }
    }

    public function test_no_secret_logging_in_session_serialization(): void
    {
        $session = new AiPacsSession(
            token: 'super-secret-bearer-token-12345',
            cookies: ['session_key' => 'secret-cookie-value'],
        );

        $serialized = json_encode($session, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('super-secret-bearer-token-12345', $serialized);
        $this->assertStringNotContainsString('secret-cookie-value', $serialized);
        $this->assertStringContainsString('has_token', $serialized);
        $this->assertStringContainsString('cookie_names', $serialized);
    }
}
