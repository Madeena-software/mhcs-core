<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Events\DomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04dVerifiedCheckInTicketIssueTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_check_in_page_resolves_schedule_through_the_operator_site_reference(): void
    {
        $fixture = $this->readyFixture();

        $this->get(route('operator.check-in.show', $fixture['caseId']))
            ->assertOk()
            ->assertSee('The system will generate the paper ticket number automatically.')
            ->assertDontSee('Paper ticket number');
    }

    public function test_verified_consent_confirmed_booking_is_checked_in_and_gets_one_private_ticket(): void
    {
        $fixture = $this->readyFixture();
        $operationId = (string) Str::uuid();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => '  ab-17  ',
            'operation_id' => $operationId,
        ])->assertRedirect();

        $ticket = DB::table('operator_paper_tickets')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($ticket);
        $this->assertSame('AB-17', $ticket->ticket_number);
        $this->assertSame('checked_in', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertSame(1, DB::table('booking_status_events')->where('booking_id', $fixture['bookingId'])->where('event_type', 'checked_in')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.booking.checked-in')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.paper-ticket.issued')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'member.booking-checked-in')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.paper-ticket-issued')->count());

        $this->get(route('operator.paper-ticket.show', $ticket->id))
            ->assertOk()
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false);

        $this->get(route('operator.paper-ticket.print', $ticket->id))
            ->assertOk()
            ->assertSee('Synthetic Operator Site')
            ->assertSee('AB-17')
            ->assertDontSee($fixture['memberId'])
            ->assertDontSee($fixture['bookingId'])
            ->assertDontSee('Synthetic Arrival Member')
            ->assertDontSee('MRN-');
    }

    public function test_ticket_number_is_generated_when_the_operator_does_not_submit_one(): void
    {
        $fixture = $this->readyFixture();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame('T-001', DB::table('operator_paper_tickets')->where('booking_id', $fixture['bookingId'])->value('ticket_number'));
    }

    public function test_issue_replay_is_idempotent_changed_input_and_second_attempt_fail_closed(): void
    {
        $fixture = $this->readyFixture();
        $operationId = (string) Str::uuid();
        $payload = ['ticket_number' => 'A-1', 'operation_id' => $operationId];

        $this->post(route('operator.check-in.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), $payload)->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'A-2',
            'operation_id' => $operationId,
        ])->assertRedirect();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'A-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseCount('operator_paper_tickets', 1);
        $this->assertSame('A-1', DB::table('operator_paper_tickets')->value('ticket_number'));
        $this->assertSame(1, DB::table('booking_status_events')->where('event_type', 'checked_in')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.paper-ticket-issued')->count());
    }

    public function test_database_rejects_duplicate_number_for_the_same_site_and_schedule(): void
    {
        $fixture = $this->readyFixture();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'DUP-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $secondBookingId = (string) Str::uuid();
        $secondBooking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $secondBooking['id'] = $secondBookingId;
        DB::table('bookings')->insert($secondBooking);

        $this->expectException(QueryException::class);
        DB::table('operator_paper_tickets')->insert([
            'id' => (string) Str::uuid(),
            'booking_id' => $secondBookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'DUP-1',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_missing_consent_invalid_number_and_unmatched_case_do_not_change_booking(): void
    {
        $fixture = $this->matchedFixture(false);
        foreach (['', 'A/B', 'é-1', str_repeat('A', 33)] as $number) {
            $this->post(route('operator.check-in.store', $fixture['caseId']), [
                'ticket_number' => $number,
                'operation_id' => (string) Str::uuid(),
            ])->assertRedirect();
        }
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'A-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_paper_tickets', 0);
        $this->assertSame(0, DB::table('booking_status_events')->where('event_type', 'checked_in')->count());
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.booking-checked-in')->count());

        $unmatched = $this->matchedFixture(false, false);
        $this->post(route('operator.check-in.store', $unmatched['caseId']), [
            'ticket_number' => 'B-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('arrived', DB::table('bookings')->where('id', $unmatched['bookingId'])->value('status'));

        $crossSite = $this->readyFixture();
        $otherSiteId = (string) Str::uuid();
        DB::table('operator_sites')->insert([
            'id' => $otherSiteId,
            'operator_site_id' => 'other-site-'.Str::lower(Str::random(8)),
            'organization_id' => $crossSite['organizationStableId'],
            'organization_name' => 'Synthetic Operator Organization',
            'code' => 'OTHER-'.substr($otherSiteId, 0, 8),
            'display_name' => 'Other Synthetic Site',
            'address_line' => null,
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('operator_identity_verifications')->where('id', $crossSite['caseId'])->update(['operator_site_id' => $otherSiteId]);
        $this->post(route('operator.check-in.store', $crossSite['caseId']), [
            'ticket_number' => 'CROSS-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('arrived', DB::table('bookings')->where('id', $crossSite['bookingId'])->value('status'));
    }

    public function test_account_permission_site_and_shift_revocation_are_rechecked(): void
    {
        foreach (['account', 'permission', 'site', 'shift'] as $revocation) {
            $fixture = $this->readyFixture();
            $ticketCount = DB::table('operator_paper_tickets')->count();
            if ($revocation === 'account') {
                DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
            } elseif ($revocation === 'permission') {
                DB::table('authorization_permission_assignments')->where('user_id', $fixture['operator']->id)->where('permission', 'operator.identity.verify')->update(['active' => false]);
            } elseif ($revocation === 'site') {
                DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
            } else {
                DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'revoked']);
            }

            $this->post(route('operator.check-in.store', $fixture['caseId']), [
                'ticket_number' => 'R-1',
                'operation_id' => (string) Str::uuid(),
            ])->assertRedirect();
            $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
            $this->assertSame($ticketCount, DB::table('operator_paper_tickets')->count());
        }
    }

    public function test_audit_failure_rolls_back_member_transition_and_ticket(): void
    {
        $fixture = $this->readyFixture();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('synthetic audit failure');
            }
        });
        app()->forgetScopedInstances();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'FAIL-AUDIT',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_paper_tickets', 0);
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.booking-checked-in')->count());

    }

    public function test_outbox_failure_rolls_back_member_transition_and_ticket(): void
    {
        $fixture = $this->readyFixture();
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('synthetic outbox failure');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'FAIL-OUTBOX',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_paper_tickets', 0);
    }

    public function test_operator_ticket_write_failure_rolls_back_member_transition(): void
    {
        $fixture = $this->readyFixture();
        DB::table('operator_paper_tickets')->insert([
            'id' => (string) Str::uuid(),
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TAKEN',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'NEW-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('arrived', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertSame(1, DB::table('operator_paper_tickets')->count());
        $this->assertSame(0, DB::table('outbox_messages')->where('event_name', 'member.booking-checked-in')->count());
    }

    public function test_reprint_is_a_distinct_idempotent_private_request_and_revocation_closes_print(): void
    {
        $fixture = $this->readyFixture();
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'ticket_number' => 'PRINT-1',
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $ticketId = (string) DB::table('operator_paper_tickets')->value('id');
        $operationId = (string) Str::uuid();

        $this->post(route('operator.paper-ticket.reprint', $ticketId), ['operation_id' => $operationId])->assertRedirect(route('operator.paper-ticket.print', $ticketId));
        $this->post(route('operator.paper-ticket.reprint', $ticketId), ['operation_id' => $operationId])->assertRedirect(route('operator.paper-ticket.print', $ticketId));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.paper-ticket.reprint-requested')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.paper-ticket-reprint-requested')->count());

        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);
        $this->get(route('operator.paper-ticket.print', $ticketId))->assertRedirect(route('operator.dashboard'));
    }

    /** @return array<string, mixed> */
    private function readyFixture(): array
    {
        return $this->matchedFixture(true);
    }

    /** @return array<string, mixed> */
    private function matchedFixture(bool $consent, bool $matched = true): array
    {
        $fixture = $this->operatorFixture(false);
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'check-in-'.$fixture['memberId']),
        ]);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['operator']->id,
            'permission' => 'operator.identity.verify',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertIdentityAssets($fixture);
        $this->startOperatorSession($fixture);
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');
        $case = app(OperatorIdentityVerificationService::class)->start($arrivalId, (string) Str::uuid());
        if ($matched) {
            app(OperatorIdentityVerificationService::class)->decide($case['case_id'], 'matched', null, (string) Str::uuid());
        }
        if ($consent && $matched) {
            $this->post(route('operator.paper-consent.store', $case['case_id']), [
                'form_name' => 'Informed Consent',
                'form_version' => 'V1',
                'signer_type' => 'member',
                'signature_confirmed' => '1',
                'signed_at' => '2040-01-10',
                'operation_id' => (string) Str::uuid(),
                'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic signed paper\n%%EOF"),
            ])->assertRedirect();
        }

        return [...$fixture, 'caseId' => $case['case_id']];
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
}
