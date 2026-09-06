<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorClaimAndIdempotencyInstantsPortabilityTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_operator_claim_and_idempotency_instants_support_post_2038_timestamps_and_preserve_nullability(): void
    {
        $fixture = $this->operatorFixture(false);
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TST-001',
            'issued_at' => '2040-01-10 03:30:00',
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 03:30:00',
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => '2040-01-10 03:30:00',
            'operator_profile_id' => null,
            'claimed_at' => null,
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 03:30:00',
        ]);

        DB::table('idempotent_consumptions')->insert([
            'message_id' => 'msg-null',
            'consumer' => 'test-consumer',
            'payload_hash' => hash('sha256', 'payload'),
            'status' => 'pending',
            'handled_at' => null,
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 03:30:00',
        ]);

        $admission = DB::table('operator_queue_admissions')->where('id', $admissionId)->first();
        $this->assertNotNull($admission);
        $this->assertNull($admission->claimed_at);

        $consumption = DB::table('idempotent_consumptions')->where('message_id', 'msg-null')->first();
        $this->assertNotNull($consumption);
        $this->assertNull($consumption->handled_at);

        DB::table('operator_queue_admissions')->where('id', $admissionId)->update([
            'claimed_at' => '2040-01-10 04:00:00',
            'operator_profile_id' => $fixture['profileId'],
        ]);

        DB::table('idempotent_consumptions')->insert([
            'message_id' => 'msg-2040',
            'consumer' => 'test-consumer-2',
            'payload_hash' => hash('sha256', 'payload-2'),
            'status' => 'completed',
            'handled_at' => '2040-01-10 04:00:00',
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 04:00:00',
        ]);

        $admissionUpdated = DB::table('operator_queue_admissions')->where('id', $admissionId)->first();
        $this->assertSame('2040-01-10 04:00:00', (string) $admissionUpdated->claimed_at);

        $consumption2040 = DB::table('idempotent_consumptions')->where('message_id', 'msg-2040')->first();
        $this->assertSame('2040-01-10 04:00:00', (string) $consumption2040->handled_at);
        $this->assertSame('2040-01-10 03:30:00', (string) $consumption2040->created_at);
        $this->assertSame('2040-01-10 04:00:00', (string) $consumption2040->updated_at);
    }

    public function test_claim_and_idempotency_flow_accepts_post_2038_application_clock(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2040-01-10T03:30:00+00:00'));
        $this->app->instance(Clock::class, $clock);

        $fixture = $this->operatorFixture(false);
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'LCD-001',
            'issued_at' => '2040-01-10 03:30:00',
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 03:30:00',
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => '2040-01-10 03:30:00',
            'operator_profile_id' => null,
            'claimed_at' => null,
            'created_at' => '2040-01-10 03:30:00',
            'updated_at' => '2040-01-10 03:30:00',
        ]);

        $idempotency = app(IdempotencyStore::class);
        $operationId = (string) Str::uuid();
        $outcome = $idempotency->run(
            $operationId,
            'operator.queue-admission.claim',
            ['admission_id' => $admissionId],
            function () use ($admissionId, $fixture, $clock): array {
                $now = $clock->now();
                DB::table('operator_queue_admissions')
                    ->where('id', $admissionId)
                    ->update([
                        'operator_profile_id' => $fixture['profileId'],
                        'claimed_at' => $now,
                        'updated_at' => $now,
                    ]);

                return ['status' => 'claimed'];
            }
        );

        $this->assertSame('handled', (string) $outcome->status);

        $admission = DB::table('operator_queue_admissions')->where('id', $admissionId)->first();
        $this->assertNotNull($admission);
        $this->assertSame((string) $fixture['profileId'], (string) $admission->operator_profile_id);
        $this->assertSame('2040-01-10 03:30:00', (string) $admission->claimed_at);

        $consumption = DB::table('idempotent_consumptions')->where('message_id', $operationId)->first();
        $this->assertNotNull($consumption);
        $this->assertSame('handled', $consumption->status);
        $this->assertSame('2040-01-10 03:30:00', (string) $consumption->handled_at);
        $this->assertSame('2040-01-10 03:30:00', (string) $consumption->created_at);
        $this->assertSame('2040-01-10 03:30:00', (string) $consumption->updated_at);
    }

    public function test_migration_rollback_safety_guard_rejects_post_2038_admission_values(): void
    {
        $fixture = $this->operatorFixture(false);
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TST-RB-1',
            'issued_at' => '2026-05-10 00:00:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => '2026-05-10 00:00:00',
            'operator_profile_id' => $fixture['profileId'],
            'claimed_at' => '2040-01-10 03:30:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000002_make_operator_claim_and_idempotency_instants_mysql_portable.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back operator claim and idempotency instants while values exceed the MySQL TIMESTAMP range.');

        $migration->down();
    }

    public function test_migration_rollback_safety_guard_rejects_post_2038_idempotency_values(): void
    {
        DB::table('idempotent_consumptions')->insert([
            'message_id' => 'msg-rb-2040',
            'consumer' => 'test-consumer',
            'payload_hash' => hash('sha256', 'payload'),
            'status' => 'completed',
            'handled_at' => '2040-01-10 03:30:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000002_make_operator_claim_and_idempotency_instants_mysql_portable.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back operator claim and idempotency instants while values exceed the MySQL TIMESTAMP range.');

        $migration->down();
    }

    public function test_migration_rollback_and_reapply_succeeds_when_values_in_range(): void
    {
        $fixture = $this->operatorFixture(false);
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TST-RB-OK',
            'issued_at' => '2026-05-10 00:00:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'basic_examination',
            'state' => 'waiting',
            'ready_at' => '2026-05-10 00:00:00',
            'operator_profile_id' => $fixture['profileId'],
            'claimed_at' => '2026-05-10 01:00:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        DB::table('idempotent_consumptions')->insert([
            'message_id' => 'msg-rb-ok',
            'consumer' => 'test-consumer',
            'payload_hash' => hash('sha256', 'payload'),
            'status' => 'completed',
            'handled_at' => '2026-05-10 01:00:00',
            'created_at' => '2026-05-10 00:00:00',
            'updated_at' => '2026-05-10 00:00:00',
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000002_make_operator_claim_and_idempotency_instants_mysql_portable.php');

        $migration->down();
        $this->assertTrue(Schema::hasColumn('operator_queue_admissions', 'claimed_at'));
        $this->assertTrue(Schema::hasColumn('idempotent_consumptions', 'handled_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('operator_queue_admissions', 'claimed_at'));
        $this->assertTrue(Schema::hasColumn('idempotent_consumptions', 'handled_at'));
    }
}
