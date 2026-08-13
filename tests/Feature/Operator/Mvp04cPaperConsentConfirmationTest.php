<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04cPaperConsentConfirmationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.asset_grants' => ['max_ttl_seconds' => 300, 'audiences' => ['operator-identity']]]);
        Storage::fake('local');
    }

    public function test_missing_private_scan_is_rejected_without_side_effects(): void
    {
        $fixture = $this->matchedFixture();
        $operationId = (string) Str::uuid();

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $this->payloadWithoutScan($operationId))
            ->assertRedirect()
            ->assertSessionHasErrors('scan');

        $this->assertDatabaseCount('examination_consents', 0);
        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertSame(0, DB::table('audit_events')->where('action', 'member.booking.paper-consent.confirmed')->count());
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.paper-consent-confirmed')->count());
    }

    public function test_valid_private_upload_is_plain_private_and_not_in_shared_evidence(): void
    {
        $fixture = $this->matchedFixture();
        $plain = "%PDF-1.7\nsynthetic signed paper\n%%EOF";
        $operationId = (string) Str::uuid();

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            ...$this->payload($operationId),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', $plain),
        ])->assertRedirect(route('operator.paper-consent.show', $fixture['caseId']));

        $consent = DB::table('examination_consents')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($consent);
        $this->assertSame('application/pdf', $consent->private_scan_format);
        $this->assertNotNull($consent->private_scan_object_key);
        $this->assertSame($plain, (string) Storage::disk('local')->get($consent->private_scan_object_key));
        $this->assertStringNotContainsString((string) $consent->private_scan_object_key, json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($plain, json_encode(DB::table('outbox_messages')->get(), JSON_THROW_ON_ERROR));
        $this->get(route('operator.paper-consent.show', $fixture['caseId']))
            ->assertOk()
            ->assertSee('Disimpan secara privat')
            ->assertDontSee($plain)
            ->assertDontSee((string) $consent->private_scan_object_key);
    }

    public function test_form_uses_today_date_picker_and_100_mb_upload_copy(): void
    {
        $fixture = $this->matchedFixture();

        $this->get(route('operator.paper-consent.show', $fixture['caseId']))
            ->assertOk()
            ->assertSee('Tanggal penandatanganan sebenarnya')
            ->assertSee('type="date"', false)
            ->assertSee('value="'.now()->format('Y-m-d').'"', false)
            ->assertSee('maksimal 100 MB');
    }

    public function test_malformed_private_upload_is_checked_by_the_service_after_request_limit(): void
    {
        $fixture = $this->matchedFixture();

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            ...$this->payload((string) Str::uuid()),
            'scan' => UploadedFile::fake()->create('signed-consent.pdf', 15 * 1024, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasErrors('consent')->assertSessionDoesntHaveErrors('scan');
    }

    public function test_same_input_replays_but_changed_input_and_duplicate_booking_fail_closed(): void
    {
        $fixture = $this->matchedFixture();
        $operationId = (string) Str::uuid();
        $payload = $this->payload($operationId);

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            ...$payload,
            'signed_at' => '2040-01-11',
        ])->assertRedirect();
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $this->payload((string) Str::uuid()))
            ->assertRedirect();

        $this->assertDatabaseCount('examination_consents', 1);
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'member.paper-consent-confirmed')->count());
        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
    }

    public function test_form_signer_signature_date_and_upload_boundaries_are_rejected_without_side_effects(): void
    {
        $cases = [
            ['form_name' => 'Other Consent'],
            ['form_version' => 'V2'],
            ['signer_type' => 'representative'],
            ['signature_confirmed' => '0'],
            ['signed_at' => '2040-01-10T09:59:00+07:00'],
            ['scan' => UploadedFile::fake()->createWithContent('signed.pdf', 'plain text')],
            ['scan' => UploadedFile::fake()->create('signed.pdf', 102401, 'application/pdf')],
        ];

        foreach ($cases as $change) {
            $fixture = $this->matchedFixture();
            $outboxBefore = DB::table('outbox_messages')->count();
            $payload = [...$this->payload((string) Str::uuid()), ...$change];
            $this->post(route('operator.paper-consent.store', $fixture['caseId']), $payload)->assertRedirect();
            $this->assertDatabaseCount('examination_consents', 0);
            $this->assertSame($outboxBefore, DB::table('outbox_messages')->count());
            $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        }
    }

    public function test_permission_assignment_and_stale_assignment_are_rechecked(): void
    {
        $fixture = $this->matchedFixture();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.identity.verify')
            ->update(['active' => false]);

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $this->payload((string) Str::uuid()))
            ->assertRedirect();
        $this->assertDatabaseCount('examination_consents', 0);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.identity.verify')
            ->update(['active' => true]);
        DB::table('operator_site_assignments')
            ->where('operator_profile_id', $fixture['profileId'])
            ->update(['active' => false]);

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), $this->payload((string) Str::uuid()))
            ->assertRedirect();
        $this->assertDatabaseCount('examination_consents', 0);
    }

    public function test_nonmatched_case_and_failed_transaction_do_not_create_consent_or_leave_upload(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->makeMemberDigestUnique($fixture);
        $this->grantIdentityPermission($fixture);
        $this->startOperatorSession($fixture);
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');
        $case = app(OperatorIdentityVerificationService::class)->start($arrivalId, (string) Str::uuid());

        $this->get(route('operator.paper-consent.show', $case['case_id']))->assertRedirect(route('operator.verification-worklist'));
        $this->assertDatabaseCount('examination_consents', 0);

        $fixture = $this->matchedFixture();
        $filesBeforeFailure = Storage::disk('local')->allFiles();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic audit failure');
            }
        });
        app()->forgetScopedInstances();
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            ...$this->payload((string) Str::uuid()),
            'scan' => UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.7\nsynthetic signed paper\n%%EOF"),
        ])->assertRedirect();

        $this->assertDatabaseCount('examination_consents', 0);
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.paper-consent-confirmed')->count());
        $this->assertSame($filesBeforeFailure, Storage::disk('local')->allFiles());
    }

    /** @return array<string, mixed> */
    private function matchedFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $this->makeMemberDigestUnique($fixture);
        $this->grantIdentityPermission($fixture);
        $this->insertIdentityAssets($fixture);
        $this->startOperatorSession($fixture);
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');
        $case = app(OperatorIdentityVerificationService::class)->start($arrivalId, (string) Str::uuid());
        app(OperatorIdentityVerificationService::class)->decide($case['case_id'], 'matched', null, (string) Str::uuid());

        return [...$fixture, 'caseId' => $case['case_id']];
    }

    /** @param array<string, mixed> $fixture */
    private function makeMemberDigestUnique(array $fixture): void
    {
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'paper-consent-'.$fixture['memberId']),
        ]);
    }

    /** @param array<string, mixed> $fixture */
    private function grantIdentityPermission(array $fixture): void
    {
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['operator']->id,
            'permission' => 'operator.identity.verify',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $fixture */
    private function insertIdentityAssets(array $fixture): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access', 'operator.identity.verify'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.identity.asset',
        );
        foreach ([['ktp', 'synthetic-identity-document'], ['profile_photo', 'synthetic-latest-profile']] as [$type, $contents]) {
            $object = app(PrivateObjectStore::class)->put($contents, $context, 'operator.identity.asset');
            DB::table('member_verification_assets')->insert([
                'id' => (string) Str::uuid(),
                'member_id' => $fixture['memberId'],
                'type' => $type,
                'private_object_key' => (string) $object->key,
                'checksum' => $object->checksum,
                'bytes' => $object->bytes,
                'format' => 'image/jpeg',
                'review_status' => 'approved',
                'is_current' => true,
                'uploaded_by_user_id' => $fixture['operator']->id,
                'reviewed_by_user_id' => $fixture['operator']->id,
                'reviewed_at' => now(),
                'replaces_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $fixture */
    private function startOperatorSession(array $fixture): void
    {
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
    }

    /** @return array<string, string> */
    private function payload(string $operationId): array
    {
        return [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => $operationId,
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic signed paper\n%%EOF"),
        ];
    }

    /** @return array<string, mixed> */
    private function payloadWithoutScan(string $operationId): array
    {
        $payload = $this->payload($operationId);
        unset($payload['scan']);

        return $payload;
    }
}
