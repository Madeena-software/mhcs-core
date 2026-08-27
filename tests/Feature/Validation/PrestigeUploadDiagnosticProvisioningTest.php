<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\User;
use App\Modules\Member\Application\Data\PrestigeUploadDiagnosticMemberRegistrationData;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use App\Shared\Validation\NonclinicalValidationContext;
use App\Shared\Validation\NonclinicalValidationContextProvider;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class PrestigeUploadDiagnosticProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.identifier_key' => str_repeat('i', 32), 'mhcs.security.grant_key' => str_repeat('g', 32)]);
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-08-27T01:07:00+00:00')));
    }

    public function test_fixed_prestige_subject_is_registered_without_clinical_identity_assets(): void
    {
        $user = User::factory()->create(['email' => 'gbsuparta@ugm.ac.id', 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false]);
        $provider = new NonclinicalValidationContextProvider(NonclinicalValidationContext::PRESTIGE_KEY);
        $this->app->instance(AuthenticatedContextProvider::class, $provider);
        $provider->memberRegistration();

        $result = app(MemberRegistrationService::class)->registerPrestigeUploadDiagnostic(new PrestigeUploadDiagnosticMemberRegistrationData('prestige-upload-diagnostic-v1:gbsuparta:member-v1', (string) $user->id, 'gbsuparta'));
        $member = DB::table('members')->where('id', $result->memberId)->first();

        $this->assertSame('nonclinical_validation', $member->identity_status);
        $this->assertSame('nonclinical_validation', $member->registration_source);
        $this->assertNull($member->identity_document_type);
        $this->assertNull($member->encrypted_nik);
        $this->assertNull($member->nik_lookup_digest);
        $this->assertSame(0, DB::table('member_verification_assets')->where('member_id', $member->id)->count());
        $this->assertDatabaseHas('member_external_identifiers', ['member_id' => $member->id, 'namespace' => 'prestige.validation', 'value' => 'gbsuparta']);
    }

    public function test_registration_contract_rejects_arbitrary_subjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PrestigeUploadDiagnosticMemberRegistrationData('operation', 'user', 'arbitrary');
    }
}
