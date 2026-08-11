<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp14SyntheticCaptureGatewayTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'mhcs.security.object_key' => str_repeat('o', 32),
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.image_policy' => [
                'file_count' => 2,
                'per_file_bytes' => 1048576,
                'total_bytes' => 2097152,
                'decompressed_bytes' => 4194304,
                'max_width' => 4096,
                'max_height' => 4096,
                'field_count' => 32,
                'cpu_seconds' => 5,
                'memory_bytes' => 134217728,
                'execution_seconds' => 30,
                'process_count' => 1,
                'temporary_storage_bytes' => 8388608,
                'accepted_forms' => ['zip-npz'],
                'recovery_window_seconds' => 300,
                'max_attempts' => 1,
            ],
        ]);
    }

    public function test_called_xray_admission_exposes_the_synthetic_capture_entry_point(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('Submit synthetic capture')
            ->assertSee(route('operator.xray-capture.show', $admission));
    }

    public function test_capture_form_is_session_only_and_warns_before_navigation(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('Radiograph NPZ')
            ->assertSee('Matching gain NPZ')
            ->assertSee('beforeunload');
    }

    public function test_exact_fixture_pair_is_stored_once_and_advances_xray_after_dicom_association(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);
        $submissionId = (string) Str::uuid();

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect();

        $capture = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $this->assertNotNull($capture);
        $this->assertSame('accepted', $capture->status);
        $this->assertSame(2, DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->count());
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->count());
        $this->assertSame('awaiting_ai', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'image-gateway.capture-accepted')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'image-gateway-capture-accepted')->count());
        $this->assertSame(1, DB::table('idempotent_consumptions')->where('message_id', $submissionId)->where('consumer', 'image-gateway.capture.submit')->count());

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => $submissionId,
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect();

        $this->assertSame(1, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        $this->assertCount(6, Storage::disk('local')->allFiles());
    }

    public function test_invalid_or_duplicate_fixture_input_fails_without_stage_or_storage_side_effects(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUploadAs('synthetic-radiograph-01.npz', 'renamed.npz')],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('capture');

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [
                    $this->fixtureUpload('synthetic-radiograph-01.npz'),
                    $this->fixtureUpload('synthetic-radiograph-01.npz'),
                ],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('radiographs');

        $this->assertSame('called', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_operator_study_uses_the_private_dicom_grant_and_standard_download(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);
        $this->postCapture($admission);
        $studyId = (string) DB::table('image_gateway_studies')->value('id');

        $this->get(route('operator.study.show', $studyId))
            ->assertOk()
            ->assertSee('data-dicom-viewer')
            ->assertSee('Automatic VOI')
            ->assertSee('Zoom and pan only')
            ->assertSee('data-testid="dicom-viewport"', false)
            ->assertDontSee('Window/Level')
            ->assertDontSee('Brightness')
            ->assertSee(route('operator.study.download', $studyId));

        $dicom = $this->get(route('operator.study.dicom', $studyId));
        $dicom->assertOk()
            ->assertHeader('Content-Type', 'application/dicom')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(file_get_contents(base_path('resources/fixtures/image-gateway/synthetic-study.dcm')), $dicom->getContent());

        $this->get(route('operator.study.download', $studyId))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="synthetic-study.dcm"');
    }

    public function test_synthetic_bridge_is_forbidden_outside_local_and_testing(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);
        $this->app->instance('env', 'production');

        $this->post(route('operator.xray-capture.store', $admission), [
            '_token' => csrf_token(),
            'submission_id' => (string) Str::uuid(),
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertForbidden();

        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_late_audit_failure_rolls_back_rows_and_cleans_private_objects(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);
        $this->app->instance(AuditStore::class, new class implements AuditStore {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic audit failure');
            }
        });

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect()->assertSessionHasErrors('capture');

        $this->assertSame('called', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));
        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame(0, DB::table('image_gateway_studies')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    /** @return array<string, mixed> */
    private function readyFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        return $fixture;
    }

    /** @param array<string, mixed> $fixture */
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
            'ticket_number' => 'SYNTH-XRAY-01',
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

    private function fixtureUploadAs(string $source, string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('resources/fixtures/image-gateway/'.$source),
            $name,
            'application/octet-stream',
            null,
            true,
        );
    }

    private function postCapture(string $admission): void
    {
        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect();
    }
}
