<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Outbox\OutboxStore;
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
            ->assertSee('Kirim pengambilan gambar sintetis')
            ->assertSee(route('operator.xray-capture.show', $admission));
    }

    public function test_capture_form_is_session_only_and_warns_before_navigation(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture);

        $this->get(route('operator.xray-capture.show', $admission))
            ->assertOk()
            ->assertSee('NPZ radiografi')
            ->assertSee('NPZ gain yang sesuai')
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
            ->followingRedirects()
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUploadAs('synthetic-radiograph-01.npz', 'renamed.npz')],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertOk()
            ->assertSee('Identitas fixture sintetis tidak diterima.')
            ->assertDontSee('The synthetic fixture identity is not accepted.');

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

    public function test_altered_fixture_bytes_fail_without_database_or_private_object_residue(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-ALTERED');

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [UploadedFile::fake()->createWithContent('synthetic-radiograph-01.npz', 'altered synthetic bytes')],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('capture');

        $this->assertNoCaptureSideEffects($admission);
    }

    public function test_missing_fixture_fails_without_database_or_private_object_residue(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-MISSING');

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('gain');

        $this->assertNoCaptureSideEffects($admission);
    }

    public function test_pair_mismatch_fails_without_database_or_private_object_residue(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-MISMATCH');

        $this->from(route('operator.xray-capture.show', $admission))
            ->post(route('operator.xray-capture.store', $admission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
                'gain' => $this->fixtureUploadAs('synthetic-radiograph-01.npz', 'synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('capture');

        $this->assertNoCaptureSideEffects($admission);
    }

    public function test_replay_conflict_does_not_accept_a_second_case(): void
    {
        $fixture = $this->readyFixture();
        $firstAdmission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-REPLAY-1');
        $submissionId = (string) Str::uuid();

        $payload = [
            'submission_id' => $submissionId,
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ];
        $this->post(route('operator.xray-capture.store', $firstAdmission), $payload)->assertRedirect();
        DB::table('operator_queue_admissions')->where('id', $firstAdmission)->update([
            'operator_profile_id' => null,
            'claimed_at' => null,
        ]);
        $secondAdmission = $this->insertCalledXrayAdmission(
            [...$fixture, 'bookingId' => $this->copyBooking($fixture)],
            'SYNTH-REPLAY-2',
        );

        $this->post(route('operator.xray-capture.store', $secondAdmission), [
            ...$payload,
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect()->assertSessionHasErrors('capture');

        $this->assertSame('awaiting_ai', DB::table('operator_queue_admissions')->where('id', $firstAdmission)->value('state'), 'first admission state');
        $this->assertNoCaptureSideEffects($secondAdmission, 1);
        $this->assertSame(1, DB::table('image_gateway_capture_sets')->count(), 'capture count');
        $this->assertSame(1, DB::table('idempotent_consumptions')->where('message_id', $submissionId)->count(), 'idempotency count');
    }

    public function test_cross_case_site_and_shift_inputs_fail_without_side_effects(): void
    {
        $fixture = $this->readyFixture();
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'mvp14-cross-boundary-'.$fixture['memberId']),
        ]);
        $foreignFixture = $this->operatorFixture(false);
        $foreignAdmission = $this->insertCalledXrayAdmission($foreignFixture, 'SYNTH-CROSS-CASE');

        $this->from(route('operator.xray-capture.show', $foreignAdmission))
            ->post(route('operator.xray-capture.store', $foreignAdmission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('capture');
        $this->assertNoCaptureSideEffects($foreignAdmission);

        $crossShiftAdmission = $this->insertCrossShiftCalledXrayAdmission($fixture);
        $this->from(route('operator.xray-capture.show', $crossShiftAdmission))
            ->post(route('operator.xray-capture.store', $crossShiftAdmission), [
                'submission_id' => (string) Str::uuid(),
                'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
                'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('capture');
        $this->assertNoCaptureSideEffects($crossShiftAdmission);
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
            ->assertSee('VOI otomatis')
            ->assertSee('Hanya zoom dan geser')
            ->assertSee('data-testid="dicom-viewport"', false)
            ->assertDontSee('Window/Level')
            ->assertDontSee('Brightness')
            ->assertSee(route('operator.study.download', $studyId));

        $dicom = $this->get(route('operator.study.dicom', $studyId));
        $dicom->assertOk()
            ->assertHeader('Content-Type', 'application/dicom')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(file_get_contents(base_path('resources/fixtures/image-gateway/synthetic-study.dcm')), $dicom->getContent());

        $download = $this->get(route('operator.study.download', $studyId));
        $download->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="synthetic-study.dcm"');
        $this->assertSame($dicom->getContent(), $download->getContent());
    }

    public function test_second_current_shift_operator_can_discover_view_and_download_the_submitting_operators_study(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-SHARED-STUDY');
        $this->postCapture($admission);
        $studyId = (string) DB::table('image_gateway_studies')->value('id');
        $objectKey = (string) DB::table('image_gateway_studies')->value('object_key');
        $second = $this->secondOperatorFixture($fixture);

        $this->actingAs($second['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.study.results'))
            ->assertOk()
            ->assertSee('Daftar kerja hasil DICOM')
            ->assertSee($studyId)
            ->assertSee(route('operator.study.show', $studyId))
            ->assertDontSee($objectKey)
            ->assertDontSee('synthetic-radiograph-01.npz')
            ->assertDontSee('Synthetic Arrival Member');

        $this->get(route('operator.study.show', $studyId))
            ->assertOk()
            ->assertSee('VOI otomatis')
            ->assertSee('Hanya zoom dan geser');
        $this->get(route('operator.study.dicom', $studyId))->assertOk();
        $this->get(route('operator.study.download', $studyId))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="synthetic-study.dcm"');

        $this->assertSame('accepted', DB::table('image_gateway_capture_sets')->value('status'));
        $this->assertSame($fixture['profileId'], DB::table('image_gateway_capture_sets')->value('operator_profile_id'));
        $this->assertSame('awaiting_ai', DB::table('operator_queue_admissions')->where('id', $admission)->value('state'));

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->get(route('operator.study.results'))->assertOk()->assertSee($studyId);
        $this->get(route('operator.study.download', $studyId))->assertOk();
    }

    public function test_study_viewer_and_download_require_current_operator_scope_and_have_no_raw_fixture_route(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-ACCESS');
        $this->postCapture($admission);
        $studyId = (string) DB::table('image_gateway_studies')->value('id');

        $this->app['auth']->logout();
        $this->get(route('operator.study.results'))->assertRedirect(route('login'));
        foreach ($this->studyRoutes($studyId) as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }

        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'mvp14-study-access-'.$fixture['memberId']),
        ]);
        $foreign = $this->operatorFixture(false);
        $this->actingAs($foreign['operator']);
        $this->withSession(['operator.active_site_id' => $foreign['siteLocalId']]);
        $this->get(route('operator.study.results'))->assertOk()->assertDontSee($studyId);
        foreach ($this->studyRoutes($studyId) as $route) {
            $this->get($route)->assertForbidden();
        }

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->get(route('operator.study.results'))->assertOk()->assertDontSee($studyId);
        foreach ($this->studyRoutes($studyId) as $route) {
            $this->get($route)->assertForbidden();
        }

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);
        DB::table('operator_eligible_shifts')->where('id', $fixture['eligibleId'])->update(['sync_status' => 'ineligible']);
        $this->get(route('operator.study.results'))->assertOk()->assertDontSee($studyId);
        foreach ($this->studyRoutes($studyId) as $route) {
            $this->get($route)->assertForbidden();
        }

        DB::table('operator_eligible_shifts')->where('id', $fixture['eligibleId'])->update(['sync_status' => 'eligible']);
        $unknownStudy = (string) Str::uuid();
        foreach ($this->studyRoutes($unknownStudy) as $route) {
            $this->get($route)->assertForbidden();
        }
        $this->get('/operator/studies/'.$studyId.'/npz')->assertNotFound();
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
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
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

    public function test_late_outbox_failure_rolls_back_rows_and_cleans_private_objects(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertCalledXrayAdmission($fixture, 'SYNTH-OUTBOX-FAILURE');
        $this->app->instance(AuditStore::class, new DatabaseAuditStore);
        $this->app->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic capture outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'radiographs' => [$this->fixtureUpload('synthetic-radiograph-01.npz')],
            'gain' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect()->assertSessionHasErrors('capture');

        $this->assertNoCaptureSideEffects($admission);
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
    private function insertCalledXrayAdmission(array $fixture, string $ticketNumber = 'SYNTH-XRAY-01'): string
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
            'ticket_number' => $ticketNumber,
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

    private function insertCrossShiftCalledXrayAdmission(array $fixture): string
    {
        $scheduleId = (string) Str::uuid();
        $schedule = (array) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->first();
        $schedule['id'] = $scheduleId;
        DB::table('shift_schedules')->insert($schedule);

        $bookingId = (string) Str::uuid();
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = $bookingId;
        $booking['shift_schedule_id'] = $scheduleId;
        DB::table('bookings')->insert($booking);

        return $this->insertCalledXrayAdmission(
            [...$fixture, 'scheduleId' => $scheduleId, 'bookingId' => $bookingId],
            'SYNTH-CROSS-SHIFT',
        );
    }

    private function copyBooking(array $fixture): string
    {
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = (string) Str::uuid();
        DB::table('bookings')->insert($booking);

        return (string) $booking['id'];
    }

    /** @return list<string> */
    private function studyRoutes(string $studyId): array
    {
        return [
            route('operator.study.show', $studyId),
            route('operator.study.dicom', $studyId),
            route('operator.study.download', $studyId),
        ];
    }

    private function assertNoCaptureSideEffects(string $admissionId, int $existingCaptureCount = 0): void
    {
        $this->assertSame('called', DB::table('operator_queue_admissions')->where('id', $admissionId)->value('state'));
        $this->assertSame($existingCaptureCount, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame($existingCaptureCount * 2, DB::table('image_gateway_capture_objects')->count());
        $this->assertSame($existingCaptureCount, DB::table('image_gateway_studies')->count());
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('operator_queue_admission_id', $admissionId)->where('event_type', 'capture_accepted')->count());
        $this->assertSame($existingCaptureCount, DB::table('audit_events')->where('action', 'image-gateway.capture-accepted')->count());
        $this->assertSame($existingCaptureCount, DB::table('outbox_messages')->where('event_name', 'image-gateway-capture-accepted')->count());
        $this->assertCount($existingCaptureCount * 6, Storage::disk('local')->allFiles());
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
