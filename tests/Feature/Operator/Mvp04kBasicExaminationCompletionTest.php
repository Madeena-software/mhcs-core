<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04kBasicExaminationCompletionTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claimant_completes_basic_examination_and_readies_the_same_ticket_for_xray(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('COMPLETE-1');
        $this->recordVitalSigns($admission->id);
        $this->recordQuestionnaire($fixture);
        $operationId = (string) Str::uuid();

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => $operationId])
            ->assertRedirect(route('operator.basic-examination-worklist'));

        $basic = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $xray = DB::table('operator_queue_admissions')
            ->where('operator_paper_ticket_id', $admission->operator_paper_ticket_id)
            ->where('stage', 'xray')
            ->first();
        $completed = DB::table('operator_queue_admission_history')
            ->where('operator_queue_admission_id', $admission->id)
            ->where('event_type', 'completed')
            ->first();
        $xrayAdmission = DB::table('operator_queue_admission_history')
            ->where('operator_queue_admission_id', $xray->id)
            ->where('event_type', 'admitted')
            ->first();

        $this->assertSame('completed', $basic->state);
        $this->assertNull($basic->operator_profile_id);
        $this->assertNull($basic->claimed_at);
        $this->assertSame('advance', $xray->queue_class);
        $this->assertSame('xray', $xray->stage);
        $this->assertSame('waiting', $xray->state);
        $this->assertNull($xray->operator_profile_id);
        $this->assertSame((string) $admission->operator_paper_ticket_id, (string) $xray->operator_paper_ticket_id);
        $this->assertSame((string) $admission->operator_site_id, (string) $xray->operator_site_id);
        $this->assertSame((string) $admission->member_schedule_id, (string) $xray->member_schedule_id);
        $this->assertSame((string) $completed->occurred_at, (string) $xray->ready_at);
        $this->assertSame((string) $completed->occurred_at, (string) $xrayAdmission->occurred_at);
        $this->assertSame($fixture['profileId'], $completed->operator_profile_id);
        $this->assertSame($fixture['profileId'], $xrayAdmission->operator_profile_id);
        $this->assertSame($operationId, $completed->operation_id);

        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.basic-examination.completed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-completed')->count());
        $auditMetadata = json_decode((string) DB::table('audit_events')->where('action', 'operator.basic-examination.completed')->value('metadata'), true, flags: JSON_THROW_ON_ERROR);
        $outboxPayload = json_decode((string) DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-completed')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['member_id', 'booking_id', 'assessment_id', 'systolic_bp_value', 'weight_value', 'bmi_value', 'missing_reason'] as $key) {
            $this->assertArrayNotHasKey($key, $auditMetadata);
            $this->assertArrayNotHasKey($key, $outboxPayload);
        }

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('COMPLETE-1')
            ->assertSee('xray')
            ->assertSee('waiting')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId'])
            ->assertDontSee('120');
    }

    public function test_completion_requires_the_claimants_bound_vital_signs_execution(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('MISSING-EXECUTION');

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertConflict();
        $this->assertDatabaseHas('operator_queue_admissions', [
            'id' => $admission->id,
            'state' => 'in_service',
            'operator_profile_id' => $fixture['profileId'],
        ]);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
        $this->assertSame(0, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());

        $this->recordVitalSigns($admission->id);
        $other = $this->secondOperator($fixture);
        DB::table('operator_vital_signs_executions')
            ->where('operator_queue_admission_id', $admission->id)
            ->update(['operator_profile_id' => $other['profileId']]);
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertConflict();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service']);
        $this->assertSame(0, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
    }

    public function test_completion_requires_a_captured_paper_questionnaire_after_vital_signs(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('MISSING-QUESTIONNAIRE');
        $this->recordVitalSigns($admission->id);

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertConflict();

        $this->assertDatabaseHas('operator_queue_admissions', [
            'id' => $admission->id,
            'state' => 'in_service',
            'operator_profile_id' => $fixture['profileId'],
        ]);
        $this->assertSame(0, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
    }

    public function test_claimant_captures_the_completed_paper_questionnaire_as_a_private_photo(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('QUESTIONNAIRE-CAPTURE');
        $plain = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JgN0AAAAASUVORK5CYII=', true);
        $this->assertIsString($plain);

        $this->post(route('operator.basic-examination-worklist.questionnaire.store', $admission->id), [
            'operation_id' => (string) Str::uuid(),
            'questionnaire_completed' => '1',
            'photo' => UploadedFile::fake()->createWithContent('questionnaire.png', $plain),
        ])->assertRedirect(route('operator.basic-examination-worklist'));

        $questionnaire = DB::table('member_paper_questionnaires')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($questionnaire);
        $this->assertSame($fixture['memberId'], $questionnaire->member_id);
        $this->assertSame($fixture['scheduleId'], $questionnaire->member_schedule_id);
        $this->assertSame($fixture['siteReferenceId'], $questionnaire->examination_site_id);
        $this->assertSame($fixture['siteStableId'], $questionnaire->operator_site_id);
        $this->assertSame($fixture['profileId'], $questionnaire->operator_profile_id);
        $this->assertSame('V1', $questionnaire->form_version);
        $this->assertSame('image/png', $questionnaire->private_photo_format);
        $this->assertNotSame($plain, Storage::disk('local')->get($questionnaire->private_photo_object_key));
        $this->assertStringNotContainsString((string) $questionnaire->private_photo_object_key, json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($plain, json_encode(DB::table('outbox_messages')->get(), JSON_THROW_ON_ERROR));
    }

    public function test_invalid_questionnaire_photo_leaves_no_record_or_private_object(): void
    {
        [, $admission] = $this->inServiceFixture('QUESTIONNAIRE-INVALID');

        $this->post(route('operator.basic-examination-worklist.questionnaire.store', $admission->id), [
            'operation_id' => (string) Str::uuid(),
            'questionnaire_completed' => '1',
            'photo' => UploadedFile::fake()->createWithContent('not-a-photo.jpg', 'synthetic non-image'),
        ])->assertRedirect()->assertSessionHasErrors('questionnaire');

        $this->assertSame(0, DB::table('member_paper_questionnaires')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'member.paper-questionnaire.completed')->count());
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.paper-questionnaire-completed')->count());
    }

    public function test_questionnaire_replay_creates_one_private_record_and_object(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('QUESTIONNAIRE-REPLAY');
        $operationId = (string) Str::uuid();
        $plain = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JgN0AAAAASUVORK5CYII=', true);
        $this->assertIsString($plain);

        $this->post(route('operator.basic-examination-worklist.questionnaire.store', $admission->id), [
            'operation_id' => $operationId,
            'questionnaire_completed' => '1',
            'photo' => UploadedFile::fake()->createWithContent('questionnaire.png', $plain),
        ])->assertRedirect(route('operator.basic-examination-worklist'));
        $filesAfterFirstCapture = Storage::disk('local')->allFiles();

        $this->post(route('operator.basic-examination-worklist.questionnaire.store', $admission->id), [
            'operation_id' => $operationId,
            'questionnaire_completed' => '1',
            'photo' => UploadedFile::fake()->createWithContent('replayed-questionnaire.png', $plain),
        ])->assertRedirect(route('operator.basic-examination-worklist'));

        $this->assertSame(1, DB::table('member_paper_questionnaires')->where('booking_id', $fixture['bookingId'])->count());
        $this->assertSame($filesAfterFirstCapture, Storage::disk('local')->allFiles());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.paper-questionnaire.completed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'member.paper-questionnaire-completed')->count());
    }

    public function test_non_claimant_cannot_capture_a_questionnaire_photo(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('QUESTIONNAIRE-FORBIDDEN');
        $other = $this->secondOperator($fixture);
        $plain = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JgN0AAAAASUVORK5CYII=', true);
        $this->assertIsString($plain);

        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.basic-examination-worklist.questionnaire.store', $admission->id), [
            'operation_id' => (string) Str::uuid(),
            'questionnaire_completed' => '1',
            'photo' => UploadedFile::fake()->createWithContent('questionnaire.png', $plain),
        ])->assertForbidden();

        $this->assertSame(0, DB::table('member_paper_questionnaires')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_in_service_worklist_offers_vital_signs_and_questionnaire_independently(): void
    {
        [, $admission] = $this->inServiceFixture('INDEPENDENT-CAPTURE');

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertSee('Record vital signs')
            ->assertSee('Upload paper questionnaire')
            ->assertDontSee('Complete basic examination');
    }

    public function test_completion_replay_is_idempotent_and_changed_payload_conflicts(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('REPLAY-COMPLETE');
        $this->recordVitalSigns($admission->id);
        $this->recordQuestionnaire($fixture);
        $operationId = (string) Str::uuid();

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.complete', (string) Str::uuid()), ['operation_id' => $operationId])->assertConflict();

        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
        $this->assertSame(1, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.basic-examination.completed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-completed')->count());
        $this->assertSame('completed', DB::table('operator_queue_admissions')->where('id', $admission->id)->value('state'));
        $this->assertNotNull($fixture['memberId']);
    }

    public function test_competing_completion_cannot_duplicate_the_xray_admission(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('COMPETING-COMPLETE');
        $this->recordVitalSigns($admission->id);
        $this->recordQuestionnaire($fixture);

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])->assertConflict();

        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
        $this->assertSame(1, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
    }

    public function test_completion_denies_non_claimant_revoked_and_cross_site_contexts(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('DENY-COMPLETE');
        $this->recordVitalSigns($admission->id);
        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee('120')
            ->assertDontSee($fixture['memberId']);

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);

        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service', 'operator_profile_id' => $fixture['profileId']]);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
    }

    public function test_ticket_stage_constraint_allows_xray_and_released_claim_can_be_reused(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('CONSTRAINT-COMPLETE');
        $this->recordVitalSigns($admission->id);
        $this->recordQuestionnaire($fixture);
        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $xray = DB::table('operator_queue_admissions')->where('stage', 'xray')->first();

        try {
            DB::table('operator_queue_admissions')->insert([
                'id' => (string) Str::uuid(),
                'operator_paper_ticket_id' => $xray->operator_paper_ticket_id,
                'operator_site_id' => $xray->operator_site_id,
                'member_schedule_id' => $xray->member_schedule_id,
                'queue_class' => 'advance',
                'stage' => 'xray',
                'state' => 'waiting',
                'ready_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('A paper ticket may have only one admission for each stage.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'reused-claim-'.$fixture['memberId']),
        ]);
        $next = $this->operatorFixture(false);
        DB::table('bookings')->where('id', $next['bookingId'])->update(['status' => 'checked_in']);
        $this->assignCurrentOperatorToFixture($fixture, $next);
        $next['profileId'] = $fixture['profileId'];
        $nextAdmission = $this->admit($next, 'REUSED-CLAIM');
        $this->withSession(['operator.active_site_id' => $next['siteLocalId']]);
        $this->post(route('operator.basic-examination-worklist.claim', $nextAdmission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        $this->assertDatabaseHas('operator_queue_admissions', [
            'id' => $nextAdmission->id,
            'operator_profile_id' => $fixture['profileId'],
            'state' => 'waiting',
        ]);
    }

    public function test_audit_and_outbox_failures_roll_back_completion_and_xray_readiness(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('ROLLBACK-COMPLETE');
        $this->recordVitalSigns($admission->id);
        $this->recordQuestionnaire($fixture);
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic completion audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service', 'operator_profile_id' => $fixture['profileId']]);
        $this->assertSame(0, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);

        app()->instance(AuditStore::class, new DatabaseAuditStore);
        app()->forgetScopedInstances();
        $operationId = (string) Str::uuid();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic completion outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.complete', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service', 'operator_profile_id' => $fixture['profileId']]);
        $this->assertSame(0, DB::table('operator_queue_admissions')->where('stage', 'xray')->count());
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'completed')->count());
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
    }

    /** @return array{0: array<string, mixed>, 1: object} */
    private function inServiceFixture(string $ticketNumber): array
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'checked_in']);
        $admission = $this->admit($fixture, $ticketNumber);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.start', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        return [$fixture, $admission];
    }

    private function recordVitalSigns(string $admissionId): void
    {
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admissionId), $this->valuePayload((string) Str::uuid()))
            ->assertRedirect(route('operator.basic-examination-worklist'));
    }

    private function recordQuestionnaire(array $fixture): void
    {
        $now = now();
        DB::table('member_paper_questionnaires')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['memberId'],
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'examination_site_id' => $fixture['siteReferenceId'],
            'operator_site_id' => $fixture['siteStableId'],
            'operator_profile_id' => $fixture['profileId'],
            'completed_at' => $now,
            'form_version' => 'V1',
            'private_photo_object_key' => 'synthetic-questionnaire',
            'private_photo_checksum' => hash('sha256', 'synthetic-questionnaire'),
            'private_photo_bytes' => 1,
            'private_photo_format' => 'image/jpeg',
            'operation_id' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed> */
    private function valuePayload(string $operationId): array
    {
        return [
            'operation_id' => $operationId,
            'systolic_bp_value' => '120',
            'systolic_bp_missing_reason' => null,
            'diastolic_bp_value' => '80',
            'diastolic_bp_missing_reason' => null,
            'temperature_value' => '36.5',
            'temperature_missing_reason' => null,
            'height_value' => '180',
            'height_missing_reason' => null,
            'weight_value' => '75',
            'weight_missing_reason' => null,
            'bmi_missing_reason' => null,
        ];
    }

    private function admit(array $fixture, string $ticketNumber): object
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
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_queue_admission_history')->insert([
            'id' => (string) Str::uuid(),
            'operator_queue_admission_id' => $admissionId,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'admitted',
            'from_state' => null,
            'to_state' => 'waiting',
            'operation_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('operator_queue_admissions')->where('id', $admissionId)->first();
    }

    /** @return array{operator: User, profileId: string} */
    private function secondOperator(array $fixture): array
    {
        $now = now();
        $operator = User::factory()->create(['email' => 'operator-second-'.Str::lower(Str::random(8)).'@example.test']);
        $profileId = (string) Str::uuid();
        DB::table('operator_profiles')->insert([
            'id' => $profileId,
            'user_id' => $operator->id,
            'display_name' => 'Second Synthetic Operator',
            'employee_code' => 'OPR-'.substr($profileId, 0, 8),
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $profileId,
            'operator_site_id' => $fixture['siteLocalId'],
            'active' => true,
            'assigned_by_user_id' => $operator->id,
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $fixture['eligibleId'],
            'operator_profile_id' => $profileId,
            'assigned_by_user_id' => $operator->id,
            'status' => 'active',
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->grant($operator, false);

        return compact('operator', 'profileId');
    }

    private function assignCurrentOperatorToFixture(array $current, array $fixture): void
    {
        $now = now();
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $current['profileId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'active' => true,
            'assigned_by_user_id' => $current['operator']->id,
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $fixture['eligibleId'],
            'operator_profile_id' => $current['profileId'],
            'assigned_by_user_id' => $current['operator']->id,
            'status' => 'active',
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
