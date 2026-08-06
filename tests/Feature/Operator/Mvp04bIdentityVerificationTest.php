<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\Member\Application\Contracts\OperatorIdentityVerificationContract;
use App\Modules\Member\Application\Services\MemberVerificationAssetService;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
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
        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'state' => 'matched', 'active_claim_operator_profile_id' => null]);
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

    public function test_matched_revalidates_current_evidence_before_mutation(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $events = DB::table('operator_identity_verification_events')->where('verification_id', $case['case_id'])->count();
        $audit = DB::table('audit_events')->count();
        DB::table('member_verification_assets')
            ->where('member_id', $fixture['memberId'])
            ->where('type', 'profile_photo')
            ->where('is_current', true)
            ->update(['review_status' => 'rejected', 'is_current' => false]);

        try {
            app(OperatorIdentityVerificationService::class)->decide($case['case_id'], OperatorIdentityVerificationService::MATCHED, null, (string) Str::uuid());
            $this->fail('A matched decision was accepted without current approved evidence.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_evidence_unavailable', $exception->category);
        }

        $this->assertDatabaseHas('operator_identity_verifications', [
            'id' => $case['case_id'],
            'state' => OperatorIdentityVerificationService::OPEN,
            'active_claim_operator_profile_id' => $fixture['profileId'],
        ]);
        $this->assertSame($events, DB::table('operator_identity_verification_events')->where('verification_id', $case['case_id'])->count());
        $this->assertSame($audit, DB::table('audit_events')->count());
    }

    public function test_asset_retrieval_rejects_historical_identity_documents_and_other_members(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $oldKtp = $this->insertAsset($fixture, 'ktp', 'synthetic-old-identity-document', false);
        $otherMemberId = $this->insertOtherMember($fixture);
        $otherAsset = $this->insertAsset($fixture, 'ktp', 'synthetic-other-member-document', true, $otherMemberId);

        $this->post(route('operator.identity-verification.previous-photos', $case['case_id']), [
            'reason' => 'Latest photo is insufficient for a human comparison.',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->get(route('operator.identity-verification.asset', [$case['case_id'], $oldKtp]))->assertRedirect();
        $this->get(route('operator.identity-verification.asset', [$case['case_id'], $otherAsset]))->assertRedirect();
    }

    public function test_member_contract_rechecks_case_binding_after_permission_revocation(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $context = $this->identityContext($fixture, $case['case_id'], 'operator.identity.view');
        $contract = app(OperatorIdentityVerificationContract::class);
        $contract->currentView($context, $fixture['siteStableId'], $fixture['scheduleId'], $fixture['bookingId'], $case['case_id']);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.identity.verify')
            ->update(['active' => false]);

        try {
            $contract->currentView($context, $fixture['siteStableId'], $fixture['scheduleId'], $fixture['bookingId'], $case['case_id']);
            $this->fail('A revoked identity permission retained access to the case.');
        } catch (MemberIdentityException $exception) {
            $this->assertStringContainsString('trusted verification case', $exception->getMessage());
        }
    }

    public function test_member_contract_rejects_forged_case_and_unrevealed_previous_photo(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $contract = app(OperatorIdentityVerificationContract::class);

        try {
            $contract->currentView(
                $this->identityContext($fixture, (string) Str::uuid(), 'operator.identity.view'),
                $fixture['siteStableId'],
                $fixture['scheduleId'],
                $fixture['bookingId'],
                (string) Str::uuid(),
            );
            $this->fail('A forged identity case was accepted.');
        } catch (MemberIdentityException $exception) {
            $this->assertStringContainsString('trusted verification case', $exception->getMessage());
        }

        $previous = DB::table('member_verification_assets')
            ->where('member_id', $fixture['memberId'])
            ->where('type', 'profile_photo')
            ->where('is_current', false)
            ->first();
        try {
            $contract->retrieveAsset(
                $this->identityContext($fixture, $case['case_id'], 'operator.identity.asset'),
                $fixture['siteStableId'],
                $fixture['scheduleId'],
                $fixture['bookingId'],
                $case['case_id'],
                (string) $previous->id,
            );
            $this->fail('An unrevealed previous profile photo was retrieved.');
        } catch (MemberIdentityException $exception) {
            $this->assertStringContainsString('requested verification asset', $exception->getMessage());
        }
    }

    public function test_member_contract_rechecks_site_assignment_after_case_start(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        DB::table('operator_site_assignments')
            ->where('operator_profile_id', $fixture['profileId'])
            ->where('operator_site_id', $fixture['siteLocalId'])
            ->update(['active' => false]);

        try {
            app(OperatorIdentityVerificationContract::class)->currentView(
                $this->identityContext($fixture, $case['case_id'], 'operator.identity.view'),
                $fixture['siteStableId'],
                $fixture['scheduleId'],
                $fixture['bookingId'],
                $case['case_id'],
            );
            $this->fail('A revoked site assignment retained identity access.');
        } catch (MemberIdentityException $exception) {
            $this->assertStringContainsString('trusted verification case', $exception->getMessage());
        }
    }

    public function test_matched_denies_pending_current_identity_document(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        DB::table('member_verification_assets')
            ->where('member_id', $fixture['memberId'])
            ->where('type', 'ktp')
            ->update(['review_status' => 'pending', 'is_current' => false]);

        try {
            app(OperatorIdentityVerificationService::class)->decide($case['case_id'], OperatorIdentityVerificationService::MATCHED, null, (string) Str::uuid());
            $this->fail('A matched decision was accepted with a pending identity document.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_evidence_unavailable', $exception->category);
        }

        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'state' => OperatorIdentityVerificationService::OPEN]);
        $this->assertDatabaseCount('operator_identity_verification_events', 1);
    }

    public function test_unavailable_evidence_renders_safe_summary_without_protected_actions(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        DB::table('member_verification_assets')->where('member_id', $fixture['memberId'])->where('type', 'profile_photo')->where('is_current', true)->update(['review_status' => 'rejected', 'is_current' => false]);

        $response = $this->get(route('operator.identity-verification.show', $case['case_id']));
        $response->assertOk()
            ->assertSee('Current identity evidence is unavailable')
            ->assertSee('Report mismatch')
            ->assertSee('Insufficient evidence')
            ->assertSee('Cancel verification')
            ->assertDontSee('Current identity document')
            ->assertDontSee('Verify exact NIK')
            ->assertDontSee('Reveal previous photographs')
            ->assertDontSee('Matched')
            ->assertDontSee('/asset/');
    }

    public function test_unavailable_evidence_renders_safe_summary_when_current_document_is_missing(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        DB::table('member_verification_assets')->where('member_id', $fixture['memberId'])->where('type', 'ktp')->update(['is_current' => false]);

        $this->get(route('operator.identity-verification.show', $case['case_id']))
            ->assertOk()
            ->assertSee('Current identity evidence is unavailable')
            ->assertDontSee('Masked NIK')
            ->assertDontSee('Verify exact NIK');
    }

    public function test_unavailable_evidence_allows_only_failure_decisions_or_cancel_and_keeps_site_blocked(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        DB::table('member_verification_assets')->where('member_id', $fixture['memberId'])->where('type', 'profile_photo')->where('is_current', true)->update(['review_status' => 'rejected', 'is_current' => false]);
        $secondSiteId = $this->addSecondSite($fixture);

        try {
            app(OperatorActiveSiteService::class)->select($secondSiteId);
            $this->fail('An unavailable-evidence case did not block a site switch.');
        } catch (OperatorException $exception) {
            $this->assertSame('active_site_blocked', $exception->category);
        }

        $this->post(route('operator.identity-verification.decision', $case['case_id']), [
            'state' => OperatorIdentityVerificationService::MISMATCH_REPORTED,
            'reason' => 'Evidence unavailable for this visit.',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'state' => OperatorIdentityVerificationService::MISMATCH_REPORTED, 'active_claim_operator_profile_id' => null]);
        app(OperatorActiveSiteService::class)->select($secondSiteId);
        $this->assertSame($secondSiteId, session('operator.active_site_id'));
    }

    public function test_direct_asset_grant_rejects_wrong_age_unsupported_historical_and_cross_member_assets(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $wrongAge = $this->insertAsset($fixture, 'kia', 'synthetic-wrong-age-document', true);
        $unsupported = $this->insertAsset($fixture, 'passport', 'synthetic-unsupported-document', true);
        $historicalIdentity = $this->insertAsset($fixture, 'ktp', 'synthetic-historical-document', false);
        $otherMember = $this->insertOtherMember($fixture);
        $crossMember = $this->insertAsset($fixture, 'ktp', 'synthetic-cross-member-document', true, $otherMember);
        $unrevealedPhoto = (string) DB::table('member_verification_assets')
            ->where('member_id', $fixture['memberId'])
            ->where('type', 'profile_photo')
            ->where('is_current', false)
            ->value('id');
        $context = $this->identityContext($fixture, $case['case_id'], 'operator.identity.asset');
        $service = app(MemberVerificationAssetService::class);

        foreach ([$wrongAge, $unsupported, $historicalIdentity, $crossMember, $unrevealedPhoto] as $assetId) {
            try {
                $service->grantForOperator(
                    $assetId,
                    $context,
                    $fixture['siteStableId'],
                    $fixture['scheduleId'],
                    $fixture['bookingId'],
                    $case['case_id'],
                    'operator-identity',
                    new DateTimeImmutable('+60 seconds'),
                );
                $this->fail('The direct Member grant accepted a forbidden verification asset.');
            } catch (MemberIdentityException $exception) {
                $this->assertStringContainsString('requested verification asset', $exception->getMessage());
            }
        }
    }

    public function test_member_contract_rechecks_persisted_authentication_and_portal_authority(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $context = $this->identityContext($fixture, $case['case_id'], 'operator.identity.view');
        $contract = app(OperatorIdentityVerificationContract::class);
        $arguments = [$context, $fixture['siteStableId'], $fixture['scheduleId'], $fixture['bookingId'], $case['case_id']];
        $contract->currentView(...$arguments);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        try {
            $contract->currentView(...$arguments);
            $this->fail('A revoked portal permission retained Member contract access.');
        } catch (MemberIdentityException) {
            $this->assertTrue(true);
        }
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);

        foreach ([
            ['account_status' => 'suspended'],
            ['login_enabled' => false],
            ['must_change_password' => true],
        ] as $change) {
            DB::table('users')->where('id', $fixture['operator']->id)->update($change);
            try {
                $contract->currentView(...$arguments);
                $this->fail('A non-authenticatable User retained Member contract access.');
            } catch (MemberIdentityException) {
                $this->assertTrue(true);
            }
            DB::table('users')->where('id', $fixture['operator']->id)->update([
                'account_status' => 'active',
                'login_enabled' => true,
                'must_change_password' => false,
            ]);
        }
    }

    public function test_identity_free_text_is_local_and_shared_audit_reason_is_controlled(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $privateReason = '900000000001 private-object-key-should-not-enter-audit';
        app(OperatorIdentityVerificationService::class)->decide($case['case_id'], OperatorIdentityVerificationService::MISMATCH_REPORTED, $privateReason, (string) Str::uuid());

        $audit = json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($privateReason, $audit);
        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'reason' => $privateReason]);
        $this->assertDatabaseHas('operator_identity_verification_events', ['verification_id' => $case['case_id'], 'reason' => $privateReason]);
        $this->assertStringContainsString('identity_mismatch_reported', $audit);
    }

    public function test_one_operator_cannot_start_a_second_open_case_until_the_first_is_terminal(): void
    {
        $fixture = $this->identityFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $case = $this->arriveAndStart($fixture);
        $secondBookingId = (string) Str::uuid();
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = $secondBookingId;
        $booking['status'] = 'arrived';
        DB::table('bookings')->insert($booking);
        $secondArrivalId = (string) Str::uuid();
        DB::table('operator_arrivals')->insert([
            'id' => $secondArrivalId,
            'booking_id' => $secondBookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'occurrence_at' => now(),
            'recorded_at' => now(),
            'operation_id' => (string) Str::uuid(),
            'source' => 'test',
            'status' => 'recorded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(OperatorIdentityVerificationService::class)->start($secondArrivalId, (string) Str::uuid());
            $this->fail('The Operator started two open identity cases.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_operator_claimed', $exception->category);
        }

        $this->assertDatabaseHas('operator_identity_verifications', [
            'id' => $case['case_id'],
            'active_claim_operator_profile_id' => $fixture['profileId'],
            'state' => OperatorIdentityVerificationService::OPEN,
        ]);

        app(OperatorIdentityVerificationService::class)->cancel($case['case_id'], 'Release the first claim for the next arrival.', (string) Str::uuid());
        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'active_claim_operator_profile_id' => null]);
        $secondCase = app(OperatorIdentityVerificationService::class)->start($secondArrivalId, (string) Str::uuid());
        $this->assertSame($fixture['profileId'], $secondCase['operator_profile_id']);

        try {
            DB::table('operator_identity_verifications')->where('id', $case['case_id'])->update([
                'active_claim_operator_profile_id' => $fixture['profileId'],
            ]);
            $this->fail('The database accepted two active claim keys for one Operator.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0] ?? null);
        }
        app(OperatorIdentityVerificationService::class)->cancel($secondCase['case_id'], 'Second claim constraint test complete.', (string) Str::uuid());
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
    private function insertAsset(array $fixture, string $type, string $contents, bool $current, ?string $memberId = null): string
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access', 'operator.identity.verify'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.identity.asset',
        );
        $object = app(PrivateObjectStore::class)->put($contents, $context, 'operator.identity.asset');
        $now = now();
        $assetId = (string) Str::uuid();
        DB::table('member_verification_assets')->insert([
            'id' => $assetId,
            'member_id' => $memberId ?? $fixture['memberId'],
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

        return $assetId;
    }

    /** @param array<string, mixed> $fixture */
    private function insertOtherMember(array $fixture): string
    {
        $user = User::factory()->create(['email' => 'other-member-'.Str::lower(Str::random(8)).'@example.test']);
        $member = (array) DB::table('members')->where('id', $fixture['memberId'])->first();
        $memberId = (string) Str::uuid();
        $member['id'] = $memberId;
        $member['user_id'] = (string) $user->id;
        $member['medical_record_number'] = 'MRN-'.substr($memberId, 0, 8);
        $member['nik_lookup_digest'] = hash('sha256', $memberId);
        $member['encrypted_nik'] = 'other-member-nik';
        $member['name'] = 'Other Synthetic Member';
        DB::table('members')->insert($member);

        return $memberId;
    }

    /** @param array<string, mixed> $fixture */
    private function identityContext(array $fixture, string $caseId, string $purpose): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access', 'operator.identity.verify'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            caseId: LocalId::fromString($caseId),
            purpose: $purpose,
        );
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
