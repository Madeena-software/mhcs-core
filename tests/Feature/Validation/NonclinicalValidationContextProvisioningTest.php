<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\User;
use App\Modules\Operator\Application\Services\NonclinicalValidationOperatorContextProvisioningService;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Shared\Authorization\ActiveSiteResolver;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\LaravelAuthenticatedContextProvider;
use App\Shared\Validation\NonclinicalValidationAccountProvisioningService;
use App\Shared\Validation\NonclinicalValidationContext;
use App\Shared\Validation\NonclinicalValidationContextProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class NonclinicalValidationContextProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_provider_is_closed_and_binds_one_validation_member(): void
    {
        $provider = new NonclinicalValidationContextProvider;
        $this->expectException(LogicException::class);
        $provider->memberBooking();
    }

    public function test_context_provider_rejects_rebinding_and_exposes_only_fixed_system_phases(): void
    {
        $provider = new NonclinicalValidationContextProvider;
        $memberId = '11111111-1111-4111-8111-111111111111';
        DB::table('audit_events')->insert([
            'event_id' => (string) Str::uuid(), 'event_version' => 1, 'actor_id' => '00000000-0000-4000-8000-000000000000',
            'roles' => json_encode(['system']), 'permissions' => json_encode([]), 'target_type' => 'App\\Models\\User', 'target_id' => $memberId,
            'action' => 'production.validation-context.member-account.provisioned', 'occurred_at' => now(), 'recorded_at' => now(),
            'source' => 'validation', 'outcome' => 'success', 'metadata' => json_encode(['validation_context' => NonclinicalValidationContext::KEY, 'nonclinical' => true, 'principal_type' => 'member']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $provider->bindValidationMember($memberId);
        $provider->memberBooking();
        $this->assertSame($memberId, (string) $provider->current()->actorId);

        try {
            $provider->bindValidationMember('22222222-2222-4222-8222-222222222222');
            $this->fail('The validation Member context was rebound.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $provider->accountProvisioning();
        $this->assertSame('production.validation-context.account-provision', $provider->current()->purpose);
        $provider->operatorProvisioning();
        $this->assertSame('production.validation-context.operator-context-provision', $provider->current()->purpose);
        $this->assertSame(['system'], $provider->current()->roles);
    }

    public function test_lookalike_operator_without_phase_c_ownership_is_rejected(): void
    {
        $fixture = $this->fixture();
        $user = User::factory()->create(['email' => 'lookalike@example.test', 'password' => Hash::make('lookalike-secret')]);
        $now = now();
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'operator', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach (['operator.portal.access', 'operator.attendance.read', 'operator.arrival.record', 'operator.identity.verify'] as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $provider = new NonclinicalValidationContextProvider;
        $provider->operatorProvisioning();
        $this->app->instance(AuthenticatedContextProvider::class, $provider);

        $this->expectException(\RuntimeException::class);
        app(NonclinicalValidationOperatorContextProvisioningService::class)->provision($user->id, $fixture['schedule_id'], 'site-validation', $fixture['eligible_id']);
    }

    public function test_missing_legitimate_eligible_shift_fails_without_creating_context(): void
    {
        $this->fixture(false);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'=test-secret-'.Str::random(32));
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('members')->count());
        $this->assertSame(0, DB::table('operator_eligible_shifts')->count());
    }

    public function test_replay_fails_closed_when_local_imaging_order_is_removed(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        DB::table('local_imaging_orders')->delete();

        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
        $this->assertSame(1, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('local_imaging_orders')->count());
    }

    public function test_replay_fails_closed_on_an_unexpected_operator_permission(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $operatorId = DB::table('operator_profiles')->value('user_id');
        DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $operatorId, 'permission' => 'operator.shift.manage', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
        $this->assertSame(1, DB::table('bookings')->count());
        $this->assertSame(1, DB::table('operator_profiles')->count());
    }

    public function test_replay_fails_closed_when_profile_ownership_is_removed(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        DB::table('audit_events')->where('action', 'production.validation-context.operator-profile.provisioned')->delete();

        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
        $this->assertSame(1, DB::table('bookings')->count());
        $this->assertSame(1, DB::table('operator_profiles')->count());
    }

    public function test_inconsistent_earliest_projection_is_skipped(): void
    {
        $fixture = $this->fixture();
        $schedule = DB::table('shift_schedules')->where('id', $fixture['schedule_id'])->first();
        $badSchedule = (string) Str::uuid();
        $badEligible = (string) Str::uuid();
        $this->insertCandidate($schedule, $badSchedule, $badEligible, now()->utc()->addDay(), now()->utc()->addDay()->addHours(4), now()->utc()->addDays(3));

        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $this->assertSame($fixture['schedule_id'], DB::table('bookings')->value('shift_schedule_id'));
    }

    public function test_identical_start_times_use_schedule_id_tiebreak(): void
    {
        $fixture = $this->fixture();
        DB::table('shift_schedules')->where('id', $fixture['schedule_id'])->update(['status' => 'closed']);
        $source = DB::table('shift_schedules')->where('id', $fixture['schedule_id'])->first();
        $starts = now()->utc()->addDays(2);
        $first = '00000000-0000-4000-8000-000000000001';
        $second = '00000000-0000-4000-8000-000000000002';
        $this->insertCandidate($source, $first, (string) Str::uuid(), $starts, $starts->copy()->addHours(4), $starts);
        $this->insertCandidate($source, $second, (string) Str::uuid(), $starts, $starts->copy()->addHours(4), $starts);

        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $this->assertSame($first, DB::table('bookings')->value('shift_schedule_id'));
    }

    public function test_site_and_shift_ownership_markers_are_required_on_replay(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);

        DB::table('audit_events')->where('action', 'production.validation-context.site-assignment.provisioned')->delete();
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);

        DB::table('audit_events')->insert($this->ownedMarker('site-assignment.provisioned', (string) DB::table('operator_site_assignments')->value('id')));
        DB::table('audit_events')->where('action', 'production.validation-context.shift-assignment.provisioned')->delete();
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
    }

    public function test_runtime_operator_login_and_active_site_resolution_succeed(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $operator = User::query()->where('email', 'like', '%-operator@invalid')->firstOrFail();
        $siteId = DB::table('operator_site_assignments')->value('operator_site_id');

        $this->assertTrue(auth('web')->attempt(['email' => $operator->email, 'password' => $secret]));
        session()->put('operator.active_site_id', $siteId);
        $context = app(LaravelAuthenticatedContextProvider::class)->current();
        $this->assertSame($siteId, (string) app(ActiveSiteResolver::class)->resolve($context));
    }

    public function test_nullable_provenance_keeps_foreign_key_enforced(): void
    {
        $fixture = $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $assignment = DB::table('operator_shift_assignments')->first();
        $userId = DB::table('users')->where('email', 'like', '%-operator@invalid')->value('id');
        DB::table('operator_shift_assignments')->where('id', $assignment->id)->update(['assigned_by_user_id' => $userId]);
        $this->assertSame($userId, DB::table('operator_shift_assignments')->where('id', $assignment->id)->value('assigned_by_user_id'));
        DB::table('operator_shift_assignments')->where('id', $assignment->id)->update(['assigned_by_user_id' => null]);

        $this->expectException(QueryException::class);
        DB::table('operator_shift_assignments')->where('id', $assignment->id)->update(['assigned_by_user_id' => (string) Str::uuid()]);
    }

    public function test_normal_administrator_shift_assignment_keeps_authenticated_provenance(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $assignment = DB::table('operator_shift_assignments')->first();
        DB::table('operator_shift_assignments')->where('id', $assignment->id)->update(['status' => 'revoked', 'revoked_at' => now()]);

        $admin = User::factory()->create(['email' => 'administrator-'.Str::random(8).'@example.test']);
        $now = now();
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role' => 'administrator', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'permission' => 'operator.shift.manage', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->actingAs($admin);

        $created = app(OperatorShiftAssignmentService::class)->assign((string) $assignment->operator_eligible_shift_id, (string) $assignment->operator_profile_id);
        $this->assertSame($admin->id, $created->assigned_by_user_id);
        $this->assertNotNull($created->assigned_by_user_id);
    }

    public function test_unrelated_operational_rows_are_preserved_during_provisioning(): void
    {
        $fixture = $this->fixture();
        $now = now()->utc();
        $otherUser = User::factory()->create(['email' => 'unrelated-member@example.test']);
        $otherMember = (string) Str::uuid();
        $otherSchedule = (string) Str::uuid();
        $otherBooking = (string) Str::uuid();
        $ticket = (string) Str::uuid();
        $admission = (string) Str::uuid();
        $assessment = (string) Str::uuid();
        $execution = (string) Str::uuid();
        $otherOperator = User::factory()->create(['email' => 'unrelated-operator@example.test']);
        $otherProfile = (string) Str::uuid();
        $site = DB::table('operator_sites')->where('operator_site_id', 'site-validation')->first();
        $siteRef = DB::table('examination_site_refs')->first();
        $service = DB::table('service_offerings')->first();
        $rate = DB::table('point_exchange_rates')->first();
        DB::table('shift_schedules')->insert(['id' => $otherSchedule, 'examination_site_id' => $siteRef->id, 'service_offering_id' => $service->id, 'display_reference' => 'JAD-ENDED', 'starts_at' => $now->copy()->subDays(2), 'ends_at' => $now->copy()->subDay(), 'quota' => 10, 'status' => 'closed', 'eligible_at' => $now->copy()->subDays(3), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('members')->insert(['id' => $otherMember, 'user_id' => $otherUser->id, 'family_id' => null, 'medical_record_number' => (string) Str::uuid(), 'identity_status' => 'nonclinical_validation', 'identity_document_type' => null, 'encrypted_nik' => null, 'nik_lookup_digest' => null, 'name' => 'Unrelated Member', 'birth_date' => '1990-01-01', 'administrative_gender' => 'unknown', 'registration_source' => 'test', 'phone' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('bookings')->insert(['id' => $otherBooking, 'member_id' => $otherMember, 'shift_schedule_id' => $otherSchedule, 'service_offering_id' => $service->id, 'examination_site_id_snapshot' => $siteRef->id, 'booking_type' => 'b2c', 'funding_source' => 'personal', 'status' => 'confirmed', 'service_code_snapshot' => $service->code, 'point_cost_snapshot' => $service->point_price, 'point_exchange_rate_id' => $rate->id, 'includes_ai_snapshot' => false, 'includes_doctor_snapshot' => false, 'site_code_snapshot' => $siteRef->code, 'site_name_snapshot' => $siteRef->display_name, 'site_timezone_snapshot' => $siteRef->timezone, 'created_at' => $now, 'confirmed_at' => $now, 'updated_at' => $now]);
        DB::table('operator_profiles')->insert(['id' => $otherProfile, 'user_id' => $otherOperator->id, 'display_name' => 'Unrelated Operator', 'employee_code' => 'UNRELATED-1', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_paper_tickets')->insert(['id' => $ticket, 'booking_id' => $otherBooking, 'member_schedule_id' => $otherSchedule, 'operator_site_id' => $site->id, 'operator_profile_id' => $otherProfile, 'ticket_number' => 'UNRELATED-1', 'issued_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_queue_admissions')->insert(['id' => $admission, 'operator_paper_ticket_id' => $ticket, 'operator_site_id' => $site->id, 'member_schedule_id' => $otherSchedule, 'queue_class' => 'standard', 'stage' => 'arrival', 'state' => 'waiting', 'ready_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('member_vital_signs_assessments')->insert(['id' => $assessment, 'member_id' => $otherMember, 'booking_id' => $otherBooking, 'member_schedule_id' => $otherSchedule, 'systolic_bp_value' => null, 'systolic_bp_unit' => 'mmHg', 'systolic_bp_missing_reason' => 'not_taken', 'diastolic_bp_value' => null, 'diastolic_bp_unit' => 'mmHg', 'diastolic_bp_missing_reason' => 'not_taken', 'temperature_value' => null, 'temperature_unit' => 'C', 'temperature_missing_reason' => 'not_taken', 'height_value' => null, 'height_unit' => 'cm', 'height_missing_reason' => 'not_taken', 'weight_value' => null, 'weight_unit' => 'kg', 'weight_missing_reason' => 'not_taken', 'bmi_value' => null, 'bmi_unit' => 'kg/m2', 'bmi_missing_reason' => 'not_taken', 'effective_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_vital_signs_executions')->insert(['id' => $execution, 'member_vital_signs_assessment_id' => $assessment, 'operator_queue_admission_id' => $admission, 'operator_profile_id' => $otherProfile, 'operator_site_id' => $site->id, 'occurred_at' => $now, 'operation_id' => 'unrelated-vitals-'.$execution, 'created_at' => $now, 'updated_at' => $now]);

        $counts = array_map(fn (string $table): int => DB::table($table)->count(), ['operator_paper_tickets', 'operator_queue_admissions', 'member_vital_signs_assessments', 'operator_vital_signs_executions']);
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $this->assertSame($ticket, DB::table('operator_paper_tickets')->where('id', $ticket)->value('id'));
        $this->assertSame($admission, DB::table('operator_queue_admissions')->where('id', $admission)->value('id'));
        $this->assertSame($assessment, DB::table('member_vital_signs_assessments')->where('id', $assessment)->value('id'));
        $this->assertSame($execution, DB::table('operator_vital_signs_executions')->where('id', $execution)->value('id'));
        $this->assertSame($counts, array_map(fn (string $table): int => DB::table($table)->count(), ['operator_paper_tickets', 'operator_queue_admissions', 'member_vital_signs_assessments', 'operator_vital_signs_executions']));
    }

    public function test_late_unowned_profile_failure_rolls_back_command_state(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        $provider = new NonclinicalValidationContextProvider;
        $provider->accountProvisioning();
        $this->app->instance(AuthenticatedContextProvider::class, $provider);
        $accounts = app(NonclinicalValidationAccountProvisioningService::class)->provision($secret);
        $profile = (string) Str::uuid();
        DB::table('operator_profiles')->insert(['id' => $profile, 'user_id' => $accounts['operator_user_id'], 'display_name' => 'Pre-existing unowned', 'employee_code' => 'PREEXISTING-1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame(1, DB::table('operator_profiles')->where('id', $profile)->count());
        $this->assertSame(0, DB::table('members')->count());
        $this->assertSame(0, DB::table('member_external_identifiers')->count());
        $this->assertSame(0, DB::table('point_ledger_entries')->count());
        $this->assertSame(0, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('local_imaging_orders')->count());
        $this->assertSame(0, DB::table('operator_site_assignments')->count());
        $this->assertSame(0, DB::table('operator_shift_assignments')->count());
        $this->assertSame(2, DB::table('audit_events')->whereIn('action', ['production.validation-context.member-account.provisioned', 'production.validation-context.operator-account.provisioned'])->count());
    }

    public function test_command_output_contains_no_generated_identifiers_or_secret(): void
    {
        $fixture = $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $outputBuffer = new BufferedOutput;
        $this->assertSame(0, $this->app->make(Kernel::class)->call('mhcs:provision-nonclinical-validation-context', [], $outputBuffer));
        $output = $outputBuffer->fetch();
        $member = DB::table('members')->first();
        $operator = DB::table('users')->where('email', 'like', '%-operator@invalid')->first();
        $values = [$member->user_id, $operator->id, $member->id, $member->medical_record_number, DB::table('bookings')->value('id'), $fixture['schedule_id'], DB::table('operator_sites')->value('id'), 'site-validation', DB::table('operator_profiles')->value('id'), $fixture['eligible_id'], DB::table('operator_site_assignments')->value('id'), DB::table('operator_shift_assignments')->value('id'), $operator->email, DB::table('users')->where('id', $operator->id)->value('password'), $secret];
        foreach (array_filter($values, fn (mixed $value): bool => is_string($value) && $value !== '') as $value) {
            $this->assertStringNotContainsString($value, $output);
        }
        foreach (['validation_context_key='.NonclinicalValidationContext::KEY, 'environment_guard=PASS', 'authorization_guard=PASS', 'operator_minimum_permissions=PASS', 'operator_site_assignment=PASS', 'operator_shift_assignment=PASS', 'arrival_state=NOT_EXECUTED', 'ticket_state=NOT_EXECUTED', 'basic_examination_state=NOT_EXECUTED', 'xray_admission_state=NOT_EXECUTED', 'capture_present=false', 'validation_operator_login_ready=true', 'audit_marker=PASS', 'application_records_retention=RETAINED', 'validation_context_provisioning=PASS'] as $field) {
            $this->assertStringContainsString($field, $output);
        }
    }

    public function test_command_failure_output_is_bounded(): void
    {
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME);
        $outputBuffer = new BufferedOutput;
        $this->assertSame(1, $this->app->make(Kernel::class)->call('mhcs:provision-nonclinical-validation-context', [], $outputBuffer));
        $output = $outputBuffer->fetch();
        $this->assertStringContainsString('validation_context_provisioning=FAIL', $output);
        $this->assertStringContainsString('failure_category=SECRET_REQUIRED', $output);
        $this->assertStringNotContainsString('stack trace', strtolower($output));
        $this->assertStringNotContainsString('SQLSTATE', $output);
    }

    public function test_command_boundary_and_missing_secret_are_fail_closed(): void
    {
        $this->assertArrayHasKey('mhcs:provision-nonclinical-validation-context', $this->app->make(Kernel::class)->all());

        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME);
        $this->artisan('mhcs:provision-nonclinical-validation-context')
            ->assertExitCode(1)
            ->expectsOutput('failure_category=SECRET_REQUIRED');
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('members')->count());
        $this->assertSame(0, DB::table('point_ledger_entries')->count());
        $this->assertSame(0, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('operator_profiles')->count());
    }

    public function test_first_run_creates_the_normal_bounded_context_and_stops_before_operations(): void
    {
        $fixture = $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);

        $this->artisan('mhcs:provision-nonclinical-validation-context')
            ->assertExitCode(0)
            ->expectsOutput('validation_context_key='.NonclinicalValidationContext::KEY)
            ->expectsOutput('arrival_state=NOT_EXECUTED')
            ->expectsOutput('ticket_state=NOT_EXECUTED')
            ->expectsOutput('basic_examination_state=NOT_EXECUTED')
            ->expectsOutput('xray_admission_state=NOT_EXECUTED')
            ->expectsOutput('capture_present=false');

        $memberUserId = (string) DB::table('members')->value('user_id');
        $operatorUserId = (string) DB::table('operator_profiles')->value('user_id');
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame([], DB::table('authorization_role_assignments')->where('user_id', $memberUserId)->pluck('role')->all());
        $this->assertSame([], DB::table('authorization_permission_assignments')->where('user_id', $memberUserId)->pluck('permission')->all());
        $this->assertSame(['operator'], DB::table('authorization_role_assignments')->where('user_id', $operatorUserId)->pluck('role')->all());
        $this->assertSame([
            'operator.arrival.record', 'operator.attendance.read',
            'operator.identity.verify', 'operator.portal.access',
        ], DB::table('authorization_permission_assignments')->where('user_id', $operatorUserId)->orderBy('permission')->pluck('permission')->all());

        $member = DB::table('members')->first();
        $booking = DB::table('bookings')->first();
        $this->assertSame('nonclinical_validation', $member->identity_status);
        $this->assertSame('nonclinical_validation', $member->registration_source);
        $this->assertNull($member->identity_document_type);
        $this->assertNull($member->encrypted_nik);
        $this->assertNull($member->nik_lookup_digest);
        $this->assertSame(1, DB::table('member_external_identifiers')->where('namespace', 'mhcs.validation')->where('value', NonclinicalValidationContext::KEY)->count());
        $this->assertSame(0, DB::table('member_verification_assets')->count());
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('b2c', $booking->booking_type);
        $this->assertSame('personal', $booking->funding_source);
        $this->assertSame($fixture['schedule_id'], $booking->shift_schedule_id);
        $this->assertSame(2, DB::table('point_ledger_entries')->count());
        $this->assertSame(1, DB::table('local_imaging_orders')->count());
        $this->assertSame(1, DB::table('operator_site_assignments')->count());
        $this->assertSame(1, DB::table('operator_shift_assignments')->count());
        $this->assertNull(DB::table('operator_site_assignments')->value('assigned_by_user_id'));
        $this->assertNull(DB::table('operator_shift_assignments')->value('assigned_by_user_id'));
        $this->assertSame(0, DB::table('operator_arrivals')->count());
        $this->assertSame(0, DB::table('operator_identity_verifications')->count());
        $this->assertSame(0, DB::table('examination_consents')->count());
        $this->assertSame(0, DB::table('operator_paper_tickets')->count());
        $this->assertSame(0, DB::table('operator_queue_admissions')->count());
        $this->assertSame(0, DB::table('member_vital_signs_assessments')->count());
        $this->assertSame(0, DB::table('operator_vital_signs_executions')->count());
        $this->assertSame(0, DB::table('member_paper_questionnaires')->count());
        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
    }

    public function test_exact_replay_is_duplicate_free_and_wrong_secret_fails_closed(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);
        $counts = array_map(fn (string $table): int => DB::table($table)->count(), ['users', 'members', 'member_external_identifiers', 'point_ledger_entries', 'bookings', 'local_imaging_orders', 'operator_profiles', 'operator_site_assignments', 'operator_shift_assignments']);
        $hash = DB::table('users')->where('email', 'like', '%-operator@invalid')->value('password');

        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0)->expectsOutput('booking_state=EXISTING_VALID');
        $this->assertSame($counts, array_map(fn (string $table): int => DB::table($table)->count(), ['users', 'members', 'member_external_identifiers', 'point_ledger_entries', 'bookings', 'local_imaging_orders', 'operator_profiles', 'operator_site_assignments', 'operator_shift_assignments']));
        $this->assertSame($hash, DB::table('users')->where('email', 'like', '%-operator@invalid')->value('password'));

        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'=wrong-'.Str::random(16));
        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(1)->expectsOutput('failure_category=SAFE_PROVISIONING_FAILURE');
        $this->assertSame($hash, DB::table('users')->where('email', 'like', '%-operator@invalid')->value('password'));
    }

    public function test_replay_accepts_semantically_identical_operator_ownership_metadata_in_different_key_order(): void
    {
        $this->fixture();
        $secret = 'test-secret-'.Str::random(32);
        putenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME.'='.$secret);

        $this->artisan('mhcs:provision-nonclinical-validation-context')->assertExitCode(0);

        DB::table('audit_events')
            ->where('action', 'production.validation-context.operator-account.provisioned')
            ->update(['metadata' => json_encode([
                'principal_type' => 'operator',
                'validation_context' => NonclinicalValidationContext::KEY,
                'nonclinical' => true,
            ], JSON_THROW_ON_ERROR)]);

        $this->artisan('mhcs:provision-nonclinical-validation-context')
            ->assertExitCode(0)
            ->expectsOutput('booking_state=EXISTING_VALID');
    }

    /** @return array{schedule_id: string, eligible_id: string} */
    private function fixture(bool $withEligible = true): array
    {
        $now = now()->utc();
        $ids = array_map(fn (): string => (string) Str::uuid(), range(1, 8));
        [$org, $siteRef, $service, $site, $schedule, $eligible, $rate, $source] = $ids;
        DB::table('operator_organization_refs')->insert(['id' => $org, 'operator_organization_id' => 'org-validation', 'name' => 'Validation Org', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_sites')->insert(['id' => $site, 'operator_site_id' => 'site-validation', 'organization_id' => 'org-validation', 'organization_name' => 'Validation Org', 'code' => 'VAL-01', 'display_name' => 'Validation Site', 'timezone' => 'UTC', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteRef, 'operator_site_id' => 'site-validation', 'operator_organization_ref_id' => $org, 'code' => 'VAL-01', 'display_name' => 'Validation Site', 'timezone' => 'UTC', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('service_offerings')->insert(['id' => $service, 'code' => 'VAL-CHEST', 'name' => 'Validation service', 'point_price' => '12.5000', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('point_exchange_rates')->insert(['id' => $rate, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shift_schedules')->insert(['id' => $schedule, 'examination_site_id' => $siteRef, 'service_offering_id' => $service, 'display_reference' => 'VAL-2026-01', 'starts_at' => $now->copy()->addDays(2), 'ends_at' => $now->copy()->addDays(2)->addHours(4), 'quota' => 10, 'status' => 'open', 'eligible_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        if ($withEligible) {
            DB::table('operator_eligible_shifts')->insert(['id' => $eligible, 'member_schedule_id' => $schedule, 'operator_site_id' => 'site-validation', 'schedule_starts_at' => $now->copy()->addDays(2), 'schedule_ends_at' => $now->copy()->addDays(2)->addHours(4), 'confirmed_count_at_eligibility' => 5, 'quota' => 10, 'event_version' => 1, 'source_event_id' => 'fixture:'.$schedule, 'eligible_at' => $now, 'sync_status' => 'eligible', 'created_at' => $now, 'updated_at' => $now]);
        }

        return ['schedule_id' => $schedule, 'eligible_id' => $eligible];
    }

    private function insertCandidate(object $source, string $schedule, string $eligible, mixed $starts, mixed $ends, mixed $projectionStarts): void
    {
        $now = now()->utc();
        DB::table('shift_schedules')->insert([
            'id' => $schedule, 'examination_site_id' => $source->examination_site_id, 'service_offering_id' => $source->service_offering_id,
            'display_reference' => 'JAD-'.Str::substr($schedule, -8), 'starts_at' => $starts, 'ends_at' => $ends, 'quota' => 10, 'status' => 'open', 'eligible_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('operator_eligible_shifts')->insert([
            'id' => $eligible, 'member_schedule_id' => $schedule, 'operator_site_id' => 'site-validation', 'schedule_starts_at' => $projectionStarts,
            'schedule_ends_at' => $ends, 'confirmed_count_at_eligibility' => 0, 'quota' => 10, 'event_version' => 1, 'source_event_id' => 'candidate:'.$schedule,
            'eligible_at' => $now, 'sync_status' => 'eligible', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed> */
    private function ownedMarker(string $suffix, string $targetId): array
    {
        return [
            'event_id' => (string) Str::uuid(), 'event_version' => 1, 'actor_id' => '00000000-0000-4000-8000-000000000000',
            'roles' => json_encode(['system']), 'permissions' => json_encode([]), 'target_type' => 'operator', 'target_id' => $targetId,
            'action' => 'production.validation-context.'.$suffix, 'occurred_at' => now(), 'recorded_at' => now(), 'source' => 'validation', 'outcome' => 'success',
            'metadata' => json_encode(['validation_context' => NonclinicalValidationContext::KEY, 'nonclinical' => true, 'provisioning_actor' => 'system', 'human_assignment_performed' => false]),
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
}
