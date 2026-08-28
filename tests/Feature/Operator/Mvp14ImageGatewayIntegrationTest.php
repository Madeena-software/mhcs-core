<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
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
        $acceptedAudit = DB::table('audit_events')->where('action', 'image-gateway.capture-accepted')->first();
        $this->assertNotNull($acceptedAudit);
        $this->assertSame((string) $fixture['operator']->id, (string) $acceptedAudit->actor_id);
        $this->assertNotNull($acceptedAudit->correlation_id);
        $this->assertSame([
            'capture_id' => (string) $capture->id,
            'admission_id' => $admission,
            'operator_site_id' => $fixture['siteStableId'],
            'status' => 'accepted',
        ], json_decode((string) $acceptedAudit->metadata, true, 512, JSON_THROW_ON_ERROR));
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
            ->assertSee('Tiket sesi foto radiografi: <code>IMG-XRAY-01</code>', false)
            ->assertSee('name="metadata[examination][study_description]"', false)
            ->assertSee('value="CHEST RADIOGRAPH"', false)
            ->assertSee('name="metadata[capture][detector_type]"', false)
            ->assertSee('value="BED"', false)
            ->assertSee('value="TRX"', false)
            ->assertSee('<select id="body_part_examined"', false)
            ->assertSee('value="CHEST"', false)
            ->assertSee('<select id="laterality"', false)
            ->assertSee('value="U"', false)
            ->assertSee('<select id="projection"', false)
            ->assertSee('value="PA"', false)
            ->assertDontSee('<datalist', false)
            ->assertDontSee('value="THORAX"', false);
    }

    public function test_frozen_capture_metadata_shows_laterality_code_and_label(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => [
                'examination' => ['study_description' => 'CHEST RADIOGRAPH'],
                'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'L', 'projection' => 'PA'],
            ],
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('L (Kiri)', false);
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

    public function test_failed_capture_can_correct_bed_to_trx_and_retry_with_the_same_sources_and_new_signature(): void
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

        $capture = DB::table('image_gateway_capture_sets')->where('id', DB::table('image_gateway_capture_sets')->value('id'))->first();
        $objects = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->get()->keyBy('object_type');
        $oldMetadata = json_decode((string) $capture->capture_metadata, true, 512, JSON_THROW_ON_ERROR);
        $oldManifestBytes = Storage::disk('local')->get((string) $objects->get('manifest')->object_key);
        $oldManifest = json_decode($oldManifestBytes, true, 512, JSON_THROW_ON_ERROR);
        $oldSignature = json_decode(Storage::disk('local')->get((string) $objects->get('manifest_signature')->object_key), true, 512, JSON_THROW_ON_ERROR);
        $radiographBytes = Storage::disk('local')->get((string) $objects->get('radiograph')->object_key);
        $gainBytes = Storage::disk('local')->get((string) $objects->get('gain')->object_key);
        $stableCaptureFields = array_intersect_key((array) $capture, array_flip([
            'id', 'submission_id', 'admission_id', 'booking_id', 'member_schedule_id', 'operator_site_id',
            'operator_profile_id', 'status', 'accepted_at', 'radiograph_checksum', 'gain_checksum',
        ]));

        $mpipsBody = null;
        $mpipsCalls = 0;
        Http::fake(function (Request $request) use (&$mpipsBody, &$mpipsCalls) {
            $mpipsCalls++;
            if ($mpipsCalls === 1) {
                return Http::response(['detail' => 'conversion failed'], 500);
            }

            $mpipsBody = $request->body();

            return Http::response(
                str_repeat("\0", 128).'DICM'.'corrected dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            );
        });
        app()->call([new ProcessCaptureSet((string) $capture->id), 'handle']);
        $this->assertSame('failed', DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('processing_status'));

        $this->post(route('operator.xray-capture.correct-detector', $admission), [
            'detector_type' => 'TRX',
        ])->assertRedirect(route('operator.study.results'));

        $correctionAudits = DB::table('audit_events')->where('action', 'image-gateway.detector-corrected')->get();
        $this->assertCount(1, $correctionAudits);
        $correctionAudit = $correctionAudits->first();
        $this->assertSame('image-gateway', $correctionAudit->source);
        $this->assertSame('success', $correctionAudit->outcome);
        $this->assertSame('image-gateway.capture-set', $correctionAudit->target_type);
        $this->assertSame((string) $capture->id, $correctionAudit->target_id);
        $this->assertSame((string) $fixture['operator']->id, (string) $correctionAudit->actor_id);
        $this->assertNotNull($correctionAudit->correlation_id);
        $this->assertSame([
            'capture_id' => (string) $capture->id,
            'admission_id' => $admission,
            'operator_site_id' => $fixture['siteStableId'],
            'previous_detector' => 'BED',
            'corrected_detector' => 'TRX',
        ], json_decode((string) $correctionAudit->metadata, true, 512, JSON_THROW_ON_ERROR));

        $corrected = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $correctedMetadata = json_decode((string) $corrected->capture_metadata, true, 512, JSON_THROW_ON_ERROR);
        $expectedMetadata = $oldMetadata;
        $expectedMetadata['capture']['detector_type'] = 'TRX';
        $this->assertSame($expectedMetadata, $correctedMetadata);
        foreach ($stableCaptureFields as $field => $value) {
            $this->assertSame((string) $value, (string) $corrected->{$field}, $field);
        }
        $this->assertSame('accepted', $corrected->status);
        $this->assertSame('pending', $corrected->processing_status);
        $this->assertSame('pending', $corrected->mpips_status);
        $this->assertSame('pending', $corrected->dicom_status);
        $this->assertNull($corrected->last_error_code);
        $this->assertNull($corrected->last_response_status);
        $this->assertNull($corrected->failed_at);
        $this->assertSame(1, (int) $corrected->attempts);

        $newObjects = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->get()->keyBy('object_type');
        foreach (['radiograph', 'gain'] as $type) {
            $this->assertSame((string) $objects->get($type)->id, (string) $newObjects->get($type)->id);
            $this->assertSame((string) $objects->get($type)->object_key, (string) $newObjects->get($type)->object_key);
            $this->assertSame((string) $objects->get($type)->checksum, (string) $newObjects->get($type)->checksum);
            $this->assertSame(Storage::disk('local')->get((string) $objects->get($type)->object_key), $type === 'radiograph' ? $radiographBytes : $gainBytes);
        }

        $newManifestBytes = Storage::disk('local')->get((string) $newObjects->get('manifest')->object_key);
        $newManifest = json_decode($newManifestBytes, true, 512, JSON_THROW_ON_ERROR);
        $expectedManifest = $oldManifest;
        $expectedManifest['capture']['detector_type'] = 'TRX';
        $this->assertSame($expectedManifest, $newManifest);
        $newSignature = json_decode(Storage::disk('local')->get((string) $newObjects->get('manifest_signature')->object_key), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame($oldSignature['signature'], $newSignature['signature']);
        $verified = app(ManifestSigner::class)->verify(SignedManifest::fromArray($newSignature));
        $this->assertSame(hash('sha256', $newManifestBytes), $verified->metadataChecksum);
        Storage::disk('local')->assertMissing((string) $objects->get('manifest')->object_key);
        Storage::disk('local')->assertMissing((string) $objects->get('manifest_signature')->object_key);

        app()->call([new ProcessCaptureSet((string) $capture->id), 'handle']);

        $afterProcessing = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $this->assertSame('completed', $afterProcessing->processing_status);
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());
        $this->assertIsString($mpipsBody);
        $this->assertStringContainsString('"detector_type":"TRX"', $mpipsBody);
        $this->assertStringNotContainsString('"detector_type":"BED"', $mpipsBody);
        Queue::assertPushed(ProcessCaptureSet::class, 2);
        Http::assertSentCount(2);
    }

    public function test_eligible_failed_capture_shows_frozen_metadata_and_explicit_detector_correction_only(): void
    {
        ['admission' => $admission] = $this->failedCapture();

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('Metadata pengambilan gambar (dibekukan)')
            ->assertSee('Jenis detektor')
            ->assertSee('BED')
            ->assertSee('name="detector_type"', false)
            ->assertSee('value="BED"', false)
            ->assertSee('value="TRX"', false)
            ->assertSee('Koreksi jenis detektor dan coba proses DICOM lagi')
            ->assertDontSee('name="metadata[capture][detector_type]"', false)
            ->assertDontSee('name="radiograph_npz"', false)
            ->assertDontSee('name="gain_npz"', false);
    }

    public function test_detector_correction_rejects_a_capture_that_is_not_failed(): void
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
        ])->assertRedirect();

        $captureId = (string) DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->value('id');
        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');

        $this->assertSame('BED', json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $captureId)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'image-gateway.detector-corrected')->count());
        Queue::assertPushed(ProcessCaptureSet::class, 1);
    }

    public function test_detector_correction_rejects_missing_sources(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();
        DB::table('image_gateway_capture_objects')
            ->where('capture_set_id', $capture->id)
            ->where('object_type', 'radiograph')
            ->delete();

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');
        Queue::assertPushed(ProcessCaptureSet::class, 1);
    }

    public function test_detector_correction_rejects_an_existing_study(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();
        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $capture->submission_id,
        ])->assertRedirect(route('operator.study.results'));
        app()->call([new ProcessCaptureSet((string) $capture->id), 'handle']);
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');
        $this->assertSame('BED', json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
    }

    public function test_detector_correction_rejects_same_or_invalid_detector_without_dispatching(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'BED'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');
        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'INVALID'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('detector_type');

        $this->assertSame('BED', json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
        Queue::assertPushed(ProcessCaptureSet::class, 1);
    }

    public function test_detector_correction_rejects_unavailable_metadata_and_unauthorized_operator_context(): void
    {
        ['capture' => $capture, 'admission' => $admission, 'fixture' => $fixture] = $this->failedCapture();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['capture_metadata' => null]);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');

        $other = $this->secondOperatorFixture($fixture);
        $this->actingAs($other['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])->assertForbidden();
        Queue::assertPushed(ProcessCaptureSet::class, 1);
    }

    public function test_detector_correction_rejects_an_inconsistent_stored_manifest(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update([
            'manifest_checksum' => hash('sha256', 'tampered manifest'),
        ]);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');
        $this->assertSame('BED', json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
        Queue::assertPushed(ProcessCaptureSet::class, 1);
    }

    public function test_detector_correction_ignores_unrelated_metadata_input(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();

        $this->post(route('operator.xray-capture.correct-detector', $admission), [
            'detector_type' => 'TRX',
            'metadata' => [
                'examination' => ['study_description' => 'CHANGED'],
                'capture' => ['body_part_examined' => 'HAND', 'laterality' => 'L', 'projection' => 'AP'],
            ],
        ])->assertRedirect(route('operator.study.results'));

        $metadata = json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('TRX', $metadata['capture']['detector_type']);
        $this->assertSame('CHEST RADIOGRAPH', $metadata['examination']['study_description']);
        $this->assertSame('CHEST', $metadata['capture']['body_part_examined']);
        $this->assertSame('U', $metadata['capture']['laterality']);
        $this->assertSame('PA', $metadata['capture']['projection']);
        Queue::assertPushed(ProcessCaptureSet::class, 2);
    }

    public function test_conflicting_detector_correction_does_not_record_a_success_audit(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();
        $realStore = app(PrivateObjectStore::class);
        $putCount = 0;
        $store = Mockery::mock(PrivateObjectStore::class);
        $store->shouldIgnoreMissing();
        $store->shouldReceive('grant')->andReturnUsing(fn (...$arguments) => $realStore->grant(...$arguments));
        $store->shouldReceive('get')->andReturnUsing(fn (...$arguments) => $realStore->get(...$arguments));
        $store->shouldReceive('delete')->andReturnUsing(fn (...$arguments) => $realStore->delete(...$arguments));
        $store->shouldReceive('put')->twice()->andReturnUsing(function (...$arguments) use (&$putCount, $realStore, $capture): PrivateObject {
            $object = $realStore->put(...$arguments);
            if (++$putCount === 1) {
                $metadata = json_decode((string) DB::table('image_gateway_capture_sets')->where('id', $capture->id)->value('capture_metadata'), true, 512, JSON_THROW_ON_ERROR);
                $metadata['capture']['detector_type'] = 'TRX';
                DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update([
                    'capture_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                ]);
            }

            return $object;
        });
        $beforeObjects = DB::table('image_gateway_capture_objects')
            ->where('capture_set_id', $capture->id)
            ->orderBy('object_type')
            ->get()
            ->map(fn (object $object): array => [(string) $object->id, (string) $object->object_key, (string) $object->checksum])
            ->all();
        $beforeFiles = Storage::disk('local')->allFiles();
        $this->app->instance(PrivateObjectStore::class, $store);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');

        $afterObjects = DB::table('image_gateway_capture_objects')
            ->where('capture_set_id', $capture->id)
            ->orderBy('object_type')
            ->get()
            ->map(fn (object $object): array => [(string) $object->id, (string) $object->object_key, (string) $object->checksum])
            ->all();
        $this->assertSame($beforeObjects, $afterObjects);
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'image-gateway.detector-corrected')->count());
    }

    public function test_detector_correction_at_retry_budget_is_fail_closed_without_mutation_or_audit(): void
    {
        ['capture' => $capture, 'admission' => $admission] = $this->failedCapture();
        $maxAttempts = (int) config('mhcs.mpips.max_attempts', 5);
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['attempts' => $maxAttempts]);
        $beforeCapture = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $beforeObjects = DB::table('image_gateway_capture_objects')
            ->where('capture_set_id', $capture->id)
            ->orderBy('object_type')
            ->get()
            ->map(fn (object $object): array => [(string) $object->id, (string) $object->object_key, (string) $object->checksum])
            ->all();
        $manifestKey = (string) DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest')->value('object_key');
        $signatureKey = (string) DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest_signature')->value('object_key');
        $beforeManifest = Storage::disk('local')->get($manifestKey);
        $beforeSignature = Storage::disk('local')->get($signatureKey);
        $beforeFiles = Storage::disk('local')->allFiles();
        $beforeAudit = DB::table('audit_events')
            ->orderBy('event_id')
            ->get()
            ->map(fn (object $event): array => (array) $event)
            ->all();

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])
            ->assertRedirect(route('operator.xray-capture.show', $admission))
            ->assertSessionHasErrors('capture');

        $afterCapture = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first();
        $afterObjects = DB::table('image_gateway_capture_objects')
            ->where('capture_set_id', $capture->id)
            ->orderBy('object_type')
            ->get()
            ->map(fn (object $object): array => [(string) $object->id, (string) $object->object_key, (string) $object->checksum])
            ->all();
        $this->assertSame($maxAttempts, (int) $afterCapture->attempts);
        $this->assertSame((string) $beforeCapture->capture_metadata, (string) $afterCapture->capture_metadata);
        $this->assertSame($beforeObjects, $afterObjects);
        $this->assertSame($beforeManifest, Storage::disk('local')->get($manifestKey));
        $this->assertSame($beforeSignature, Storage::disk('local')->get($signatureKey));
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        $afterAudit = DB::table('audit_events')
            ->orderBy('event_id')
            ->get()
            ->map(fn (object $event): array => (array) $event)
            ->all();
        $this->assertSame($beforeAudit, $afterAudit);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'image-gateway.detector-corrected')->count());
    }

    public function test_detector_correction_rejects_a_wrong_active_site(): void
    {
        ['admission' => $admission] = $this->failedCapture();
        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);

        $this->post(route('operator.xray-capture.correct-detector', $admission), ['detector_type' => 'TRX'])->assertForbidden();
        Queue::assertPushed(ProcessCaptureSet::class, 1);
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
        $this->get(route('operator.study.results'))
            ->assertOk()
            ->assertSee('Nama')
            ->assertSee('Tiket kertas')
            ->assertSee('Rekam medis')
            ->assertSee('Shift')
            ->assertSee($studyReference)
            ->assertSee('Synthetic Arrival Member')
            ->assertSee('MRN-')
            ->assertSee((string) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->value('display_reference'))
            ->assertDontSee('<strong>'.$studyId.'</strong>', false);
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

    /** @return array{fixture: array<string, mixed>, admission: string, capture: object, submissionId: string} */
    private function failedCapture(): array
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
        Http::fakeSequence()
            ->push(['detail' => 'conversion failed'], 500)
            ->pushResponse($this->validMpipsResponse());
        app()->call([new ProcessCaptureSet($captureId), 'handle']);
        $capture = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        $this->assertSame('failed', $capture->processing_status);

        return compact('fixture', 'admission', 'capture', 'submissionId');
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
