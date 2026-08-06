<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04bIdentityVerificationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.asset_grants' => ['max_ttl_seconds' => 300, 'audiences' => ['operator-identity']]]);
        Storage::fake('local');
    }

    public function test_arrived_operator_can_lookup_exact_nik_reveal_history_retrieve_inline_and_decide_without_check_in(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);

        $this->get(route('operator.identity-verification.show', $case['case_id']))
            ->assertOk()
            ->assertSee('Synthetic Arrival Member')
            ->assertSee('Current identity document')
            ->assertDontSee('Previous approved profile photograph');

        $nik = '900000000001';
        $lookup = $this->post(route('operator.identity-verification.lookup', $case['case_id']), [
            'nik' => $nik,
            'at' => '2040-01-10T10:15:00+07:00',
        ]);
        $lookup->assertOk()->assertSee('Masked NIK');
        $this->assertStringNotContainsString($nik, $lookup->getContent());
        $this->assertStringNotContainsString($nik, json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR));
        $reveal = $this->post(route('operator.identity-verification.previous-photos', $case['case_id']), [
            'reason' => 'Latest photo is insufficient for a human comparison.',
            'operation_id' => (string) Str::uuid(),
        ]);
        $reveal->assertRedirect(route('operator.identity-verification.show', $case['case_id']));
        $previousView = $this->get(route('operator.identity-verification.show', $case['case_id']));
        $previousView->assertOk()->assertSee('Previous approved profile photograph');
        $this->assertDatabaseHas('operator_identity_verification_events', ['verification_id' => $case['case_id'], 'event_type' => 'previous_photos_revealed']);

        $asset = DB::table('member_verification_assets')->where('member_id', $fixture['memberId'])->where('type', 'ktp')->first();
        $assetResponse = $this->get(route('operator.identity-verification.asset', [$case['case_id'], $asset->id]));
        $assetResponse->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('synthetic-identity-document');

        $this->post(route('operator.identity-verification.decision', $case['case_id']), [
            'state' => 'matched',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('operator.identity-verification.show', $case['case_id']));
        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'state' => 'matched']);
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->assertDatabaseMissing('bookings', ['id' => $fixture['bookingId'], 'status' => 'checked_in']);
        $this->get(route('operator.identity-verification.asset', [$case['case_id'], $asset->id]))->assertRedirect();
    }

    public function test_identity_permission_is_required_and_open_case_blocks_site_switch_until_cancelled(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');

        $this->post(route('operator.identity-verification.start'), ['arrival_id' => $arrivalId, 'operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseCount('operator_identity_verifications', 0);

        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['operator']->id,
            'permission' => 'operator.identity.verify',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $case = app('App\Modules\Operator\Application\Services\OperatorIdentityVerificationService')->start($arrivalId, (string) Str::uuid());
        $secondSiteId = $this->addSecondSite($fixture);

        try {
            app(OperatorActiveSiteService::class)->select($secondSiteId);
            $this->fail('An open identity case did not block site switching.');
        } catch (OperatorException $exception) {
            $this->assertSame('active_site_blocked', $exception->category);
        }
        $this->assertSame($fixture['siteLocalId'], session('operator.active_site_id'));

        app('App\Modules\Operator\Application\Services\OperatorIdentityVerificationService')->cancel($case['case_id'], 'Member left before verification completed.', (string) Str::uuid());
        app(OperatorActiveSiteService::class)->select($fixture['siteLocalId']);
        $reclaimed = app(OperatorIdentityVerificationService::class)->start($arrivalId, (string) Str::uuid(), true);
        $this->assertSame('open', $reclaimed['state']);
        app(OperatorIdentityVerificationService::class)->cancel($reclaimed['case_id'], 'Reclaim test complete.', (string) Str::uuid());
        app(OperatorActiveSiteService::class)->select($secondSiteId);
        $this->assertSame($secondSiteId, session('operator.active_site_id'));
    }

    public function test_terminal_decision_requires_reason_for_failure_and_is_idempotent_but_immutable(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $service = app(OperatorIdentityVerificationService::class);

        try {
            $service->decide($case['case_id'], OperatorIdentityVerificationService::MISMATCH_REPORTED, null, (string) Str::uuid());
            $this->fail('A mismatch without a reason was accepted.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_reason_required', $exception->category);
        }

        $operationId = (string) Str::uuid();
        $first = $service->decide($case['case_id'], OperatorIdentityVerificationService::MISMATCH_REPORTED, 'Physical identity does not match.', $operationId);
        $replay = $service->decide($case['case_id'], OperatorIdentityVerificationService::MISMATCH_REPORTED, 'Physical identity does not match.', $operationId);
        $this->assertSame($first['case_id'], $replay['case_id']);
        $this->assertSame('mismatch_reported', $replay['state']);

        try {
            $service->decide($case['case_id'], OperatorIdentityVerificationService::MATCHED, null, (string) Str::uuid());
            $this->fail('A terminal mismatch was reopened.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_terminal', $exception->category);
        }
        $this->assertSame(1, DB::table('operator_identity_verification_events')->where('verification_id', $case['case_id'])->where('event_type', 'decision')->count());
    }

    /** @return array<string, mixed> */
    private function identityFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['operator']->id,
            'permission' => 'operator.identity.verify',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertAsset($fixture, 'ktp', 'synthetic-identity-document', true);
        $this->insertAsset($fixture, 'profile_photo', 'synthetic-latest-profile', true);
        $this->insertAsset($fixture, 'profile_photo', 'synthetic-previous-profile', false);

        return $fixture;
    }

    /** @param array<string, mixed> $fixture */
    private function arriveAndStart(array $fixture): array
    {
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');

        return app('App\Modules\Operator\Application\Services\OperatorIdentityVerificationService')->start($arrivalId, (string) Str::uuid());
    }

    /** @param array<string, mixed> $fixture */
    private function insertAsset(array $fixture, string $type, string $contents, bool $current): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.identity.verify'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.identity.asset',
        );
        $object = app(PrivateObjectStore::class)->put($contents, $context, 'operator.identity.asset');
        $now = now();
        DB::table('member_verification_assets')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['memberId'],
            'type' => $type,
            'private_object_key' => (string) $object->key,
            'checksum' => $object->checksum,
            'bytes' => $object->bytes,
            'format' => 'image/jpeg',
            'review_status' => 'approved',
            'is_current' => $current,
            'uploaded_by_user_id' => $fixture['operator']->id,
            'reviewed_by_user_id' => $fixture['operator']->id,
            'reviewed_at' => $now,
            'replaces_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $fixture */
    private function addSecondSite(array $fixture): string
    {
        $siteId = (string) Str::uuid();
        DB::table('operator_sites')->insert([
            'id' => $siteId,
            'operator_site_id' => 'operator-site-second',
            'organization_id' => 'operator-org-second',
            'organization_name' => 'Second organization',
            'code' => 'SECOND-SITE',
            'display_name' => 'Second site',
            'address_line' => null,
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $fixture['profileId'],
            'operator_site_id' => $siteId,
            'active' => true,
            'assigned_by_user_id' => $fixture['operator']->id,
            'assigned_at' => now(),
            'revoked_at' => null,
            'reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $siteId;
    }
}
