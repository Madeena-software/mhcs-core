<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\Member\Application\Contracts\OperatorVitalSignsContract;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Events\DomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04jPrivateVitalSignsCaptureTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_claimant_records_values_with_fixed_units_and_server_calculated_bmi(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-1');
        $this->get(route('operator.basic-examination-worklist.vital-signs', $admission->id))
            ->assertOk()
            ->assertSee('mmHg')
            ->assertSee('kg/m²')
            ->assertSee('Screening result; not a diagnosis')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);

        $operationId = (string) Str::uuid();
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload($operationId))
            ->assertRedirect(route('operator.basic-examination-worklist'))
            ->assertSessionHas('status', 'Vital signs recorded.');

        $assessment = DB::table('member_vital_signs_assessments')->where('booking_id', $fixture['bookingId'])->first();
        $execution = DB::table('operator_vital_signs_executions')->where('operator_queue_admission_id', $admission->id)->first();
        $this->assertNotNull($assessment);
        $this->assertNotNull($execution);
        $this->assertSame($fixture['memberId'], $assessment->member_id);
        $this->assertSame($fixture['bookingId'], $assessment->booking_id);
        $this->assertSame('120', $this->canonicalDecimal($assessment->systolic_bp_value));
        $this->assertSame('80', $this->canonicalDecimal($assessment->diastolic_bp_value));
        $this->assertSame('23.15', $this->canonicalDecimal($assessment->bmi_value));
        $this->assertSame('mmHg', $assessment->systolic_bp_unit);
        $this->assertSame('°C', $assessment->temperature_unit);
        $this->assertSame('cm', $assessment->height_unit);
        $this->assertSame('kg', $assessment->weight_unit);
        $this->assertSame('kg/m²', $assessment->bmi_unit);
        $this->assertSame($fixture['profileId'], $execution->operator_profile_id);
        $this->assertSame($fixture['siteLocalId'], $execution->operator_site_id);
        $this->assertSame($admission->id, $execution->operator_queue_admission_id);
        $this->assertSame($operationId, $execution->operation_id);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service']);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.basic-examination.vital-signs-recorded')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-vital-signs-recorded')->count());

        $auditMetadata = json_decode((string) DB::table('audit_events')->where('action', 'operator.basic-examination.vital-signs-recorded')->value('metadata'), true, flags: JSON_THROW_ON_ERROR);
        $outboxPayload = json_decode((string) DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-vital-signs-recorded')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('systolic_bp_value', $auditMetadata);
        $this->assertArrayNotHasKey('temperature_value', $auditMetadata);
        $this->assertArrayNotHasKey('weight_value', $auditMetadata);
        $this->assertArrayNotHasKey('systolic_bp_value', $outboxPayload);
        $this->assertArrayNotHasKey('bmi_value', $outboxPayload);
    }

    public function test_allowed_missing_reasons_are_stored_and_bmi_requires_its_own_reason(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-MISSING');
        $payload = $this->valuePayload((string) Str::uuid());
        $payload['systolic_bp_value'] = null;
        $payload['systolic_bp_missing_reason'] = 'unavailable';
        $payload['diastolic_bp_value'] = null;
        $payload['diastolic_bp_missing_reason'] = 'refused';
        $payload['temperature_value'] = null;
        $payload['temperature_missing_reason'] = 'not_applicable';
        $payload['weight_value'] = null;
        $payload['weight_missing_reason'] = 'unavailable';
        $payload['bmi_missing_reason'] = 'unavailable';

        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $payload)
            ->assertRedirect(route('operator.basic-examination-worklist'));

        $assessment = DB::table('member_vital_signs_assessments')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNull($assessment->systolic_bp_value);
        $this->assertSame('unavailable', $assessment->systolic_bp_missing_reason);
        $this->assertSame('refused', $assessment->diastolic_bp_missing_reason);
        $this->assertSame('not_applicable', $assessment->temperature_missing_reason);
        $this->assertSame('unavailable', $assessment->weight_missing_reason);
        $this->assertNull($assessment->bmi_value);
        $this->assertSame('unavailable', $assessment->bmi_missing_reason);
    }

    public function test_mixed_value_and_missing_reason_is_rejected_without_persistence(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-INVALID');
        $payload = $this->valuePayload((string) Str::uuid());
        $payload['temperature_missing_reason'] = 'refused';

        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('temperature_value');

        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'operator.basic-examination.vital-signs-recorded']);
        $this->assertNotNull($fixture['memberId']);
    }

    public function test_zero_negative_and_non_finite_height_or_weight_are_rejected_before_persistence(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-POSITIVE');
        $auditCount = DB::table('audit_events')->count();
        $outboxCount = DB::table('outbox_messages')->count();
        $invalid = [
            ['height_value' => '0', 'weight_value' => '75'],
            ['height_value' => '-180', 'weight_value' => '75'],
            ['height_value' => '1e309', 'weight_value' => '75'],
            ['height_value' => '180', 'weight_value' => '0'],
            ['height_value' => '180', 'weight_value' => '-75'],
            ['height_value' => '180', 'weight_value' => '1e309'],
        ];

        foreach ($invalid as $index => $values) {
            $operationId = (string) Str::uuid();
            $payload = $this->valuePayload($operationId);
            $payload['height_value'] = $values['height_value'];
            $payload['weight_value'] = $values['weight_value'];

            $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $payload)
                ->assertRedirect()
                ->assertSessionHasErrors($index < 3 ? 'height_value' : 'weight_value')
                ->assertDontSee('Vital signs recorded.');

            $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
        }

        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertSame($auditCount, DB::table('audit_events')->count());
        $this->assertSame($outboxCount, DB::table('outbox_messages')->count());
        $this->assertNotNull($fixture['memberId']);
    }

    public function test_member_contract_rejects_zero_negative_and_non_finite_height_or_weight(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-CONTRACT-POSITIVE');
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.basic-examination.vital-signs',
        );
        $contract = app(OperatorVitalSignsContract::class);

        foreach ([
            ['height_value' => '0', 'weight_value' => '75'],
            ['height_value' => '-180', 'weight_value' => '75'],
            ['height_value' => '1e309', 'weight_value' => '75'],
            ['height_value' => '180', 'weight_value' => '0'],
            ['height_value' => '180', 'weight_value' => '-75'],
            ['height_value' => '180', 'weight_value' => '1e309'],
        ] as $values) {
            $payload = $this->valuePayload((string) Str::uuid());
            $payload['height_value'] = $values['height_value'];
            $payload['weight_value'] = $values['weight_value'];

            try {
                $contract->record(
                    $context,
                    $fixture['siteStableId'],
                    $fixture['memberId'],
                    $fixture['bookingId'],
                    $fixture['scheduleId'],
                    $payload,
                    '2040-01-10T03:20:00+00:00',
                );
                $this->fail('Invalid positive-input BMI data must be rejected by the Member contract.');
            } catch (Mvp03Exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertNotNull($admission);
    }

    public function test_exact_replay_is_idempotent_and_changed_payload_conflicts(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-REPLAY');
        $operationId = (string) Str::uuid();
        $payload = $this->valuePayload($operationId);

        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $payload)->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $payload)->assertRedirect();
        $changed = $payload;
        $changed['temperature_value'] = '38.00';
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $changed)->assertConflict();

        $this->assertDatabaseCount('member_vital_signs_assessments', 1);
        $this->assertDatabaseCount('operator_vital_signs_executions', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.basic-examination.vital-signs-recorded')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.basic-examination-vital-signs-recorded')->count());
        $this->assertSame($fixture['memberId'], DB::table('member_vital_signs_assessments')->value('member_id'));
    }

    public function test_non_claimant_and_revoked_context_cannot_capture_or_leak_data(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-DENY');
        $other = $this->secondOperator($fixture);
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.basic-examination-worklist.vital-signs', $admission->id))
            ->assertForbidden()
            ->assertDontSee('120');
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload((string) Str::uuid()))
            ->assertForbidden()
            ->assertDontSee('120')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId']);

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload((string) Str::uuid()))->assertForbidden();
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'active']);
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload((string) Str::uuid()))->assertForbidden();

        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
    }

    public function test_audit_and_outbox_failures_roll_back_member_and_operator_records(): void
    {
        [$fixture, $admission] = $this->inServiceFixture('VITAL-ROLLBACK');
        $operationId = (string) Str::uuid();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic vital-signs audit failure');
            }
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload($operationId))
            ->assertRedirect();
        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);

        app()->instance(AuditStore::class, new DatabaseAuditStore);
        app()->forgetScopedInstances();
        $operationId = (string) Str::uuid();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic vital-signs outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();

        $this->post(route('operator.basic-examination-worklist.vital-signs.store', $admission->id), $this->valuePayload($operationId))
            ->assertRedirect();
        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId, 'status' => 'handled']);
        $this->assertDatabaseHas('operator_queue_admissions', ['id' => $admission->id, 'state' => 'in_service']);
        $this->assertNotNull($fixture['memberId']);
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

    private function canonicalDecimal(mixed $value): string
    {
        $value = (string) $value;

        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
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
