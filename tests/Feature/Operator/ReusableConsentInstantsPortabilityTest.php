<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ReusableConsentInstantsPortabilityTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_reusable_consent_instants_support_post_2038_timestamps(): void
    {
        $fixture = $this->operatorFixture();

        $consentId = (string) Str::uuid();
        $confirmationId = (string) Str::uuid();

        DB::table('member_master_consents')->insert([
            'id' => $consentId,
            'member_id' => $fixture['memberId'],
            'consent_version' => 1,
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'screening_scope' => 'radiography_screening',
            'signer_type' => 'member',
            'signer_member_id' => $fixture['memberId'],
            'signed_at' => '2040-01-10 00:00:00',
            'status' => 'active',
            'withdrawn_at' => '2040-01-11 00:00:00',
            'withdrawn_reason' => 'Test',
            'withdrawn_by_operator_id' => (string) $fixture['operator']->id,
            'created_by_operator_id' => (string) $fixture['operator']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('consent_visit_confirmations')->insert([
            'id' => $confirmationId,
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'member_master_consent_id' => $consentId,
            'examination_site_id' => $fixture['siteReferenceId'],
            'operator_site_id' => $fixture['siteStableId'],
            'confirmed_by_operator_id' => (string) $fixture['operator']->id,
            'confirmed_at' => '2040-01-10 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $consent = DB::table('member_master_consents')->where('id', $consentId)->first();
        $this->assertNotNull($consent);
        $this->assertSame('2040-01-10 00:00:00', (string) $consent->signed_at);
        $this->assertSame('2040-01-11 00:00:00', (string) $consent->withdrawn_at);

        $confirmation = DB::table('consent_visit_confirmations')->where('id', $confirmationId)->first();
        $this->assertNotNull($confirmation);
        $this->assertSame('2040-01-10 00:00:00', (string) $confirmation->confirmed_at);
    }

    public function test_reusable_consent_instants_migration_rollback_safety_guard(): void
    {
        $fixture = $this->operatorFixture();

        $consentId = (string) Str::uuid();
        DB::table('member_master_consents')->insert([
            'id' => $consentId,
            'member_id' => $fixture['memberId'],
            'consent_version' => 1,
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'screening_scope' => 'radiography_screening',
            'signer_type' => 'member',
            'signer_member_id' => $fixture['memberId'],
            'signed_at' => '2040-01-10 00:00:00',
            'status' => 'active',
            'withdrawn_at' => null,
            'created_by_operator_id' => (string) $fixture['operator']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000001_make_reusable_consent_instants_mysql_portable.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back reusable consent instants while values exceed the MySQL TIMESTAMP range.');

        $migration->down();
    }

    public function test_reusable_consent_instants_migration_rollback_and_reapply_succeeds_when_values_in_range(): void
    {
        $fixture = $this->operatorFixture();

        $consentId = (string) Str::uuid();
        DB::table('member_master_consents')->insert([
            'id' => $consentId,
            'member_id' => $fixture['memberId'],
            'consent_version' => 1,
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'screening_scope' => 'radiography_screening',
            'signer_type' => 'member',
            'signer_member_id' => $fixture['memberId'],
            'signed_at' => '2026-05-10 00:00:00',
            'status' => 'active',
            'withdrawn_at' => null,
            'created_by_operator_id' => (string) $fixture['operator']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000001_make_reusable_consent_instants_mysql_portable.php');

        $migration->down();
        $this->assertTrue(Schema::hasColumn('member_master_consents', 'signed_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('member_master_consents', 'signed_at'));
    }
}
