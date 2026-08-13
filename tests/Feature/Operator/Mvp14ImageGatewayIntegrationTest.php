<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp14ImageGatewayIntegrationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.private_object_disk' => 'local',
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.manifest_key' => str_repeat('m', 32),
            'mhcs.security.manifest_key_id' => 'test-key',
            'mhcs.mpips.base_url' => 'http://127.0.0.1:8014',
            'mhcs.mpips.api_key' => 'test-api-key',
        ]);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_capture_starts_source_persistence_and_mpips_together(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $submissionId = (string) Str::uuid();
        Http::fake($this->validMpipsResponse());

        $response = $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ]);
        $this->assertSame(302, $response->status(), $response->headers->get('Location') ?? '');
        $this->assertSame(route('operator.study.results'), $response->headers->get('Location'));

        $capture = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $this->assertNotNull($capture);
        $this->assertSame('completed', $capture->processing_status);
        $this->assertSame('success', $capture->radiograph_status);
        $this->assertSame('success', $capture->gain_status);
        $this->assertSame('success', $capture->mpips_status);
        $this->assertSame('awaiting_ai', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        $this->assertSame(4, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->count());
        Queue::assertNothingPushed();
        Http::assertSentCount(1);
        $radiograph = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->first();
        $this->assertSame(file_get_contents(base_path('resources/fixtures/image-gateway/synthetic-radiograph-01.npz')), Storage::disk('local')->get((string) $radiograph->object_key));
        $this->assertArrayNotHasKey('encryption', json_decode((string) Storage::disk('local')->get((string) $radiograph->object_key.'.meta.json'), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_job_submits_the_minimal_multipart_contract_and_stores_a_valid_dicom(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake(function (Request $request) use ($fixture) {
            $this->assertSame(['test-api-key'], $request->header('X-MPIPS-API-Key'));
            $this->assertStringContainsString('/v1/radiographs/dicom', $request->url());
            $body = $request->body();
            foreach (['radiograph_npz', 'gain_npz', 'manifest'] as $field) {
                $this->assertStringContainsString('name="'.$field.'"', $body);
            }
            $this->assertStringContainsString('"member_id":"'.$fixture['memberId'].'"', $body);
            foreach (['detector_type', 'gain_id', 'image_spacing', 'conversion_job_id', 'study_instance_uid'] as $omitted) {
                $this->assertStringNotContainsString($omitted, $body);
            }

            return Http::response(
                str_repeat("\0", 128).'DICM'.'valid dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            );
        });

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');

        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        Http::assertSentCount(1);
    }

    public function test_retrying_a_missing_radiograph_does_not_repeat_gain_or_mpips(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());
        $submissionId = (string) Str::uuid();

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect();
        $capture = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $gain = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'gain')->first();
        $radiograph = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->first();
        Storage::disk('local')->delete((string) $radiograph->object_key);
        Storage::disk('local')->delete((string) $radiograph->object_key.'.meta.json');
        DB::table('image_gateway_capture_objects')->where('id', $radiograph->id)->delete();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['radiograph_status' => 'failed']);

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
        ])->assertRedirect();

        $this->assertSame(1, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'gain')->count());
        $this->assertSame((string) $gain->object_key, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'gain')->value('object_key'));
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());
        Http::assertSentCount(1);
    }

    public function test_invalid_upload_has_no_capture_queue_or_private_object_residue(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiograph_npz' => UploadedFile::fake()->createWithContent('not-an-npz.npz', 'not a zip'),
                'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect(route('operator.xray-capture.show', $admission));

        $this->assertSame('called', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame(0, DB::table('image_gateway_capture_objects')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'image-gateway.capture-accepted')->count());
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'image-gateway-capture-accepted')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_retryable_and_terminal_mpips_responses_are_persisted_without_a_study(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fakeSequence()->push([], 503)->push(
            str_repeat("\0", 128).'DICM'.'recovered dicom',
            200,
            [
                'Content-Type' => 'application/dicom',
                'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            ],
        );

        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        $this->assertSame('retrying', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $job = new ProcessCaptureSet($captureId);
        app()->call([$job, 'handle']);
        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        Http::assertSentCount(2);
    }

    public function test_malformed_mpips_dicom_is_not_published(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake(Http::response('not dicom', 200, [
            'Content-Type' => 'application/dicom',
            'X-Conversion-Job-ID' => (string) Str::uuid(),
            'X-Correlation-ID' => (string) Str::uuid(),
        ]));

        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        $job = new ProcessCaptureSet($captureId);
        app()->call([$job, 'handle']);

        $this->assertSame('failed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame('transport_invalid', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('last_error_code'));
        $this->assertSame(0, DB::table('image_gateway_studies')->count());
        Http::assertSentCount(2);
    }

    public function test_redelivery_recovers_a_claim_abandoned_after_the_queue_lease(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());

        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        DB::table('image_gateway_capture_sets')->where('id', $captureId)->update([
            'processing_status' => 'processing',
            'attempts' => 1,
            'updated_at' => now()->subSeconds((int) config('queue.connections.database.retry_after') + 1),
        ]);

        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->count());
        Http::assertSentCount(1);
    }

    public function test_duplicate_delivery_while_a_claim_is_active_does_not_create_a_second_study(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $captureId = null;
        $duplicateRan = false;
        Http::fake(function () use (&$captureId, &$duplicateRan) {
            if (! $duplicateRan) {
                $duplicateRan = true;
                app()->call([new ProcessCaptureSet((string) $captureId), 'handle']);
            }

            return Http::response(
                str_repeat("\0", 128).'DICM'.'valid dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            );
        });

        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->count());
        Http::assertSentCount(1);
    }

    public function test_expired_claim_fences_the_interrupted_worker_from_creating_a_second_study(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $captureId = null;
        $requests = 0;
        Http::fake(function () use (&$captureId, &$requests) {
            $requests++;
            if ($requests === 1) {
                DB::table('image_gateway_capture_sets')->where('id', $captureId)->update([
                    'processing_lease_expires_at' => now()->subSecond(),
                ]);
                app()->call([new ProcessCaptureSet((string) $captureId), 'handle']);
            }

            return Http::response(
                str_repeat("\0", 128).'DICM'.'valid dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            );
        });

        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->count());
        Http::assertSentCount(1);
    }

    public function test_same_site_current_shift_operator_can_read_the_returned_dicom_and_other_boundaries_cannot(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());
        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);
        $studyId = (string) DB::table('image_gateway_studies')->value('id');
        $this->assertNotSame('', $studyId);
        app()->call([new ProcessCaptureSet($captureId), 'handle']);
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        Http::assertSentCount(1);

        $second = $this->secondOperatorFixture($fixture);
        $this->actingAs($second['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->get(route('operator.study.results'))->assertOk()->assertSee($studyId);
        $this->get(route('operator.study.dicom', $studyId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/dicom')
            ->assertHeader('Cache-Control', 'no-store, private');
        $download = $this->get(route('operator.study.download', $studyId));
        $download->assertOk();
        $this->assertStringStartsWith('attachment; filename="capture-', (string) $download->headers->get('Content-Disposition'));

        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'image-gateway-foreign-'.$fixture['memberId']),
        ]);
        $foreign = $this->operatorFixture(false);
        $this->actingAs($foreign['operator'])->withSession(['operator.active_site_id' => $foreign['siteLocalId']]);
        $this->get(route('operator.study.results'))->assertOk()->assertDontSee($studyId);
        $this->get(route('operator.study.show', $studyId))->assertForbidden();
        $this->get(route('operator.study.dicom', $studyId))->assertForbidden();

        $this->actingAs($second['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $second['profileId'])->update(['status' => 'revoked']);
        $this->get(route('operator.study.results'))->assertOk()->assertDontSee($studyId);
        $this->get(route('operator.study.show', $studyId))->assertForbidden();

        $this->actingAs($fixture['memberUser'])->withSession([]);
        $this->get(route('operator.study.results'))->assertForbidden();
        $this->get('/operator/studies/'.$studyId.'/npz')->assertNotFound();
    }

    private function insertCalledXrayAdmission(array $fixture): string
    {
        $now = now();
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'IMG-XRAY-01',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'called',
            'ready_at' => $now,
            'operator_profile_id' => $fixture['profileId'],
            'claimed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $admissionId;
    }

    /** @return callable(Request): Response */
    private function validMpipsResponse(): callable
    {
        return static fn () => Http::response(
            str_repeat("\0", 128).'DICM'.'valid dicom payload',
            200,
            [
                'Content-Type' => 'application/dicom',
                'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            ],
        );
    }

    private function postCapture(string $admission): void
    {
        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));
    }

    private function fixtureUpload(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('resources/fixtures/image-gateway/'.$name),
            $name,
            'application/octet-stream',
            null,
            true,
        );
    }
}
