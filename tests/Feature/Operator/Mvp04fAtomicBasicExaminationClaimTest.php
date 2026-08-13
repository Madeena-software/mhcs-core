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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04fAtomicBasicExaminationClaimTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claim_is_atomic_private_and_preserves_waiting_stage_and_fifo_fields(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'CLAIM-1');
        $operationId = (string) Str::uuid();

        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), [
            'operation_id' => $operationId,
        ])->assertRedirect(route('operator.basic-examination-worklist'));

        $claimed = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $this->assertSame($fixture['profileId'], $claimed->operator_profile_id);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertSame('basic_examination', $claimed->stage);
        $this->assertSame('waiting', $claimed->state);
        $this->assertSame((string) $admission->ready_at, (string) $claimed->ready_at);
        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $admission->id,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'claimed',
            'from_state' => 'waiting',
            'to_state' => 'waiting',
            'operation_id' => $operationId,
        ]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.claimed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-claimed')->count());

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertSee('CLAIM-1')
            ->assertSee('Diklaim oleh Anda')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_exact_replay_has_one_claim_and_changed_payload_is_a_conflict(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'REPLAY-1');
        $operationId = (string) Str::uuid();
        $payload = ['operation_id' => $operationId];

        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), $payload)->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), $payload)->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.claim', Str::uuid()), $payload)
            ->assertRedirect(route('operator.basic-examination-worklist'))
            ->assertSessionHasErrors(['queue' => __('The queue admission could not be claimed.')]);

        $this->assertSame(1, DB::table('operator_queue_admissions')->whereNotNull('operator_profile_id')->count());
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.claimed')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-claimed')->count());
    }

    public function test_competing_operator_cannot_see_or_claim_an_owned_admission(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'COMPETE-1');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertDontSee('COMPETE-1')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_one_operator_cannot_claim_a_second_admission(): void
    {
        $fixture = $this->readyFixture();
        $first = $this->admit($fixture, 'ONE-1');
        $second = $this->insertWaitingAdmission($fixture, $first, 'ONE-2');

        $this->post(route('operator.basic-examination-worklist.claim', $first->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $response = $this->post(route('operator.basic-examination-worklist.claim', $second->id), ['operation_id' => (string) Str::uuid()]);
        $response->assertRedirect(route('operator.basic-examination-worklist'));
        $this->assertSame(
            __('This Operator already has another queue admission in progress.'),
            $response->getSession()->get('status'),
        );

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $first->id, 'operator_profile_id' => $fixture['profileId']]);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $second->id, 'operator_profile_id' => null]);
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_awaiting_ai_xray_does_not_block_a_later_basic_claim(): void
    {
        $fixture = $this->readyFixture();
        $awaiting = $this->admit($fixture, 'AWAITING-AI');
        DB::table('operator_queue_admissions')->where('id', $awaiting->id)->update(['stage' => 'xray', 'state' => 'awaiting_ai']);
        $later = $this->insertWaitingAdmission($fixture, $awaiting, 'BASIC-AFTER-AI');

        $this->post(route('operator.basic-examination-worklist.claim', $later->id), ['operation_id' => (string) Str::uuid()])
            ->assertRedirect(route('operator.basic-examination-worklist'));
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $later->id, 'operator_profile_id' => $fixture['profileId']]);
    }

    public function test_claim_denies_revoked_shift_site_permission_account_and_active_site(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'DENY-1');

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => true]);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);

        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $fixture['operator']->forceFill(['account_status' => 'suspended']);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null]);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_stale_or_foreign_and_non_waiting_admissions_fail_closed(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'STALE-1');

        $this->post(route('operator.basic-examination-worklist.claim', (string) Str::uuid()), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);

        DB::table('operator_queue_admissions')->where('id', $admission->id)->update(['state' => 'called']);
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertRedirect(route('operator.basic-examination-worklist'))
            ->assertSessionHasErrors(['queue' => __('The queue admission could not be claimed.')]);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null, 'state' => 'called']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'claimed')->count());
    }

    public function test_audit_and_outbox_failures_roll_back_the_claim(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'ROLLBACK-AUDIT');
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic claim audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => $operationId])->assertRedirect();
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
                throw new RuntimeException('synthetic claim outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'operator_profile_id' => null]);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'claimed']);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
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

    /** @return array<string, mixed> */
    private function readyFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        return $fixture;
    }

    private function insertWaitingAdmission(array $fixture, object $first, string $ticketNumber): object
    {
        $bookingId = (string) Str::uuid();
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = $bookingId;
        DB::table('bookings')->insert($booking);
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $bookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => $ticketNumber,
            'issued_at' => $first->ready_at,
            'created_at' => $first->ready_at,
            'updated_at' => $first->ready_at,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => $first->ready_at,
            'created_at' => $first->ready_at,
            'updated_at' => $first->ready_at,
        ]);
        DB::table('operator_queue_admission_history')->insert([
            'id' => (string) Str::uuid(),
            'operator_queue_admission_id' => $admissionId,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'admitted',
            'from_state' => null,
            'to_state' => 'waiting',
            'operation_id' => (string) Str::uuid(),
            'occurred_at' => $first->ready_at,
            'created_at' => $first->ready_at,
            'updated_at' => $first->ready_at,
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
}
