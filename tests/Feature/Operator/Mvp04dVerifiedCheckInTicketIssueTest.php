<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorCheckInTicketService;
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
use PDO;
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
            ->assertSee('Sistem akan membuat nomor tiket kertas secara otomatis.')
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
        $operationId = (string) Str::uuid();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => $operationId,
        ])->assertRedirect();

        $ticket = DB::table('operator_paper_tickets')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($ticket);
        $this->assertSame('T-001', $ticket->ticket_number);
        $this->assertSame('checked_in', DB::table('bookings')->where('id', $fixture['bookingId'])->value('status'));
        $this->assertDatabaseCount('operator_queue_admissions', 1);
        $this->assertDatabaseCount('operator_queue_admission_history', 1);
        $this->assertSame(2, DB::table('audit_events')->whereIn('action', ['operator.paper-ticket.issued', 'operator.queue-admission.created'])->count());
        $this->assertSame(2, DB::table('outbox_messages')->whereIn('event_name', ['operator.paper-ticket-issued', 'operator.queue-admission-created'])->count());
        $this->assertDatabaseHas('idempotent_consumptions', [
            'message_id' => $operationId,
            'consumer' => OperatorCheckInTicketService::PURPOSE,
            'status' => 'handled',
        ]);
    }

    public function test_mysql_concurrent_blank_ticket_issue_allocates_two_numbers_and_keeps_each_check_in_atomic(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The automatic ticket allocation concurrency probe requires MySQL and proc_open.');
        }

        $pdo = $this->mysqlPdo();
        $fixture = $this->mysqlConcurrentFixture($pdo);

        try {
            $worker = <<<'PHP'
$root = getcwd();
$stage = 'bootstrap';
try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $stage = 'auth';
    if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
        throw new \RuntimeException('worker-driver-mismatch');
    }
    $user = \App\Models\User::query()->findOrFail($input['operator_id']);
    \Illuminate\Support\Facades\Auth::guard()->setUser($user);
    $stage = 'session';
    session()->put('operator.active_site_id', $input['site_id']);
    echo "ready\n";
    flush();
    fgets(STDIN);
    $stage = 'issue';
    $result = app(\App\Modules\Operator\Application\Services\OperatorCheckInTicketService::class)->issue($input['case_id'], '', $input['operation_id']);
    echo 'success:'.$result['ticket_number'];
} catch (\Throwable $exception) {
    fwrite(STDERR, 'worker-'.$stage.'-'.(new \ReflectionClass($exception))->getShortName());
    exit(1);
}
PHP;
            $processes = [];
            foreach ($fixture['operatorIds'] as $index => $operatorId) {
                $input = base64_encode(json_encode([
                    'operator_id' => $operatorId,
                    'site_id' => $fixture['siteId'],
                    'case_id' => $fixture['caseIds'][$index],
                    'operation_id' => $fixture['operations'][$index],
                ], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, '-r', $worker, $input], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start the automatic ticket allocation concurrency probe.');
                }
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [$process, $pipes]) {
                $ready = trim((string) fgets($pipes[1]));
                if ($ready !== 'ready') {
                    $error = trim((string) stream_get_contents($pipes[2]));
                    throw new RuntimeException('Automatic ticket allocation worker did not become ready: '.($error === '' ? 'worker-unknown' : $error));
                }
            }

            foreach ($processes as [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fclose($pipes[0]);
            }

            $outcomes = [];
            foreach ($processes as [$process, $pipes]) {
                $outcomes[] = trim((string) stream_get_contents($pipes[1]));
                $error = trim((string) stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
            }
            sort($outcomes);
            $this->assertSame(['success:T-001', 'success:T-002'], $outcomes);

            $tickets = $pdo->query("select ticket_number from operator_paper_tickets where member_schedule_id = '{$fixture['scheduleId']}' order by ticket_number")->fetchAll(PDO::FETCH_COLUMN);
            $this->assertSame(['T-001', 'T-002'], $tickets);
            foreach ($fixture['bookingIds'] as $bookingId) {
                $this->assertSame('checked_in', $pdo->query("select status from bookings where id = '{$bookingId}'")->fetchColumn());
                $this->assertSame(1, (int) $pdo->query("select count(*) from operator_paper_tickets where booking_id = '{$bookingId}'")->fetchColumn());
                $this->assertSame(1, (int) $pdo->query("select count(*) from operator_queue_admissions where operator_paper_ticket_id in (select id from operator_paper_tickets where booking_id = '{$bookingId}')")->fetchColumn());
                $this->assertSame(1, (int) $pdo->query("select count(*) from operator_queue_admission_history where operator_queue_admission_id in (select id from operator_queue_admissions where operator_paper_ticket_id in (select id from operator_paper_tickets where booking_id = '{$bookingId}'))")->fetchColumn());
                $this->assertSame(1, (int) $pdo->query("select count(*) from booking_status_events where booking_id = '{$bookingId}' and event_type = 'checked_in'")->fetchColumn());
            }
            $this->assertSame(4, (int) $pdo->query("select count(*) from audit_events where action in ('operator.paper-ticket.issued', 'operator.queue-admission.created') and metadata like '%{$fixture['scheduleId']}%'")->fetchColumn());
            $this->assertSame(4, (int) $pdo->query("select count(*) from outbox_messages where event_name in ('operator.paper-ticket-issued', 'operator.queue-admission-created') and payload like '%{$fixture['scheduleId']}%'")->fetchColumn());
            $this->assertSame(2, (int) $pdo->query("select count(*) from idempotent_consumptions where consumer = 'operator.check-in.issue' and message_id in ('{$fixture['operations'][0]}', '{$fixture['operations'][1]}') and status = 'handled'")->fetchColumn());
            fwrite(STDOUT, 'MySQL automatic allocation: outcomes='.implode(',', $outcomes).'; tickets='.implode(',', $tickets).'; issue/queue audit records=4; issue/queue outbox records=4; handled idempotency records=2'.PHP_EOL);
        } finally {
            $this->cleanupMysqlConcurrentFixture($pdo, $fixture);
        }
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

    /** @return array<string, mixed> */
    private function mysqlConcurrentFixture(PDO $pdo): array
    {
        $now = '2026-08-12 03:10:00';
        $operatorIds = [(string) Str::uuid(), (string) Str::uuid()];
        $memberUserIds = [(string) Str::uuid(), (string) Str::uuid()];
        $memberIds = [(string) Str::uuid(), (string) Str::uuid()];
        $profileIds = [(string) Str::uuid(), (string) Str::uuid()];
        $bookingIds = [(string) Str::uuid(), (string) Str::uuid()];
        $arrivalIds = [(string) Str::uuid(), (string) Str::uuid()];
        $caseIds = [(string) Str::uuid(), (string) Str::uuid()];
        $operations = [(string) Str::uuid(), (string) Str::uuid()];
        $siteId = (string) Str::uuid();
        $siteReferenceId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $rateId = (string) Str::uuid();
        $eligibleId = (string) Str::uuid();
        $stableSiteId = 'operator-concurrency-site-'.substr($siteId, 0, 8);
        $stableOrganizationId = 'operator-concurrency-org-'.substr($organizationId, 0, 8);
        $serviceCode = 'RAD-CONCURRENCY';

        foreach ([...$operatorIds, ...$memberUserIds] as $index => $userId) {
            $this->insertPdo($pdo, 'insert into users (id, email, email_verified_at, password, remember_token, account_status, login_enabled, must_change_password, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$userId, 'operator-ticket-'.$index.'-'.substr($userId, 0, 8).'@example.test', null, 'hash', null, 'active', 1, 0, $now, $now]);
        }
        $this->insertPdo($pdo, 'insert into operator_organization_refs (id, operator_organization_id, name, source_version, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [$organizationId, $stableOrganizationId, 'Synthetic Operator Organization', '1', 1, $now, $now]);
        $this->insertPdo($pdo, 'insert into examination_site_refs (id, operator_site_id, operator_organization_ref_id, code, display_name, timezone, source_version, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$siteReferenceId, $stableSiteId, $organizationId, 'CONCURRENCY-SITE', 'Synthetic Operator Site', 'Asia/Jakarta', '1', 1, $now, $now]);
        $this->insertPdo($pdo, 'insert into operator_sites (id, operator_site_id, organization_id, organization_name, code, display_name, address_line, timezone, active, source_version, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$siteId, $stableSiteId, $stableOrganizationId, 'Synthetic Operator Organization', 'CONCURRENCY-SITE', 'Synthetic Operator Site', null, 'Asia/Jakarta', 1, '1', $now, $now]);
        $this->insertPdo($pdo, 'insert into service_offerings (id, code, name, includes_ai, includes_doctor, point_price, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$serviceId, $serviceCode, 'Synthetic Radiography', 1, 0, '2.5000', 1, $now, $now]);
        $this->insertPdo($pdo, 'insert into point_exchange_rates (id, rupiah_per_point, status, effective_at, configured_by_admin_id, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [$rateId, 10000, 'active', $now, null, $now, $now]);
        $this->insertPdo($pdo, 'insert into shift_schedules (id, examination_site_id, service_offering_id, starts_at, ends_at, quota, status, eligible_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$scheduleId, $siteReferenceId, $serviceId, '2040-01-10 03:00:00', '2040-01-10 04:00:00', 5, 'open', $now, $now, $now]);
        $this->insertPdo($pdo, 'insert into operator_eligible_shifts (id, member_schedule_id, operator_site_id, schedule_starts_at, schedule_ends_at, confirmed_count_at_eligibility, quota, event_version, source_event_id, eligible_at, sync_status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$eligibleId, $scheduleId, $stableSiteId, '2040-01-10 03:00:00', '2040-01-10 04:00:00', 5, 5, 1, 'test:operator-concurrency:'.$scheduleId, $now, 'eligible', $now, $now]);

        foreach ($operatorIds as $index => $operatorId) {
            $profileId = $profileIds[$index];
            $this->insertPdo($pdo, 'insert into operator_profiles (id, user_id, display_name, employee_code, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [$profileId, $operatorId, 'Synthetic Concurrent Operator '.($index + 1), 'CONC-OPR-'.($index + 1), 1, $now, $now]);
            $this->insertPdo($pdo, 'insert into operator_site_assignments (id, operator_profile_id, operator_site_id, active, assigned_by_user_id, assigned_at, revoked_at, reason, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $profileId, $siteId, 1, $operatorId, $now, null, null, $now, $now]);
            $this->insertPdo($pdo, 'insert into operator_shift_assignments (id, operator_eligible_shift_id, operator_profile_id, assigned_by_user_id, status, assigned_at, revoked_at, reason, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $eligibleId, $profileId, $operatorId, 'active', $now, null, null, $now, $now]);
            $this->insertPdo($pdo, 'insert into authorization_role_assignments (id, user_id, role, assigned_by_user_id, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $operatorId, 'operator', null, 1, $now, $now]);
            foreach (['operator.portal.access', 'operator.identity.verify'] as $permission) {
                $this->insertPdo($pdo, 'insert into authorization_permission_assignments (id, user_id, permission, assigned_by_user_id, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $operatorId, $permission, null, 1, $now, $now]);
            }
        }

        foreach ($memberIds as $index => $memberId) {
            $memberUserId = $memberUserIds[$index];
            $bookingId = $bookingIds[$index];
            $profileId = $profileIds[$index];
            $this->insertPdo($pdo, 'insert into members (id, user_id, family_id, medical_record_number, identity_status, identity_document_type, encrypted_nik, nik_lookup_digest, name, birth_date, administrative_gender, registration_source, phone, created_at, updated_at, current_address, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$memberId, $memberUserId, null, 'MRN-CONC-'.($index + 1), 'verified', 'ktp', 'synthetic-encrypted-identifier', hash('sha256', 'synthetic-concurrent-'.$memberId), 'Synthetic Concurrent Member '.($index + 1), '1988-01-10', 'unspecified', 'administrator', null, $now, $now, 'Synthetic address', 'Synthetic contact', 'Sibling', '0800000000']);
            $this->insertPdo($pdo, 'insert into bookings (id, member_id, shift_schedule_id, service_offering_id, examination_site_id_snapshot, booking_type, funding_source, status, service_code_snapshot, point_cost_snapshot, point_exchange_rate_id, includes_ai_snapshot, includes_doctor_snapshot, site_code_snapshot, site_name_snapshot, site_timezone_snapshot, created_at, confirmed_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$bookingId, $memberId, $scheduleId, $serviceId, $siteReferenceId, 'b2c', 'personal', 'arrived', $serviceCode, '2.5000', $rateId, 1, 0, 'CONCURRENCY-SITE', 'Synthetic Operator Site', 'Asia/Jakarta', $now, $now, $now]);
            $this->insertPdo($pdo, 'insert into point_ledger_entries (id, member_id, booking_id, funding_source, entry_type, point_delta, source_reference, reverses_id, created_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $memberId, $bookingId, 'personal', 'charge', '-2.5000', 'test:operator-concurrency:'.$bookingId, null, $now]);
            $arrivalId = $arrivalIds[$index];
            $caseId = $caseIds[$index];
            $this->insertPdo($pdo, 'insert into operator_arrivals (id, booking_id, member_schedule_id, operator_site_id, operator_profile_id, occurrence_at, recorded_at, operation_id, source, status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$arrivalId, $bookingId, $scheduleId, $siteId, $profileId, $now, $now, 'test:operator-concurrency:arrival:'.$bookingId, 'operator.portal', 'recorded', $now, $now]);
            $this->insertPdo($pdo, 'insert into operator_identity_verifications (id, arrival_id, booking_id, member_schedule_id, operator_site_id, operator_profile_id, state, started_at, decided_at, reason_category, reason, operation_id, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$caseId, $arrivalId, $bookingId, $scheduleId, $siteId, $profileId, 'matched', $now, $now, null, null, 'test:operator-concurrency:identity:'.$bookingId, $now, $now]);
            $this->insertPdo($pdo, 'insert into operator_identity_verification_events (id, verification_id, event_type, from_state, to_state, reason, operation_id, occurred_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $caseId, 'decided', 'open', 'matched', null, 'test:operator-concurrency:identity-event:'.$bookingId, $now, $now, $now]);
            $this->insertPdo($pdo, 'insert into examination_consents (id, member_id, booking_id, examination_site_id, operator_site_id, form_name, form_version, signer_type, signer_member_id, signature_confirmed, signed_at, confirmed_by_operator_id, recorded_at, idempotency_id, private_scan_object_key, private_scan_checksum, private_scan_bytes, private_scan_format, status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $memberId, $bookingId, $siteReferenceId, $stableSiteId, 'Informed Consent', 'V1', 'member', $memberId, 1, $now, $profileId, $now, 'test:operator-concurrency:consent:'.$bookingId, 'synthetic-private-scan-'.$bookingId, hash('sha256', 'synthetic-consent'), 32, 'application/pdf', 'confirmed', $now, $now]);
        }

        return compact('siteId', 'scheduleId', 'operatorIds', 'caseIds', 'operations', 'bookingIds', 'memberIds', 'memberUserIds', 'profileIds', 'arrivalIds', 'siteReferenceId', 'organizationId', 'serviceId', 'rateId', 'eligibleId');
    }

    /** @param array<string, mixed> $fixture */
    private function cleanupMysqlConcurrentFixture(PDO $pdo, array $fixture): void
    {
        $this->insertPdo($pdo, 'delete from audit_events where metadata like ?', ['%'.$fixture['scheduleId'].'%']);
        $this->insertPdo($pdo, 'delete from outbox_messages where payload like ?', ['%'.$fixture['scheduleId'].'%']);
        $this->insertPdo($pdo, 'delete from idempotent_consumptions where consumer = ? and message_id in (?, ?)', [OperatorCheckInTicketService::PURPOSE, ...$fixture['operations']]);
        $this->insertPdo($pdo, 'delete from operator_queue_admission_history where operator_queue_admission_id in (select id from operator_queue_admissions where member_schedule_id = ?)', [$fixture['scheduleId']]);
        $this->insertPdo($pdo, 'delete from operator_queue_admissions where member_schedule_id = ?', [$fixture['scheduleId']]);
        $this->insertPdo($pdo, 'delete from operator_paper_tickets where member_schedule_id = ?', [$fixture['scheduleId']]);
        $this->insertPdo($pdo, 'delete from booking_status_events where booking_id in (?, ?)', $fixture['bookingIds']);
        $this->insertPdo($pdo, 'delete from examination_consents where booking_id in (?, ?)', $fixture['bookingIds']);
        $this->insertPdo($pdo, 'delete from operator_identity_verification_events where verification_id in (?, ?)', $fixture['caseIds']);
        $this->insertPdo($pdo, 'delete from operator_identity_verifications where id in (?, ?)', $fixture['caseIds']);
        $this->insertPdo($pdo, 'delete from operator_arrivals where id in (?, ?)', $fixture['arrivalIds']);
        $this->insertPdo($pdo, 'delete from point_ledger_entries where booking_id in (?, ?)', $fixture['bookingIds']);
        $this->insertPdo($pdo, 'delete from bookings where id in (?, ?)', $fixture['bookingIds']);
        $this->insertPdo($pdo, 'delete from members where id in (?, ?)', $fixture['memberIds']);
        $this->insertPdo($pdo, 'delete from operator_shift_assignments where operator_eligible_shift_id = ?', [$fixture['eligibleId']]);
        $this->insertPdo($pdo, 'delete from operator_site_assignments where operator_site_id = ?', [$fixture['siteId']]);
        $this->insertPdo($pdo, 'delete from authorization_permission_assignments where user_id in (?, ?)', $fixture['operatorIds']);
        $this->insertPdo($pdo, 'delete from authorization_role_assignments where user_id in (?, ?)', $fixture['operatorIds']);
        $this->insertPdo($pdo, 'delete from operator_profiles where id in (?, ?)', $fixture['profileIds']);
        $this->insertPdo($pdo, 'delete from operator_eligible_shifts where id = ?', [$fixture['eligibleId']]);
        $this->insertPdo($pdo, 'delete from shift_schedules where id = ?', [$fixture['scheduleId']]);
        $this->insertPdo($pdo, 'delete from service_offerings where id = ?', [$fixture['serviceId']]);
        $this->insertPdo($pdo, 'delete from point_exchange_rates where id = ?', [$fixture['rateId']]);
        $this->insertPdo($pdo, 'delete from operator_sites where id = ?', [$fixture['siteId']]);
        $this->insertPdo($pdo, 'delete from examination_site_refs where id = ?', [$fixture['siteReferenceId']]);
        $this->insertPdo($pdo, 'delete from operator_organization_refs where id = ?', [$fixture['organizationId']]);
        $this->insertPdo($pdo, 'delete from users where id in (?, ?, ?, ?)', [...$fixture['operatorIds'], ...$fixture['memberUserIds']]);
    }

    private function insertPdo(PDO $pdo, string $sql, array $values): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($values);
    }

    private function mysqlPdo(): PDO
    {
        $config = config('database.connections.mysql');

        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
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
