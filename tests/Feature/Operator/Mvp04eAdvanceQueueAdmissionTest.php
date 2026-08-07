<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorWorklistService;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04eAdvanceQueueAdmissionTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_successful_ticket_issue_creates_one_private_advance_waiting_admission_and_history(): void
    {
        $fixture = $this->readyFixture();
        $this->freezeIssueTime();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'ADV-17',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $ticket = DB::table('operator_paper_tickets')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($ticket);
        $admission = DB::table('operator_queue_admissions')->where('operator_paper_ticket_id', $ticket->id)->first();
        $this->assertNotNull($admission);
        $this->assertSame($fixture['siteLocalId'], $admission->operator_site_id);
        $this->assertSame($fixture['scheduleId'], $admission->member_schedule_id);
        $this->assertSame('advance', $admission->queue_class);
        $this->assertSame('basic_examination', $admission->stage);
        $this->assertSame('waiting', $admission->state);
        $this->assertSame((string) $ticket->issued_at, (string) $admission->ready_at);

        $this->assertDatabaseCount('operator_queue_admissions', 1);
        $this->assertDatabaseCount('operator_queue_admission_history', 1);
        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $admission->id,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'admitted',
            'to_state' => 'waiting',
        ]);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.created')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-created')->count());

        $this->get(route('operator.basic-examination-worklist'))
            ->assertOk()
            ->assertSee('ADV-17')
            ->assertSee('Synthetic Operator Site')
            ->assertSee('basic_examination')
            ->assertSee('waiting')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId'])
            ->assertDontSee('Synthetic Arrival Member')
            ->assertDontSee('MRN-');
    }

    public function test_replays_and_competing_attempts_do_not_duplicate_admission_history_or_evidence(): void
    {
        $fixture = $this->readyFixture();
        $operationId = (string) Str::uuid();
        $payload = ['ticket_number' => 'ADV-1', 'operation_id' => $operationId];

        $this->post(route('operator.check-in.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'ADV-2',
            'operation_id' => $operationId,
        ])->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'ADV-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseCount('operator_paper_tickets', 1);
        $this->assertDatabaseCount('operator_queue_admissions', 1);
        $this->assertDatabaseCount('operator_queue_admission_history', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.queue-admission.created')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.queue-admission-created')->count());
    }

    public function test_worklist_is_fifo_and_uses_admission_id_as_stable_tie_breaker(): void
    {
        $fixture = $this->readyFixture();
        $this->freezeIssueTime();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'FIRST',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $firstAdmission = DB::table('operator_queue_admissions')->first();
        $secondBookingId = (string) Str::uuid();
        $secondTicketId = (string) Str::uuid();
        $secondAdmissionId = (string) Str::uuid();
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = $secondBookingId;
        DB::table('bookings')->insert($booking);
        DB::table('operator_paper_tickets')->insert([
            'id' => $secondTicketId,
            'booking_id' => $secondBookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'SECOND',
            'issued_at' => $firstAdmission->ready_at,
            'created_at' => $firstAdmission->ready_at,
            'updated_at' => $firstAdmission->ready_at,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $secondAdmissionId,
            'operator_paper_ticket_id' => $secondTicketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => $firstAdmission->ready_at,
            'created_at' => $firstAdmission->ready_at,
            'updated_at' => $firstAdmission->ready_at,
        ]);
        DB::table('operator_queue_admission_history')->insert([
            'id' => (string) Str::uuid(),
            'operator_queue_admission_id' => $secondAdmissionId,
            'operator_profile_id' => $fixture['profileId'],
            'event_type' => 'admitted',
            'from_state' => null,
            'to_state' => 'waiting',
            'operation_id' => (string) Str::uuid(),
            'occurred_at' => $firstAdmission->ready_at,
            'created_at' => $firstAdmission->ready_at,
            'updated_at' => $firstAdmission->ready_at,
        ]);

        $expected = DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->orderBy('admissions.ready_at')
            ->orderBy('admissions.id')
            ->pluck('tickets.ticket_number')
            ->all();
        $entries = app(OperatorWorklistService::class)->basicExamination();

        $this->assertSame($expected, array_column($entries, 'ticket_number'));
        $this->assertCount(2, $entries);
        $this->assertSame(['ticket_number', 'site_name', 'schedule_starts_at', 'schedule_ends_at', 'stage', 'state', 'ready_at'], array_keys($entries[0]));
    }

    public function test_queue_audit_failure_rolls_back_check_in_ticket_queue_history_and_idempotency(): void
    {
        $fixture = $this->readyFixture();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            private int $calls = 0;

            public function append(AuditEvent $event): void
            {
                $this->calls++;
                if ($this->calls === 3) {
                    throw new RuntimeException('synthetic queue audit failure');
                }
            }
        });
        app()->forgetScopedInstances();

        $operationId = (string) Str::uuid();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'FAIL-QUEUE-AUDIT',
            'operation_id' => $operationId,
        ])->assertRedirect();

        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_paper_tickets', 0);
        $this->assertDatabaseCount('operator_queue_admissions', 0);
        $this->assertDatabaseCount('operator_queue_admission_history', 0);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
    }

    public function test_queue_outbox_failure_rolls_back_check_in_ticket_queue_history_and_idempotency(): void
    {
        $fixture = $this->readyFixture();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            private int $calls = 0;

            public function record(DomainEvent $event): void
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new RuntimeException('synthetic queue outbox failure');
                }
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $operationId = (string) Str::uuid();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'FAIL-QUEUE-OUTBOX',
            'operation_id' => $operationId,
        ])->assertRedirect();

        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_paper_tickets', 0);
        $this->assertDatabaseCount('operator_queue_admissions', 0);
        $this->assertDatabaseCount('operator_queue_admission_history', 0);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
    }

    public function test_worklist_rechecks_account_portal_site_and_shift_scope_without_leaking_rows(): void
    {
        $fixture = $this->readyFixture();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'SCOPED',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->get(route('operator.basic-examination-worklist'))->assertOk()->assertDontSee('SCOPED');

        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->get(route('operator.basic-examination-worklist'))->assertForbidden();

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => true]);
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $this->get(route('operator.basic-examination-worklist'))->assertForbidden();

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);
        $this->withSession(['operator.active_site_id' => (string) Str::uuid()]);
        $this->get(route('operator.basic-examination-worklist'))->assertForbidden();

        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $this->get(route('operator.basic-examination-worklist'))->assertForbidden();
    }

    private function freezeIssueTime(): void
    {
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2040-01-10T03:10:00+00:00')));
    }
}
