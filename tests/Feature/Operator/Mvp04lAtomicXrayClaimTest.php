<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04lAtomicXrayClaimTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claim_is_atomic_private_and_preserves_xray_waiting_and_fifo_fields(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertAdmission($fixture, 'XRAY-CLAIM-1', 'xray');
        $operationId = (string) Str::uuid();

        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), [
            'operation_id' => $operationId,
        ])->assertRedirect(route('operator.xray-readiness-worklist'));

        $claimed = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $this->assertSame($fixture['profileId'], $claimed->operator_profile_id);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertSame('xray', $claimed->stage);
        $this->assertSame('waiting', $claimed->state);
        $this->assertSame((string) $admission->operator_paper_ticket_id, (string) $claimed->operator_paper_ticket_id);
        $this->assertSame((string) $admission->operator_site_id, (string) $claimed->operator_site_id);
        $this->assertSame((string) $admission->member_schedule_id, (string) $claimed->member_schedule_id);
        $this->assertSame((string) $admission->ready_at, (string) $claimed->ready_at);
        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $admission->id,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'claimed',
            'from_state' => 'waiting',
            'to_state' => 'waiting',
            'operation_id' => $operationId,
        ]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray.claimed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.xray-claimed')->count());
        $this->assertDatabaseHas('idempotent_consumptions', [
            'message_id' => $operationId,
            'consumer' => 'operator.xray.claim',
            'status' => 'handled',
        ]);

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('XRAY-CLAIM-1')
            ->assertSee('Diklaim oleh Anda')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_exact_replay_is_safe_and_changed_payload_conflicts(): void
    {
        $fixture = $this->readyFixture();
        $first = $this->insertAdmission($fixture, 'XRAY-REPLAY-1', 'xray');
        $second = $this->insertAdmission($fixture, 'XRAY-REPLAY-2', 'xray', $this->copyBooking($fixture));
        $operationId = (string) Str::uuid();
        $payload = ['operation_id' => $operationId];

        $this->post(route('operator.xray-readiness-worklist.claim', $first->id), $payload)->assertRedirect();
        $this->post(route('operator.xray-readiness-worklist.claim', $first->id), $payload)->assertRedirect();
        $this->post(route('operator.xray-readiness-worklist.claim', $second->id), $payload)->assertConflict();

        $this->assertSame(1, DB::table('operator_queue_admissions')->whereNotNull('operator_profile_id')->count());
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray.claimed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.xray-claimed')->count());
    }

    public function test_competing_operator_cannot_see_or_claim_an_owned_admission(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertAdmission($fixture, 'XRAY-COMPETE-1', 'xray');
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertDontSee('XRAY-COMPETE-1')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_global_live_claim_constraint_blocks_cross_stage_claims(): void
    {
        $fixture = $this->readyFixture();
        $xray = $this->insertAdmission($fixture, 'XRAY-LIVE-1', 'xray');
        $basic = $this->insertAdmission($fixture, 'BASIC-LIVE-1', 'basic_examination', $this->copyBooking($fixture));

        $this->post(route('operator.xray-readiness-worklist.claim', $xray->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.claim', $basic->id), ['operation_id' => (string) Str::uuid()])->assertConflict();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $xray->id, 'operator_profile_id' => $fixture['profileId'], 'state' => 'waiting']);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $basic->id, 'operator_profile_id' => null, 'state' => 'waiting']);
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_existing_basic_claim_redirects_when_claiming_xray(): void
    {
        $fixture = $this->readyFixture();
        $xray = $this->insertAdmission($fixture, 'XRAY-BUSY-1', 'xray');
        $basic = $this->insertAdmission($fixture, 'BASIC-BUSY-1', 'basic_examination', $this->copyBooking($fixture));

        $this->post(route('operator.basic-examination-worklist.claim', $basic->id), ['operation_id' => (string) Str::uuid()])
            ->assertRedirect();

        $this->post(route('operator.xray-readiness-worklist.claim', $xray->id), ['operation_id' => (string) Str::uuid()])
            ->assertRedirect(route('operator.xray-readiness-worklist'))
            ->assertSessionHas('status', 'Operator ini masih memiliki tiket antrean lain yang sedang ditangani.');

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $basic->id, 'operator_profile_id' => $fixture['profileId']]);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $xray->id, 'operator_profile_id' => null]);
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_revoked_and_foreign_scope_denials_are_private_and_atomic(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertAdmission($fixture, 'XRAY-DENY-1', 'xray');

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => true]);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);

        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $foreignScheduleId = (string) Str::uuid();
        $foreignSchedule = (array) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->first();
        $foreignSchedule['id'] = $foreignScheduleId;
        DB::table('shift_schedules')->insert($foreignSchedule);
        $foreignFixture = [...$fixture, 'scheduleId' => $foreignScheduleId, 'bookingId' => $this->copyBooking($fixture, $foreignScheduleId)];
        $foreignAdmission = $this->insertAdmission($foreignFixture, 'XRAY-FOREIGN-1', 'xray');
        $this->post(route('operator.xray-readiness-worklist.claim', $foreignAdmission->id), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($foreignFixture['memberId'])
            ->assertDontSee($foreignFixture['bookingId']);

        DB::table('operator_queue_admissions')->where('id', $admission->id)->update(['state' => 'called']);
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertConflict();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null, 'state' => 'called']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_audit_and_outbox_failures_roll_back_the_claim(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->insertAdmission($fixture, 'XRAY-ROLLBACK-1', 'xray');
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic X-ray claim audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null]);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'claimed']);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);

        app()->instance(AuditStore::class, new DatabaseAuditStore);
        app()->forgetScopedInstances();
        $operationId = (string) Str::uuid();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic X-ray claim outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null]);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'claimed']);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
    }

    public function test_xray_worklist_remains_fifo_and_contains_no_member_or_clinical_data(): void
    {
        $fixture = $this->readyFixture();
        $first = $this->insertAdmission($fixture, 'FIFO-XRAY-1', 'xray', null, '2040-01-10 03:01:00');
        $second = $this->insertAdmission($fixture, 'FIFO-XRAY-2', 'xray', $this->copyBooking($fixture), '2040-01-10 03:02:00');

        $response = $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSeeInOrder(['FIFO-XRAY-1', 'FIFO-XRAY-2'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId'])
            ->assertDontSee('clinical')
            ->assertDontSee('medical_record_number');

        $this->assertStringContainsString('/operator/xray-readiness-worklist/'.$first->id.'/claim', $response->getContent());
        $this->assertStringContainsString('/operator/xray-readiness-worklist/'.$second->id.'/claim', $response->getContent());
    }

    /** @return array<string, mixed> */
    private function readyFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        return $fixture;
    }

    private function insertAdmission(array $fixture, string $ticketNumber, string $stage, ?string $bookingId = null, ?string $readyAt = null): object
    {
        $now = $readyAt === null ? now() : Carbon::parse($readyAt);
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();
        $bookingId ??= (string) $fixture['bookingId'];
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $bookingId,
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
            'stage' => $stage,
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

    private function copyBooking(array $fixture, ?string $scheduleId = null): string
    {
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = (string) Str::uuid();
        if ($scheduleId !== null) {
            $booking['shift_schedule_id'] = $scheduleId;
        }
        DB::table('bookings')->insert($booking);

        return (string) $booking['id'];
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
}
