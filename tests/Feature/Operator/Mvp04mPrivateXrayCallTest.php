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

final class Mvp04mPrivateXrayCallTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claimant_calls_xray_admission_and_retains_claim_fifo_and_private_visibility(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'XRAY-CALL-1');
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $claimed = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $operationId = (string) Str::uuid();

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('XRAY-CALL-1')
            ->assertSee('Panggil')
            ->assertSee('Diklaim oleh Anda');
        $this->callXray($admission->id, ['operation_id' => $operationId])
            ->assertRedirect(route('operator.xray-readiness-worklist'));

        $called = DB::table('operator_queue_admissions')->where('id', $admission->id)->first();
        $this->assertSame($fixture['profileId'], $called->operator_profile_id);
        $this->assertSame((string) $claimed->claimed_at, (string) $called->claimed_at);
        $this->assertSame((string) $admission->operator_paper_ticket_id, (string) $called->operator_paper_ticket_id);
        $this->assertSame((string) $admission->operator_site_id, (string) $called->operator_site_id);
        $this->assertSame((string) $admission->member_schedule_id, (string) $called->member_schedule_id);
        $this->assertSame((string) $admission->ready_at, (string) $called->ready_at);
        $this->assertSame('advance', $called->queue_class);
        $this->assertSame('xray', $called->stage);
        $this->assertSame('called', $called->state);
        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $admission->id,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'called',
            'from_state' => 'waiting',
            'to_state' => 'called',
            'operation_id' => $operationId,
        ]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray.called')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.xray-called')->count());
        $this->assertDatabaseHas('idempotent_consumptions', [
            'message_id' => $operationId,
            'consumer' => 'operator.xray.call',
            'status' => 'handled',
        ]);
        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertSee('XRAY-CALL-1')
            ->assertSee('Dipanggil')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
    }

    public function test_exact_replay_has_one_xray_call_and_changed_payload_is_a_conflict(): void
    {
        $fixture = $this->readyFixture();
        $first = $this->admit($fixture, 'XRAY-REPLAY-1');
        $second = $this->admit($fixture, 'XRAY-REPLAY-2', $this->copyBooking($fixture));
        $this->post(route('operator.xray-readiness-worklist.claim', $first->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $operationId = (string) Str::uuid();
        $payload = ['operation_id' => $operationId];

        $this->callXray($first->id, $payload)->assertRedirect();
        $this->callXray($first->id, $payload)->assertRedirect();
        $this->callXray($second->id, $payload)->assertConflict();

        $this->assertSame(1, DB::table('operator_queue_admissions')->where('state', 'called')->count());
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray.called')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.xray-called')->count());
    }

    public function test_non_claimant_cannot_see_or_call_a_claimants_called_xray_admission(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'XRAY-NONCLAIMANT-CALL');
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.xray-readiness-worklist'))
            ->assertOk()
            ->assertDontSee('XRAY-NONCLAIMANT-CALL')
            ->assertDontSee($fixture['profileId'])
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'called', 'operator_profile_id' => $fixture['profileId']]);
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_call_denies_revoked_shift_site_permission_account_and_forged_active_site(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'XRAY-DENY-CALL');
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => true]);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);

        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $fixture['operator']->forceFill(['account_status' => 'suspended']);
        $this->callXray($admission->id, ['operation_id' => (string) Str::uuid()])->assertRedirect(route('login'));

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_unclaimed_foreign_cross_shift_cross_site_and_non_waiting_calls_fail_closed(): void
    {
        $fixture = $this->readyFixture();
        $unclaimed = $this->admit($fixture, 'XRAY-UNCLAIMED-CALL');
        $this->callXray($unclaimed->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->callXray((string) Str::uuid(), ['operation_id' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);

        $this->post(route('operator.xray-readiness-worklist.claim', $unclaimed->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        DB::table('operator_queue_admissions')->where('id', $unclaimed->id)->update(['state' => 'in_service']);
        $this->callXray($unclaimed->id, ['operation_id' => (string) Str::uuid()])->assertConflict();
        DB::table('operator_queue_admissions')->where('id', $unclaimed->id)->update(['operator_profile_id' => null, 'claimed_at' => null]);

        $foreignScheduleId = (string) Str::uuid();
        $foreignSchedule = (array) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->first();
        $foreignSchedule['id'] = $foreignScheduleId;
        $foreignSchedule['display_reference'] = 'JAD-'.Str::upper(Str::random(8));
        DB::table('shift_schedules')->insert($foreignSchedule);
        $foreign = $this->admit([...$fixture, 'scheduleId' => $foreignScheduleId, 'bookingId' => $this->copyBooking($fixture, $foreignScheduleId)], 'XRAY-CROSS-SHIFT');
        DB::table('operator_queue_admissions')->where('id', $foreign->id)->update(['operator_profile_id' => $fixture['profileId'], 'claimed_at' => now()]);
        $this->callXray($foreign->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();
        DB::table('operator_queue_admissions')->where('id', $foreign->id)->update(['operator_profile_id' => null, 'claimed_at' => null]);

        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'xray-cross-site-'.$fixture['memberId']),
        ]);
        $crossSiteFixture = $this->operatorFixture(false);
        $crossSite = $this->admit($crossSiteFixture, 'XRAY-CROSS-SITE');
        DB::table('operator_queue_admissions')->where('id', $crossSite->id)->update(['operator_profile_id' => $fixture['profileId'], 'claimed_at' => now()]);
        $this->callXray($crossSite->id, ['operation_id' => (string) Str::uuid()])->assertForbidden();

        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $unclaimed->id, 'operator_profile_id' => null, 'state' => 'in_service']);
        $this->assertSame(0, DB::table('operator_queue_admission_history')->where('event_type', 'called')->count());
    }

    public function test_audit_and_outbox_failures_roll_back_the_xray_call(): void
    {
        $fixture = $this->readyFixture();
        $admission = $this->admit($fixture, 'XRAY-ROLLBACK-CALL');
        $this->post(route('operator.xray-readiness-worklist.claim', $admission->id), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic X-ray call audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->callXray($admission->id, ['operation_id' => $operationId])->assertRedirect();
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
                throw new RuntimeException('synthetic X-ray call outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->callXray($admission->id, ['operation_id' => $operationId])->assertRedirect();
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'waiting']);
        $this->assertDatabaseMissing('operator_queue_admission_history', ['event_type' => 'called']);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
    }

    private function callXray(string $admissionId, array $payload)
    {
        return $this->post(route('operator.xray-readiness-worklist.call', $admissionId), $payload);
    }

    private function admit(array $fixture, string $ticketNumber, ?string $bookingId = null): object
    {
        $now = now();
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
            'stage' => 'xray',
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
