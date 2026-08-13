<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
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

    public function test_capture_persists_sources_and_queues_mpips_without_calling_it(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $submissionId = (string) Str::uuid();
        Http::fake();

        $response = $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            ...$this->metadataPayload(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ]);
        $this->assertSame(302, $response->status(), $response->headers->get('Location') ?? '');
        $this->assertSame(route('operator.study.results'), $response->headers->get('Location'));

        $capture = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $this->assertNotNull($capture);
        $this->assertSame('pending', $capture->processing_status);
        $this->assertSame('success', $capture->radiograph_status);
        $this->assertSame('success', $capture->gain_status);
        $this->assertSame('pending', $capture->mpips_status);
        $this->assertSame('awaiting_ai', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertNull(DB::table('operator_queue_admissions')->where('id', $admission)->value('operator_profile_id'));
        $this->assertSame($fixture['profileId'], $capture->operator_profile_id);
        $this->assertSame([
            'examination' => ['study_description' => 'CHEST RADIOGRAPH'],
            'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA'],
        ], json_decode((string) $capture->capture_metadata, true, 512, JSON_THROW_ON_ERROR));
        $manifestObject = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest')->first();
        $manifest = json_decode((string) Storage::disk('local')->get((string) $manifestObject->object_key), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('CHEST RADIOGRAPH', $manifest['examination']['study_description']);
        $this->assertSame('BED', $manifest['capture']['detector_type']);
        $this->assertSame('CHEST', $manifest['capture']['body_part_examined']);
        $this->assertSame('U', $manifest['capture']['laterality']);
        $this->assertSame('PA', $manifest['capture']['projection']);
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('operator_queue_admission_id', $admission)->where('event_type', 'capture_accepted')->count());
        $this->assertSame(0, DB::table('image_gateway_studies')->count());
        $this->assertSame(4, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->count());
        Queue::assertPushed(ProcessCaptureSet::class, 1);
        Http::assertNothingSent();
        $radiograph = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->first();
        $this->assertSame(file_get_contents(base_path('resources/fixtures/image-gateway/synthetic-radiograph-01.npz')), Storage::disk('local')->get((string) $radiograph->object_key));
        $this->assertArrayNotHasKey('encryption', json_decode((string) Storage::disk('local')->get((string) $radiograph->object_key.'.meta.json'), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_capture_starts_both_source_writes_before_dispatching_the_worker(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $events = [];
        $pending = [];
        $createdAt = new DateTimeImmutable('2026-08-13T00:00:00+00:00');
        $store = Mockery::mock(PrivateObjectStore::class);
        $store->shouldReceive('put')->twice()->andReturnUsing(
            static fn (string $contents): PrivateObject => new PrivateObject(
                OpaqueObjectKey::fromString('objects/manifest-'.hash('sha256', $contents)),
                hash('sha256', $contents),
                strlen($contents),
                $createdAt,
            ),
        );
        $store->shouldReceive('putStreamAsync')->twice()->andReturnUsing(function ($stream, int $bytes, string $checksum, $context, string $purpose, OpaqueObjectKey $key) use (&$events, &$pending, $createdAt): PromiseInterface {
            $type = basename((string) $key);
            $events[] = 's3:'.$type;
            $object = new PrivateObject($key, $checksum, $bytes, $createdAt);
            $promise = new Promise;
            $pending[] = [$promise, $object];
            if (count($pending) === 2) {
                foreach ($pending as [$pendingPromise, $pendingObject]) {
                    $pendingPromise->resolve($pendingObject);
                }
            }

            return $promise;
        });
        $store->shouldReceive('putStream')->zeroOrMoreTimes()->andReturnUsing(
            static fn ($stream, int $bytes, string $checksum, $context, string $purpose, OpaqueObjectKey $key): PrivateObject => new PrivateObject($key, $checksum, $bytes, $createdAt),
        );
        $this->app->instance(PrivateObjectStore::class, $store);
        Http::fake(function (Request $request) use (&$events, &$pending) {
            $this->assertSame(['s3:radiograph', 's3:gain'], $events);
            $this->assertCount(2, $pending);

            return Http::response([], 500);
        });

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            ...$this->metadataPayload(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));

        $this->assertSame(['s3:radiograph', 's3:gain'], $events);
        Queue::assertPushed(ProcessCaptureSet::class, 1);
        Http::assertNothingSent();
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
            foreach (['gain_id', 'image_spacing', 'conversion_job_id', 'study_instance_uid'] as $omitted) {
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
            ...$this->metadataPayload(),
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
            ...$this->metadataPayload(),
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
        $this->assertSame(0, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());
        Http::assertNothingSent();
    }

    public function test_capture_form_has_the_required_indonesian_metadata_controls_and_defaults(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('name="metadata[examination][study_description]"', false)
            ->assertSee('value="CHEST RADIOGRAPH"', false)
            ->assertSee('name="metadata[capture][detector_type]"', false)
            ->assertSee('value="BED"', false)
            ->assertSee('value="TRX"', false)
            ->assertSee('name="metadata[capture][body_part_examined]"', false)
            ->assertSee('value="CHEST"', false)
            ->assertSee('name="metadata[capture][laterality]"', false)
            ->assertSee('value="U"', false)
            ->assertSee('name="metadata[capture][projection]"', false)
            ->assertSee('value="PA"', false)
            ->assertDontSee('THORAX');
    }

    public function test_capture_metadata_boundaries_are_rejected_before_capture_creation(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $cases = [
            [],
            ['metadata' => ['examination' => ['study_description' => str_repeat('X', 65)], 'capture' => ['detector_type' => 'THORAX', 'body_part_examined' => 'INVALID', 'laterality' => 'X', 'projection' => 'INVALID']]],
        ];

        foreach ($cases as $metadata) {
            $response = $this->from(route('operator.xray-capture.show', $admission))
                ->post(route('operator.xray-capture.store', $admission), array_merge([
                    'submission_id' => (string) Str::uuid(),
                    'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
                    'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
                ], $metadata));

            $response->assertRedirect(route('operator.xray-capture.show', $admission))->assertSessionHasErrors();
            $this->assertSame(0, DB::table('image_gateway_capture_sets')->where('admission_id', $admission)->count());
        }
    }

    public function test_legacy_null_metadata_is_not_backfilled_or_manifest_rewritten_on_retry(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());
        $this->postCapture($admission);

        $capture = DB::table('image_gateway_capture_sets')->where('admission_id', $admission)->first();
        $manifestChecksum = (string) $capture->manifest_checksum;
        $manifestCount = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest')->count();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['capture_metadata' => null, 'radiograph_status' => 'failed']);
        $radiograph = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->first();
        Storage::disk('local')->delete((string) $radiograph->object_key);
        Storage::disk('local')->delete((string) $radiograph->object_key.'.meta.json');
        DB::table('image_gateway_capture_objects')->where('id', $radiograph->id)->delete();

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $capture->submission_id,
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
        ])->assertRedirect();

        $after = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $this->assertNull($after->capture_metadata);
        $this->assertSame($manifestChecksum, $after->manifest_checksum);
        $this->assertSame($manifestCount, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest')->count());
    }

    public function test_changed_missing_radiograph_is_rejected_before_external_work(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());
        $submissionId = (string) Str::uuid();

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            ...$this->metadataPayload(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect();
        $capture = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $radiograph = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->first();
        $gain = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'gain')->first();
        Storage::disk('local')->delete((string) $radiograph->object_key);
        Storage::disk('local')->delete((string) $radiograph->object_key.'.meta.json');
        DB::table('image_gateway_capture_objects')->where('id', $radiograph->id)->delete();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['radiograph_status' => 'failed']);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => $submissionId,
                'metadata' => [
                    'examination' => ['study_description' => 'CHANGED'],
                    'capture' => ['detector_type' => 'TRX', 'body_part_examined' => 'HAND', 'laterality' => 'L', 'projection' => 'AP'],
                ],
                'radiograph_npz' => UploadedFile::fake()->createWithContent('changed.npz', "PK\x03\x04changed"),
            ])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');

        $after = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $this->assertSame('failed', $after->radiograph_status);
        $this->assertSame('success', $after->gain_status);
        $this->assertSame('CHEST RADIOGRAPH', json_decode((string) $after->capture_metadata, true, 512, JSON_THROW_ON_ERROR)['examination']['study_description']);
        $this->assertSame('BED', json_decode((string) $after->capture_metadata, true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
        $this->assertSame((string) $gain->object_key, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'gain')->value('object_key'));
        $this->assertSame(0, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());
        $this->assertSame(0, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'radiograph')->count());
        Http::assertNothingSent();
    }

    public function test_invalid_upload_has_no_capture_queue_or_private_object_residue(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                ...$this->metadataPayload(),
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
        $job = new ProcessCaptureSet($captureId);
        app()->call([$job, 'handle']);
        $this->assertSame('retrying', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        app()->call([$job, 'handle']);
        $this->assertSame('completed', DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('processing_status'));
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        Http::assertSentCount(2);
    }

    public function test_failed_dicom_processing_can_be_requeued_without_reuploading_sources(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $submissionId = (string) Str::uuid();

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            ...$this->metadataPayload(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));

        $captureId = (string) DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->value('id');
        Http::fake(Http::response(['detail' => 'conversion failed'], 500));
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        $failed = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        $this->assertSame('failed', $failed->processing_status);
        $this->assertSame('success', $failed->radiograph_status);
        $this->assertSame('success', $failed->gain_status);

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('IMG-XRAY-01')
            ->assertSee('Pemrosesan DICOM gagal')
            ->assertSee('Coba proses DICOM lagi');

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('Coba proses DICOM lagi')
            ->assertDontSee('name="radiograph_npz"', false)
            ->assertDontSee('name="gain_npz"', false);

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
        ])->assertRedirect(route('operator.study.results'));

        $retried = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        $this->assertSame('pending', $retried->processing_status);
        $this->assertSame('pending', $retried->mpips_status);
        $this->assertSame('pending', $retried->dicom_status);
        $this->assertNull($retried->last_error_code);
        Queue::assertPushed(ProcessCaptureSet::class, 2);
    }

    public function test_capture_status_is_safe_and_capture_authorized(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake();
        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');

        $this->getJson(route('operator.xray-capture.status', $admission))
            ->assertOk()
            ->assertExactJson([
                'capture_id' => $captureId,
                'processing_state' => 'queued',
                'missing_components' => [],
                'ready_results_url' => route('operator.study.results'),
            ]);

        $second = $this->secondOperatorFixture($fixture);
        $this->actingAs($second['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->getJson(route('operator.xray-capture.status', $admission))->assertForbidden();
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
        Http::assertSentCount(1);
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
        Http::assertSentCount(2);
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
        $studyReference = (string) DB::table('image_gateway_studies')->value('display_reference');
        $this->assertMatchesRegularExpression('/\ADCM-[A-Z0-9]{8}\z/', $studyReference);
        app()->call([new ProcessCaptureSet($captureId), 'handle']);
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        $this->assertSame($studyReference, DB::table('image_gateway_studies')->value('display_reference'));
        Http::assertSentCount(1);

        $second = $this->secondOperatorFixture($fixture);
        $this->actingAs($second['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->get(route('operator.study.results'))->assertOk()->assertSee($studyReference)->assertDontSee('<strong>'.$studyId.'</strong>', false);
        $this->get(route('operator.study.dicom', $studyId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/dicom')
            ->assertHeader('Cache-Control', 'no-store, private');
        $download = $this->get(route('operator.study.download', $studyId));
        $download->assertOk();
        $this->assertSame('attachment; filename="'.$studyReference.'.dcm"', (string) $download->headers->get('Content-Disposition'));

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

    public function test_display_reference_migration_backfills_a_legacy_study(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        Http::fake($this->validMpipsResponse());
        $this->postCapture($admission);
        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);
        $studyId = (string) DB::table('image_gateway_studies')->value('id');

        DB::table('image_gateway_studies')->where('id', $studyId)->update(['filename' => 'capture-'.$studyId.'.dcm']);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->dropUnique('shift_schedules_display_reference_unique');
            $table->dropColumn('display_reference');
        });
        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->dropUnique('image_gateway_studies_display_reference_unique');
            $table->dropColumn('display_reference');
        });
        $migration = require base_path('database/migrations/2026_08_13_000003_add_operator_display_references.php');
        $migration->up();

        $studyReference = (string) DB::table('image_gateway_studies')->where('id', $studyId)->value('display_reference');
        $this->assertMatchesRegularExpression('/\ADCM-[A-Z0-9]{8}\z/', $studyReference);
        $this->assertSame($studyReference.'.dcm', DB::table('image_gateway_studies')->where('id', $studyId)->value('filename'));
        $migration->down();
        $migration->up();
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
            ...$this->metadataPayload(),
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));
    }

    /** @return array<string, mixed> */
    private function metadataPayload(): array
    {
        return [
            'metadata' => [
                'examination' => ['study_description' => 'CHEST RADIOGRAPH'],
                'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA'],
            ],
        ];
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
