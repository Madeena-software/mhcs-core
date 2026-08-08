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

final class Mvp04gPrivateBasicExaminationCallTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claimant_calls_admission_and_preserves_claim_and_fifo_fields(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'CALL-1');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $claimed = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $operationId = (string) Str::uuid();

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertSee('CALL-1')
            ->assertSee('Call')
            ->assertSee('Claimed by you');
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => $operationId])
            ->assertRedirect(route('operator.basic-examination-worklist'));

        $called = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $this->assertSame($fixture['profileId'], $called->operator_profile_id);
        $this->assertSame((string) $claimed->claimed_at, (string) $called->claimed_at);
        $this->assertSame((string) $admission->operator_paper_ticket_id, (string) $called->operator_paper_ticket_id);
        $this->assertSame((string) $admission->operator_site_id, (string) $called->operator_site_id);
        $this->assertSame((string) $admission->member_schedule_id, (string) $called->member_schedule_id);
        $this->assertSame((string) $admission->ready_at, (string) $called->ready_at);
        $this->assertSame('advance', $called->queue_class);
        $this->assertSame('basic_examination', $called->stage);
        $this->assertSame('called', $called->state);
        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $admission->id,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'called',
            'from_state' => 'waiting',
            'to_state' => 'called',
            'operation_id' => $operationId,
        ]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.called')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-called')->count());
        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertDontSee('CALL-1')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_exact_replay_has_one_call_and_changed_payload_is_a_conflict(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'REPLAY-CALL');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $operationId = (string) Str::uuid();
        $payload = ['operation_id' => $operationId];

        $this->post(route('operator.basic-examination-worklist.call', $admission->id), $payload)->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), $payload)->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.call', Str::uuid()), $payload)->assertConflict();

        $this->assertSame(1, DB::table('operator_queue_admissions')->where('state', 'called')->count());
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.called')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-called')->count());
    }

    public function test_non_claimant_cannot_see_or_call_an_owned_admission(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'NONCLAIMANT-CALL');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertDontSee('NONCLAIMANT-CALL')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting', 'operator_profile_id' => $fixture['profileId']]);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_call_denies_revoked_shift_site_permission_account_and_forged_active_site(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'DENY-CALL');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => true]);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);

        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $fixture['operator']->forceFill(['account_status' => 'suspended']);
        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_stale_foreign_unclaimed_and_non_waiting_admissions_fail_closed(): void
    {
        $fixture = $this->readyFixture();
        $unclaimed = $this->admit($fixture, 'UNCLAIMED-CALL');
        $this->post(route('operator.basic-examination-worklist.call', $unclaimed->id), ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->post(route('operator.basic-examination-worklist.call', (string) Str::uuid()), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);

        $this->post(route('operator.basic-examination-worklist.claim', $unclaimed->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        DB::table('operator_queue_admissions')->where('id', $unclaimed->id)->update(['state' => 'in_service']);
        $this->post(route('operator.basic-examination-worklist.call', $unclaimed->id), ['operation_id' => (string) Str::uuid()])->assertConflict();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $unclaimed->id, 'operator_profile_id' => $fixture['profileId'], 'state' => 'in_service']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_audit_and_outbox_failures_roll_back_the_call(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'ROLLBACK-CALL');
        $this->post(route('operator.basic-examination-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic call audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting']);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'called']);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);

        app()->instance(AuditStore::class, new DatabaseAuditStore);
        app()->forgetScopedInstances();
        $operationId = (string) Str::uuid();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic call outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.call', $admission->id), ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting']);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'called']);
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
