<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\User;
use App\Modules\Operator\Application\Services\NonclinicalValidationOperatorContextProvisioningService;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Validation\NonclinicalValidationAccountProvisioningService;
use App\Shared\Validation\NonclinicalValidationContext;
use App\Shared\Validation\NonclinicalValidationContextProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
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
        $provider->bindValidationMember('11111111-1111-4111-8111-111111111111');
        $provider->memberBooking();
        $this->assertSame('11111111-1111-4111-8111-111111111111', (string) $provider->current()->actorId);

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
}
