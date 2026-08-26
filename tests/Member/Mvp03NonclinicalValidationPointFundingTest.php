<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Data\NonclinicalValidationMemberRegistrationData;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Validation\NonclinicalValidationContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp03NonclinicalValidationPointFundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_funding_creates_one_credit_from_the_selected_service_cost(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();

        $result = app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);

        $this->assertFalse($result['replayed']);
        $this->assertSame('12.5000', $result['point_cost']);
        $this->assertDatabaseCount('point_ledger_entries', 1);
        $this->assertDatabaseHas('point_ledger_entries', [
            'member_id' => $fixture['member_id'],
            'booking_id' => null,
            'funding_source' => 'personal',
            'entry_type' => 'credit',
            'point_delta' => '12.5000',
            'source_reference' => 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.point-funding.nonclinical-validation',
            'target_id' => $result['ledger_entry_id'],
        ]);
    }

    public function test_wrong_purpose_is_rejected_before_any_credit(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext('member.other-purpose');

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_authenticated_session_system_context_is_accepted_by_the_member_authorization_boundary(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();

        $result = app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);

        $this->assertFalse($result['replayed']);
    }

    public function test_anonymous_context_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindAnonymousContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_non_system_context_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindContext('authenticated-session', roles: ['administrator']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_exact_replay_before_booking_does_not_create_a_second_credit(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        $service = app(Mvp03PointService::class);

        $first = $service->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        $replay = $service->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);

        $this->assertFalse($first['replayed']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['ledger_entry_id'], $replay['ledger_entry_id']);
        $this->assertDatabaseCount('point_ledger_entries', 1);
    }

    public function test_exact_replay_after_normal_booking_verifies_matching_charge_and_zero_balance(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        $service = app(Mvp03PointService::class);
        $funding = $service->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);

        $this->bindContext('authenticated-session', $fixture['user']);
        $this->actingAs($fixture['user']);
        $booking = app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'validation-funding-booking');

        $this->bindFundingContext();
        $replay = $service->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);

        $this->assertTrue($replay['replayed']);
        $this->assertSame($funding['ledger_entry_id'], $replay['ledger_entry_id']);
        $this->assertSame('0.0000', (string) $service->personalBalance($fixture['member_id']));
        $this->assertDatabaseHas('point_ledger_entries', [
            'booking_id' => $booking['booking_id'],
            'entry_type' => 'charge',
            'point_delta' => '-12.5000',
            'funding_source' => 'personal',
            'source_reference' => 'booking:'.$booking['booking_id'].':personal-charge',
        ]);
        $this->assertDatabaseCount('point_ledger_entries', 2);
    }

    public function test_wrong_booking_charge_source_reference_is_rejected(): void
    {
        $state = $this->fundedBookingFixture();
        DB::table('point_ledger_entries')->where('booking_id', $state['booking']['booking_id'])->update(['source_reference' => 'wrong:charge-source']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($state['fixture']['schedule_id']);
    }

    public function test_wrong_booking_charge_amount_is_rejected(): void
    {
        $state = $this->fundedBookingFixture();
        DB::table('point_ledger_entries')->where('booking_id', $state['booking']['booking_id'])->update(['point_delta' => '-11.5000']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($state['fixture']['schedule_id']);
    }

    public function test_wrong_booking_point_snapshot_is_rejected(): void
    {
        $state = $this->fundedBookingFixture();
        DB::table('bookings')->where('id', $state['booking']['booking_id'])->update(['point_cost_snapshot' => '11.5000']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($state['fixture']['schedule_id']);
    }

    public function test_multiple_personal_post_funding_entries_are_rejected(): void
    {
        $state = $this->fundedBookingFixture();
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $state['fixture']['member_id'],
            'booking_id' => $state['booking']['booking_id'],
            'funding_source' => 'personal',
            'entry_type' => 'charge',
            'point_delta' => '-12.5000',
            'source_reference' => 'duplicate:charge-source',
            'reverses_id' => null,
            'created_at' => now(),
        ]);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($state['fixture']['schedule_id']);
    }

    public function test_deterministic_source_reference_has_a_unique_schema_guard(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_05_000003_create_mvp03_booking_tables.php'));

        $this->assertIsString($migration);
        $this->assertStringContainsString("\$table->string('source_reference', 191)->unique();", $migration);
    }

    public function test_normal_member_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000', false);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_wrong_validation_marker_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('member_external_identifiers')->where('member_id', $fixture['member_id'])->update(['value' => 'wrong-context']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_validation_member_with_identity_data_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('members')->where('id', $fixture['member_id'])->update(['identity_document_type' => 'ktp']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_validation_status_with_normal_source_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('members')->where('id', $fixture['member_id'])->update(['registration_source' => 'administrator']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_validation_source_with_normal_status_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('members')->where('id', $fixture['member_id'])->update(['identity_status' => 'pending_verification']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_pending_normal_member_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000', false);
        DB::table('members')->where('id', $fixture['member_id'])->update(['identity_status' => 'pending_verification']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_validation_member_with_a_verification_asset_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('member_verification_assets')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['member_id'],
            'type' => 'profile_photo',
            'private_object_key' => 'objects/validation-asset',
            'checksum' => hash('sha256', 'validation-asset'),
            'bytes' => 1,
            'format' => 'image/jpeg',
            'review_status' => 'pending',
            'is_current' => false,
            'uploaded_by_user_id' => $fixture['user']->id,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'replaces_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_zero_matching_markers_are_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('member_external_identifiers')->where('member_id', $fixture['member_id'])->delete();
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_multiple_canonical_validation_markers_are_rejected(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The duplicate-marker probe requires the local SQLite schema.');
        }

        $fixture = $this->fixture('12.5000');
        Schema::table('member_external_identifiers', static function (Blueprint $table): void {
            $table->dropUnique('member_external_identifiers_namespace_value_unique');
        });
        DB::table('member_external_identifiers')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['member_id'],
            'namespace' => NonclinicalValidationContext::MARKER_NAMESPACE,
            'value' => NonclinicalValidationContext::KEY,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_existing_credit_without_a_matching_booking_charge_and_zero_balance_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        DB::table('point_ledger_entries')->where('member_id', $fixture['member_id'])->where('source_reference', 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1')->update(['point_delta' => '0.0000']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_existing_deterministic_funding_for_another_member_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $otherMember = $this->createVerifiedMember(User::factory()->create());
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $otherMember,
            'booking_id' => null,
            'funding_source' => 'personal',
            'entry_type' => 'credit',
            'point_delta' => '12.5000',
            'source_reference' => 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1',
            'reverses_id' => null,
            'created_at' => now(),
        ]);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_unrelated_personal_ledger_history_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['member_id'],
            'booking_id' => null,
            'funding_source' => 'personal',
            'entry_type' => 'credit',
            'point_delta' => '1.0000',
            'source_reference' => 'unrelated:validation-funding',
            'reverses_id' => null,
            'created_at' => now(),
        ]);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_multiple_active_rates_are_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('point_exchange_rates')->insert([
            'id' => (string) Str::uuid(),
            'rupiah_per_point' => 10000,
            'status' => 'active',
            'effective_at' => now(),
            'configured_by_admin_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_zero_active_rates_are_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        DB::table('point_exchange_rates')->update(['status' => 'inactive']);
        $this->bindFundingContext();

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_wrong_existing_funding_amount_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        DB::table('point_ledger_entries')->where('source_reference', 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1')->update(['point_delta' => '11.5000']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_wrong_existing_funding_type_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        DB::table('point_ledger_entries')->where('source_reference', 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1')->update(['entry_type' => 'charge']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    public function test_wrong_existing_funding_source_is_rejected(): void
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        DB::table('point_ledger_entries')->where('source_reference', 'nonclinical-validation:real-npz-e2e-v1:booking-funding-v1')->update(['funding_source' => 'business_reserved']);

        $this->expectException(Mvp03Exception::class);
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
    }

    /** @return array{user: User, member_id: string, schedule_id: string} */
    private function fixture(string $pointPrice, bool $validation = true): array
    {
        $user = User::factory()->create();
        $memberId = $validation
            ? $this->registerValidationMember($user)
            : $this->createVerifiedMember($user);
        $now = now();
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();

        DB::table('operator_organization_refs')->insert([
            'id' => $organizationId,
            'operator_organization_id' => 'operator-'.$siteId,
            'name' => 'Validation Organization',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('examination_site_refs')->insert([
            'id' => $siteId,
            'operator_site_id' => 'site-'.$siteId,
            'operator_organization_ref_id' => $organizationId,
            'code' => 'SITE-'.substr($siteId, 0, 8),
            'display_name' => 'Validation Site',
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('service_offerings')->insert([
            'id' => $serviceId,
            'code' => 'VALIDATION-SERVICE',
            'name' => 'Validation Service',
            'includes_ai' => false,
            'includes_doctor' => false,
            'point_price' => $pointPrice,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('point_exchange_rates')->insert([
            'id' => (string) Str::uuid(),
            'rupiah_per_point' => 10000,
            'status' => 'active',
            'effective_at' => $now,
            'configured_by_admin_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('shift_schedules')->insert([
            'id' => $scheduleId,
            'display_reference' => 'JAD-'.Str::upper(Str::random(8)),
            'examination_site_id' => $siteId,
            'service_offering_id' => $serviceId,
            'starts_at' => '2030-01-10 03:00:00',
            'ends_at' => '2030-01-10 04:00:00',
            'quota' => 5,
            'status' => 'open',
            'eligible_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['user' => $user->fresh(), 'member_id' => $memberId, 'schedule_id' => $scheduleId];
    }

    /** @return array{fixture: array{user: User, member_id: string, schedule_id: string}, booking: array<string, mixed>} */
    private function fundedBookingFixture(): array
    {
        $fixture = $this->fixture('12.5000');
        $this->bindFundingContext();
        app(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($fixture['schedule_id']);
        $this->bindContext('authenticated-session', $fixture['user']);
        $this->actingAs($fixture['user']);
        $booking = app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'validation-funding-booking-'.Str::uuid());
        $this->bindFundingContext();

        return ['fixture' => $fixture, 'booking' => $booking];
    }

    private function registerValidationMember(User $user): string
    {
        $this->bindContext('member.nonclinical-validation', $user);
        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(
            new NonclinicalValidationMemberRegistrationData('validation-funding-'.$user->id, (string) $user->id),
        );

        return $result->memberId;
    }

    private function createVerifiedMember(User $user): string
    {
        $memberId = (string) Str::uuid();
        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.Str::upper(Str::random(10)),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'protected',
            'nik_lookup_digest' => hash('sha256', $memberId),
            'name' => 'Verified Member',
            'birth_date' => '1985-08-04',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Address',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $memberId;
    }

    /** @param list<string> $roles */
    private function bindContext(string $purpose, ?User $user = null, array $roles = ['system']): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) ($user?->id ?? Str::uuid())),
            operationId: CorrelationId::random(),
            roles: $roles,
            purpose: $purpose,
        );
        $this->app->instance(AuthenticatedContextProvider::class, new class($context) implements AuthenticatedContextProvider
        {
            public function __construct(private readonly AuthenticatedContext $context) {}

            public function current(): AuthenticatedContext
            {
                return $this->context;
            }
        });
    }

    private function bindFundingContext(string $purpose = 'member.nonclinical-validation.point-funding'): void
    {
        $this->bindContext($purpose === 'member.nonclinical-validation.point-funding' ? 'authenticated-session' : $purpose);
    }

    private function bindAnonymousContext(): void
    {
        $this->app->instance(AuthenticatedContextProvider::class, new class implements AuthenticatedContextProvider
        {
            public function current(): AuthenticatedContext
            {
                return AuthenticatedContext::anonymous();
            }
        });
    }
}
