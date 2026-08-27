<?php

declare(strict_types=1);

namespace App\Console\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\PrestigeUploadDiagnosticMemberRegistrationData;
use App\Modules\Member\Application\Services\MemberContextResolver;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use App\Shared\Validation\NonclinicalValidationContext;
use App\Shared\Validation\NonclinicalValidationContextProvider;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class PrestigeUploadDiagnosticProvisioningService
{
    private const OPERATION = NonclinicalValidationContext::PRESTIGE_KEY;

    private const SITE = 'site-prestige';

    private const SERVICE = 'SYN-CHEST-B';

    /** @var list<array{key:string,email:string,name:string}> */
    private const SUBJECTS = [
        ['key' => 'gbsuparta', 'email' => 'gbsuparta@ugm.ac.id', 'name' => 'gbsuparta'],
        ['key' => 'ipang', 'email' => 'ipang.prestige@madeena-xray.com', 'name' => 'ipang'],
    ];

    public function __construct(
        private MemberRegistrationService $registration,
        private Mvp03BookingService $booking,
        private MemberContextResolver $members,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array<string, scalar> */
    public function provision(): array
    {
        if (! app()->environment(['testing', 'production'])) {
            throw new RuntimeException('Prestige diagnostic provisioning is unavailable in this environment.');
        }

        return DB::transaction(function (): array {
            $site = DB::table('operator_sites')->where('operator_site_id', self::SITE)->where('organization_id', 'org-prestige')->where('code', 'PRES-01')->where('display_name', 'Rumah Skrining CV Prestige')->where('timezone', 'Asia/Jakarta')->where('active', true)->first();
            $siteRef = DB::table('examination_site_refs')->where('operator_site_id', self::SITE)->where('organization_id', 'org-prestige')->where('code', 'PRES-01')->where('display_name', 'Rumah Skrining CV Prestige')->where('timezone', 'Asia/Jakarta')->where('active', true)->first();
            $service = DB::table('service_offerings')->where('code', self::SERVICE)->where('active', true)->first();
            if ($site === null || $siteRef === null || $service === null) {
                throw new RuntimeException('The required Prestige site or SYN-CHEST-B offering is inconsistent.');
            }

            $this->assertAccountSet();
            $this->assertMemberSet();
            $canonical = $this->canonicalState();

            $schedule = $this->schedule((string) $siteRef->id, (string) $service->id);
            $states = [];
            foreach (self::SUBJECTS as $subject) {
                $provider = new NonclinicalValidationContextProvider(NonclinicalValidationContext::PRESTIGE_KEY);
                app()->instance(AuthenticatedContextProvider::class, $provider);
                $provider->accountProvisioning();
                $accountExisted = User::query()->where('email', $subject['email'])->exists();
                $account = $this->account($subject);
                $provider->memberRegistration();
                $member = $this->registration->registerNonclinicalValidation(new PrestigeUploadDiagnosticMemberRegistrationData(
                    self::OPERATION.':'.$subject['key'].':member-v1',
                    $account->id,
                    NonclinicalValidationContext::PRESTIGE_KEY,
                    NonclinicalValidationContext::PRESTIGE_MARKER_NAMESPACE,
                    $subject['key'],
                    $subject['name'],
                ));
                $memberModel = Member::query()->findOrFail($member->memberId);
                if (! $this->members->isExactPrestigeUploadDiagnosticIdentity($memberModel)) {
                    throw new RuntimeException('The Prestige diagnostic Member state is inconsistent.');
                }
                $this->fund($memberModel, $schedule, (string) $service->point_price, $subject['key'], $provider);
                $provider->bindValidationMember($account->id);
                $provider->memberBooking();
                Auth::guard('web')->setUser($account);
                $existing = DB::table('bookings')->where('member_id', $memberModel->id)->get();
                if ($existing->count() > 1 || ($existing->isNotEmpty() && ($existing->first()->shift_schedule_id !== $schedule->id || $existing->first()->status !== 'confirmed'))) {
                    throw new RuntimeException('The Prestige diagnostic booking state is inconsistent.');
                }
                $booking = $existing->isEmpty() ? $this->booking->createForCurrentMember($schedule->id, self::OPERATION.':'.$subject['key'].':booking-v1', (string) $service->point_price) : ['status' => 'confirmed'];
                Auth::guard('web')->logout();
                $states[$subject['key'].'_account_state'] = $accountExisted ? 'EXISTING_VALID' : 'CREATED';
                $states[$subject['key'].'_member_state'] = $member->replayed ? 'EXISTING_VALID' : 'CREATED';
                $states[$subject['key'].'_booking_state'] = $existing->isNotEmpty() ? 'EXISTING_VALID' : ((string) $booking['status'] === 'confirmed' ? 'CREATED' : 'INVALID');
            }

            $this->eligibleAndOperators($schedule, (string) $site->id);
            if ($canonical !== $this->canonicalState()) {
                throw new RuntimeException('The canonical Prestige employee or SYN-CHEST-A state changed.');
            }

            return $states + [
                'production_revision_verified' => true,
                'canonical_prestige_employee_count' => DB::table('members')->where('registration_source', 'administrator')->where('identity_status', 'verified')->whereNotNull('encrypted_nik')->count(),
                'canonical_syn_chest_a_unchanged' => true,
                'additional_validation_member_count' => DB::table('member_external_identifiers')->where('namespace', NonclinicalValidationContext::PRESTIGE_MARKER_NAMESPACE)->whereIn('value', ['gbsuparta', 'ipang'])->count(),
                'diagnostic_site' => 'PRES-01', 'diagnostic_service' => self::SERVICE, 'diagnostic_schedule_quota' => 2,
                'diagnostic_schedule_window' => $this->window($schedule), 'prestige_operator_count' => 5, 'diagnostic_shift_assignment_count' => 5,
                'arrival_state' => 'NOT_EXECUTED', 'identity_verification_state' => 'NOT_EXECUTED', 'basic_examination_state' => 'NOT_EXECUTED', 'capture_present' => false,
                'provisioning' => 'PASS',
            ];
        });
    }

    private function account(array $subject): User
    {
        $users = User::query()->where('email', $subject['email'])->lockForUpdate()->get();
        if ($users->count() > 1) {
            throw new RuntimeException('A fixed Prestige diagnostic account is duplicated.');
        }
        $user = $users->first();
        if ($user !== null) {
            if ($user->account_status !== 'active' || ! $user->login_enabled || $user->must_change_password || DB::table('authorization_role_assignments')->where('user_id', $user->id)->where('active', true)->exists() || DB::table('authorization_permission_assignments')->where('user_id', $user->id)->where('active', true)->exists() || ! $this->owned($user->id, $subject['key'])) {
                throw new RuntimeException('The existing Prestige diagnostic account is not owned and exact.');
            }

            return $user;
        }
        $now = $this->clock->now();
        $id = (string) Str::uuid();
        DB::table('users')->insert(['id' => $id, 'email' => $subject['email'], 'email_verified_at' => null, 'password' => Hash::make(bin2hex(random_bytes(32))), 'remember_token' => null, 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current(), 'production.prestige-upload-diagnostic.account.provisioned', 'system', 'success', $now, User::class, $id, metadata: ['validation_context' => self::OPERATION, 'subject' => $subject['key'], 'nonclinical' => true]));

        return User::query()->findOrFail($id);
    }

    private function assertAccountSet(): void
    {
        $found = User::query()->whereIn('email', array_column(self::SUBJECTS, 'email'))->lockForUpdate()->get();
        if ($found->count() !== 0 && $found->count() !== count(self::SUBJECTS)) {
            throw new RuntimeException('The Prestige diagnostic account state is partial.');
        }
    }

    private function assertMemberSet(): void
    {
        $markers = DB::table('member_external_identifiers')->where('namespace', NonclinicalValidationContext::PRESTIGE_MARKER_NAMESPACE)->whereIn('value', array_column(self::SUBJECTS, 'key'))->lockForUpdate()->get();
        if ($markers->count() > count(self::SUBJECTS) || $markers->groupBy('value')->contains(fn ($rows): bool => $rows->count() !== 1)) {
            throw new RuntimeException('The Prestige diagnostic Member markers are inconsistent.');
        }
        if ($markers->isNotEmpty() && $markers->count() !== count(self::SUBJECTS)) {
            throw new RuntimeException('The Prestige diagnostic Member state is partial.');
        }
    }

    private function owned(string $id, string $subject): bool
    {
        return DB::table('audit_events')->where('action', 'production.prestige-upload-diagnostic.account.provisioned')->where('target_id', $id)->where('outcome', 'success')->where('metadata', 'like', '%"subject":"'.$subject.'"%')->count() === 1;
    }

    private function schedule(string $siteRef, string $service): object
    {
        $rows = DB::table('shift_schedules')->where('examination_site_id', $siteRef)->where('service_offering_id', $service)->where('quota', 2)->get();
        $owned = $rows->filter(fn (object $row): bool => $this->ownedSchedule((string) $row->id));
        if ($owned->count() > 1 || ($owned->isNotEmpty() && $rows->count() !== 1)) {
            throw new RuntimeException('The Prestige diagnostic schedule ownership is inconsistent.');
        }
        if ($owned->isNotEmpty()) {
            return $owned->first();
        }
        if ($rows->isNotEmpty()) {
            throw new RuntimeException('An unrelated Prestige diagnostic schedule exists.');
        }
        $local = new DateTimeImmutable($this->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d').' 00:00:00', new DateTimeZone('Asia/Jakarta'));
        $start = $local->setTimezone(new DateTimeZone('UTC'));
        $end = $local->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));
        $now = $this->clock->now();
        $id = (string) Str::uuid();
        DB::table('shift_schedules')->insert(['id' => $id, 'display_reference' => 'JAD-'.Str::upper(Str::random(8)), 'examination_site_id' => $siteRef, 'service_offering_id' => $service, 'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'), 'quota' => 2, 'status' => 'open', 'eligible_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current(), 'production.prestige-upload-diagnostic.schedule.provisioned', 'system', 'success', $now, 'shift-schedule', $id, metadata: ['validation_context' => self::OPERATION, 'nonclinical' => true]));

        return DB::table('shift_schedules')->where('id', $id)->firstOrFail();
    }

    private function ownedSchedule(string $id): bool
    {
        return DB::table('audit_events')->where('action', 'production.prestige-upload-diagnostic.schedule.provisioned')->where('target_id', $id)->where('outcome', 'success')->count() === 1;
    }

    /** @return array{employees:int,schedules:array<int,array<string,mixed>>,bookings:array<int,string>} */
    private function canonicalState(): array
    {
        $serviceId = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->value('id');
        $schedules = $serviceId === null ? [] : DB::table('shift_schedules as s')->join('examination_site_refs as r', 'r.id', '=', 's.examination_site_id')->where('s.service_offering_id', $serviceId)->where('r.operator_site_id', self::SITE)->orderBy('s.id')->get(['s.id', 's.starts_at', 's.ends_at', 's.quota', 's.status'])->map(fn (object $row): array => (array) $row)->all();
        $scheduleIds = array_column($schedules, 'id');

        return [
            'employees' => DB::table('members')->join('users', 'users.id', '=', 'members.user_id')->where('users.email', 'like', '%@prestige.madeena-xray.com')->where('members.registration_source', 'administrator')->where('members.identity_status', 'verified')->whereNotNull('members.encrypted_nik')->count(),
            'schedules' => $schedules,
            'bookings' => $scheduleIds === [] ? [] : DB::table('bookings')->whereIn('shift_schedule_id', $scheduleIds)->orderBy('id')->pluck('id')->all(),
        ];
    }

    private function window(object $schedule): string
    {
        $now = $this->clock->now();

        return $now < new DateTimeImmutable($schedule->starts_at, new DateTimeZone('UTC')) ? 'FUTURE' : ($now < new DateTimeImmutable($schedule->ends_at, new DateTimeZone('UTC')) ? 'ACTIVE' : 'ENDED');
    }

    private function fund(Member $member, object $schedule, string $cost, string $subject, NonclinicalValidationContextProvider $provider): void
    {
        $source = self::OPERATION.':'.$subject.':booking-funding-v1';
        $entries = DB::table('point_ledger_entries')->where('source_reference', $source)->lockForUpdate()->get();
        if ($entries->count() > 1 || ($entries->isNotEmpty() && ((string) $entries->first()->member_id !== $member->id || (string) $entries->first()->point_delta !== $cost))) {
            throw new RuntimeException('The Prestige diagnostic funding state is inconsistent.');
        }
        if ($entries->isEmpty()) {
            $id = (string) Str::uuid();
            DB::table('point_ledger_entries')->insert(['id' => $id, 'member_id' => $member->id, 'booking_id' => null, 'funding_source' => 'personal', 'entry_type' => 'credit', 'point_delta' => $cost, 'source_reference' => $source, 'reverses_id' => null, 'created_at' => $this->clock->now()]);
            $this->audit->append(AuditEvent::fromContext($provider->current(), 'member.point-funding.prestige-upload-diagnostic', 'member', 'success', $this->clock->now(), 'point-ledger-entry', $id, metadata: ['validation_context' => self::OPERATION, 'subject' => $subject, 'nonclinical' => true]));
        }
        if (DB::table('point_ledger_entries')->where('member_id', $member->id)->sum('point_delta') != $cost && DB::table('bookings')->where('member_id', $member->id)->exists() === false) {
            throw new RuntimeException('The Prestige diagnostic funding balance is inconsistent.');
        }
    }

    private function eligibleAndOperators(object $schedule, string $operatorSiteLocalId): void
    {
        $eligible = DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->get();
        if ($eligible->count() > 1 || ($eligible->isNotEmpty() && ((int) $eligible->first()->quota !== 2 || $eligible->first()->operator_site_id !== self::SITE))) {
            throw new RuntimeException('The Prestige diagnostic eligible shift is inconsistent.');
        }
        $now = $this->clock->now();
        $eligibleId = $eligible->first()?->id;
        if ($eligibleId === null) {
            $eligibleId = (string) Str::uuid();
            DB::table('operator_eligible_shifts')->insert(['id' => $eligibleId, 'member_schedule_id' => $schedule->id, 'operator_site_id' => self::SITE, 'schedule_starts_at' => $schedule->starts_at, 'schedule_ends_at' => $schedule->ends_at, 'confirmed_count_at_eligibility' => 2, 'quota' => 2, 'event_version' => 1, 'source_event_id' => self::OPERATION.':shift-v1', 'eligible_at' => $now, 'sync_status' => 'eligible', 'created_at' => $now, 'updated_at' => $now]);
        }
        $profiles = DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->where('active', true)->orderBy('employee_code')->get();
        if ($profiles->count() !== 5) {
            throw new RuntimeException('The Prestige Operator set is not exactly five active profiles.');
        }
        $provider = new NonclinicalValidationContextProvider(NonclinicalValidationContext::PRESTIGE_KEY);
        app()->instance(AuthenticatedContextProvider::class, $provider);
        $provider->accountProvisioning();
        foreach ($profiles as $profile) {
            if (! DB::table('operator_site_assignments')->where('operator_profile_id', $profile->id)->where('operator_site_id', $operatorSiteLocalId)->where('active', true)->exists()) {
                throw new RuntimeException('A Prestige Operator is not assigned to the required site.');
            } $rows = DB::table('operator_shift_assignments')->where('operator_profile_id', $profile->id)->where('operator_eligible_shift_id', $eligibleId)->where('status', 'active')->get();
            if ($rows->count() > 1) {
                throw new RuntimeException('A Prestige diagnostic shift assignment is duplicated.');
            } if ($rows->isNotEmpty() && ! $this->ownedAssignment((string) $rows->first()->id)) {
                throw new RuntimeException('The Prestige diagnostic shift assignment is not owned by this context.');
            } if ($rows->isEmpty()) {
                $id = (string) Str::uuid();
                DB::table('operator_shift_assignments')->insert(['id' => $id, 'operator_eligible_shift_id' => $eligibleId, 'operator_profile_id' => $profile->id, 'assigned_by_user_id' => null, 'status' => 'active', 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);
                $this->audit->append(AuditEvent::fromContext($provider->current(), 'production.prestige-upload-diagnostic.shift-assignment.provisioned', 'operator', 'success', $now, 'operator-shift-assignment', $id, metadata: ['validation_context' => self::OPERATION, 'nonclinical' => true]));
            }
        }
    }

    private function ownedAssignment(string $id): bool
    {
        return DB::table('audit_events')->where('action', 'production.prestige-upload-diagnostic.shift-assignment.provisioned')->where('target_id', $id)->where('outcome', 'success')->count() === 1;
    }
}
