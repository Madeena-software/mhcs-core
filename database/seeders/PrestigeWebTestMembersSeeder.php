<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Member\Application\Services\MedicalRecordNumberGenerator;
use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Member\Domain\Enums\PointEntryType;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class PrestigeWebTestMembersSeeder extends Seeder
{
    private const SITE_ID = PrestigeClinicSeeder::OPERATOR_SITE_ID;

    private const SITE_CODE = PrestigeClinicSeeder::SITE_CODE;

    private const SERVICE_CODE = 'SYN-CHEST-A';

    private const DISPLAY_REFERENCE = 'JAD-PRES-NPZ-TEST';

    private const CREDIT_AMOUNT = '12.5000';

    /** @var list<array{name: string, email: string, nik: string, birth_date: string}> */
    private const SUBJECTS = [
        ['name' => 'gbsuparta', 'email' => 'gbsuparta@ugm.ac.id', 'nik' => '9900000000000001', 'birth_date' => '1980-01-01'],
        ['name' => 'ipang', 'email' => 'ipang.prestige@madeena-xray.com', 'nik' => '9900000000000002', 'birth_date' => '1980-01-02'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! filter_var(getenv('MHCS_ALLOW_PRODUCTION_MVP_SEED') ?: ($_ENV['MHCS_ALLOW_PRODUCTION_MVP_SEED'] ?? null), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('PrestigeWebTestMembersSeeder is limited to local, testing, or authorized production bootstrap.');
        }

        $site = DB::table('examination_site_refs')->where('operator_site_id', self::SITE_ID)->first();
        $offering = DB::table('service_offerings')->where('code', self::SERVICE_CODE)->first();
        if ($site === null || $site->code !== self::SITE_CODE || $site->display_name !== PrestigeClinicSeeder::SITE_DISPLAY_NAME || $site->timezone !== PrestigeClinicSeeder::SITE_TIMEZONE || ! $site->active || $offering === null || ! $offering->active) {
            throw new RuntimeException('The canonical Prestige site or service fixture is missing or inconsistent.');
        }

        $this->assertCanonicalPrestigeState();
        $subjects = $this->existingSubjects();
        $schedule = $this->existingSchedule((string) $site->id, (string) $offering->id);
        $bounds = $this->todayBounds();

        if ($schedule !== null && ($schedule->examination_site_id !== $site->id || $schedule->service_offering_id !== $offering->id || $schedule->starts_at !== $bounds['start'] || $schedule->ends_at !== $bounds['end'] || (int) $schedule->quota !== 2 || $schedule->status !== 'open')) {
            throw new RuntimeException('The owned Prestige web-test schedule is inconsistent or has ended.');
        }

        $this->assertIdentityAvailability($subjects);

        DB::transaction(function () use ($site, $offering, $schedule, $bounds): void {
            $now = now();
            $scheduleId = $schedule?->id;
            if ($scheduleId === null) {
                $scheduleId = (string) Str::uuid();
                DB::table('shift_schedules')->insert([
                    'id' => $scheduleId,
                    'display_reference' => self::DISPLAY_REFERENCE,
                    'examination_site_id' => $site->id,
                    'service_offering_id' => $offering->id,
                    'starts_at' => $bounds['start'],
                    'ends_at' => $bounds['end'],
                    'quota' => 2,
                    'status' => 'open',
                    'eligible_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rateId = DB::table('point_exchange_rates')->where('status', 'active')->value('id');
            if (! is_string($rateId)) {
                throw new RuntimeException('The canonical Prestige point rate is missing.');
            }

            $members = [];
            foreach (self::SUBJECTS as $definition) {
                $members[] = $this->ensureSubject($definition, $now);
            }
            foreach ($members as $member) {
                $this->ensureBooking($member, $scheduleId, $site, $offering, $rateId, $now);
            }
            $this->ensureEligibleShift($scheduleId, $bounds, $now);
        });
    }

    private function assertCanonicalPrestigeState(): void
    {
        $users = DB::table('users')->where('email', 'like', '%@prestige.madeena-xray.com')->get(['id', 'account_status', 'login_enabled']);
        $members = DB::table('members')->whereIn('user_id', $users->pluck('id'))->get(['id', 'user_id']);
        $profiles = DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->get();
        if ($users->count() !== 37 || $members->count() !== 37 || $members->pluck('user_id')->unique()->count() !== 37 || $users->where('account_status', 'active')->count() !== 37 || $users->where('login_enabled', true)->count() !== 37 || $profiles->count() !== 5 || $profiles->where('active', true)->count() !== 5) {
            throw new RuntimeException('The canonical Prestige employee or Operator fixture is not exact.');
        }
    }

    /** @return array<string, object|null> */
    private function existingSubjects(): array
    {
        $result = [];
        foreach (self::SUBJECTS as $subject) {
            $user = User::query()->where('email', $subject['email'])->first();
            if ($user === null) {
                $result[$subject['email']] = null;

                continue;
            }
            $member = DB::table('members')->where('user_id', $user->id)->first();
            if ($member === null || $user->account_status !== 'active' || ! $user->login_enabled || $member->nik_lookup_digest !== app(ProtectedIdentifierService::class)->lookupDigest($subject['nik']) || $member->name !== $subject['name'] || $member->birth_date !== $subject['birth_date'] || $member->administrative_gender !== 'unspecified' || $member->registration_source !== 'administrator' || $member->current_address !== 'MHCS web test fixture - not real identity data' || $member->identity_status !== 'verified' || $member->identity_document_type !== 'ktp' || DB::table('authorization_role_assignments')->where('user_id', $user->id)->exists() || DB::table('authorization_permission_assignments')->where('user_id', $user->id)->exists()) {
                throw new RuntimeException('An existing Prestige web-test email is not owned by this exact fixture.');
            }
            $assets = DB::table('member_verification_assets')->where('member_id', $member->id)->where('is_current', true)->where('review_status', 'approved')->pluck('type')->all();
            sort($assets);
            if ($assets !== ['ktp', 'profile_photo']) {
                throw new RuntimeException('An existing Prestige web-test Member has inconsistent verification assets.');
            }
            $result[$subject['email']] = $member;
        }

        return $result;
    }

    /** @param array<string, object|null> $subjects */
    private function assertIdentityAvailability(array $subjects): void
    {
        $identifiers = app(ProtectedIdentifierService::class);
        foreach (self::SUBJECTS as $subject) {
            $digest = $identifiers->lookupDigest($subject['nik']);
            $existing = DB::table('members')->where('nik_lookup_digest', $digest)->first();
            if ($existing !== null && ($subjects[$subject['email']]?->id !== $existing->id)) {
                throw new RuntimeException('A synthetic Prestige web-test identity already belongs to another Member.');
            }
        }
    }

    private function existingSchedule(string $siteId, string $offeringId): ?object
    {
        $schedules = DB::table('shift_schedules')->where('display_reference', self::DISPLAY_REFERENCE)->get();
        if ($schedules->count() > 1) {
            throw new RuntimeException('The Prestige web-test schedule is duplicated.');
        }

        $schedule = $schedules->first();
        if ($schedule !== null && ($schedule->examination_site_id !== $siteId || $schedule->service_offering_id !== $offeringId)) {
            throw new RuntimeException('The Prestige web-test schedule belongs to another fixture.');
        }

        return $schedule;
    }

    /** @return array{start: string, end: string} */
    private function todayBounds(): array
    {
        $timezone = new DateTimeZone(PrestigeClinicSeeder::SITE_TIMEZONE);
        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('today', $timezone);
        $end = $start->modify('+1 day');

        return ['start' => $start->setTimezone($utc)->format('Y-m-d H:i:s'), 'end' => $end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    private function ensureSubject(array $subject, mixed $now): object
    {
        $existing = User::query()->where('email', $subject['email'])->first();
        if ($existing !== null) {
            return DB::table('members')->where('user_id', $existing->id)->firstOrFail();
        }

        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $protected = app(ProtectedIdentifierService::class)->protect($subject['nik']);
        $context = new AuthenticatedContext(actorId: LocalId::fromString($userId), operationId: CorrelationId::random(), roles: ['administrator'], permissions: ['member.registration.manage', 'member.identity.verify'], purpose: 'member.registration');
        $objects = app(PrivateObjectStore::class);
        $identityObject = $objects->put('MHCS web test fixture KTP; synthetic only.', $context, 'member.registration');
        $profileObject = $objects->put('MHCS web test fixture profile photo; synthetic only.', $context, 'member.registration');

        DB::table('users')->insert(['id' => $userId, 'email' => $subject['email'], 'email_verified_at' => $now, 'password' => Hash::make(Str::random(64)), 'remember_token' => null, 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('members')->insert(['id' => $memberId, 'user_id' => $userId, 'family_id' => null, 'medical_record_number' => app(MedicalRecordNumberGenerator::class)->generate(), 'identity_status' => 'verified', 'identity_document_type' => 'ktp', 'encrypted_nik' => $protected['encrypted_display'], 'nik_lookup_digest' => $protected['lookup_digest'], 'name' => $subject['name'], 'birth_date' => $subject['birth_date'], 'administrative_gender' => 'unspecified', 'registration_source' => 'administrator', 'phone' => null, 'current_address' => 'MHCS web test fixture - not real identity data', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['type' => 'ktp', 'object' => $identityObject], ['type' => 'profile_photo', 'object' => $profileObject]] as $asset) {
            /** @var PrivateObject $object */
            $object = $asset['object'];
            DB::table('member_verification_assets')->insert(['id' => (string) Str::uuid(), 'member_id' => $memberId, 'type' => $asset['type'], 'private_object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes, 'format' => 'text/plain', 'review_status' => 'approved', 'is_current' => true, 'uploaded_by_user_id' => $userId, 'reviewed_by_user_id' => $userId, 'reviewed_at' => $now, 'replaces_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('members')->where('id', $memberId)->firstOrFail();
    }

    private function ensureBooking(object $member, string $scheduleId, object $site, object $offering, string $rateId, mixed $now): void
    {
        $existing = DB::table('bookings')->where('member_id', $member->id)->where('shift_schedule_id', $scheduleId)->first();
        $point = app(Mvp03PointService::class);
        $email = (string) DB::table('users')->where('id', $member->user_id)->value('email');
        $key = str_contains($email, 'gbsuparta') ? 'gbsuparta' : 'ipang';
        $point->creditPersonalForLocalTesting((string) $member->id, self::CREDIT_AMOUNT, 'prestige:web-test:'.$key.':credit');
        if ($existing !== null) {
            if ($existing->service_code_snapshot !== self::SERVICE_CODE || $existing->status !== 'confirmed' || $existing->booking_type !== 'b2c' || $existing->funding_source !== 'personal') {
                throw new RuntimeException('The Prestige web-test booking is inconsistent.');
            }
            $charge = DB::table('point_ledger_entries')->where('booking_id', $existing->id)->where('entry_type', PointEntryType::Charge->value)->first();
            if ($charge === null || PointAmount::fromString((string) $charge->point_delta)->compare(PointAmount::fromString('-'.self::CREDIT_AMOUNT)) !== 0 || $charge->source_reference !== 'prestige:web-test:'.$key.':charge') {
                throw new RuntimeException('The Prestige web-test booking charge is inconsistent.');
            }

            return;
        }

        $bookingId = (string) Str::uuid();
        DB::table('bookings')->insert(['id' => $bookingId, 'member_id' => $member->id, 'shift_schedule_id' => $scheduleId, 'service_offering_id' => $offering->id, 'examination_site_id_snapshot' => $site->id, 'booking_type' => 'b2c', 'funding_source' => 'personal', 'status' => 'confirmed', 'service_code_snapshot' => self::SERVICE_CODE, 'point_cost_snapshot' => self::CREDIT_AMOUNT, 'point_exchange_rate_id' => $rateId, 'includes_ai_snapshot' => (bool) $offering->includes_ai, 'includes_doctor_snapshot' => (bool) $offering->includes_doctor, 'site_code_snapshot' => self::SITE_CODE, 'site_name_snapshot' => PrestigeClinicSeeder::SITE_DISPLAY_NAME, 'site_timezone_snapshot' => PrestigeClinicSeeder::SITE_TIMEZONE, 'created_at' => $now, 'confirmed_at' => $now, 'updated_at' => $now]);
        DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $member->id, 'booking_id' => $bookingId, 'funding_source' => 'personal', 'entry_type' => PointEntryType::Charge->value, 'point_delta' => '-'.self::CREDIT_AMOUNT, 'source_reference' => 'prestige:web-test:'.$key.':charge', 'reverses_id' => null, 'created_at' => $now]);
    }

    /** @param array{start: string, end: string} $bounds */
    private function ensureEligibleShift(string $scheduleId, array $bounds, mixed $now): void
    {
        $existing = DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->first();
        $profiles = DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->orderBy('employee_code')->get();
        if ($existing !== null && ($existing->operator_site_id !== self::SITE_ID || $existing->schedule_starts_at !== $bounds['start'] || $existing->schedule_ends_at !== $bounds['end'] || (int) $existing->quota !== 2 || (int) $existing->confirmed_count_at_eligibility !== 2 || $existing->sync_status !== 'eligible')) {
            throw new RuntimeException('The Prestige web-test eligible shift is inconsistent.');
        }
        $eligibleId = $existing?->id;
        if ($eligibleId === null) {
            $eligibleId = (string) Str::uuid();
            DB::table('operator_eligible_shifts')->insert(['id' => $eligibleId, 'member_schedule_id' => $scheduleId, 'operator_site_id' => self::SITE_ID, 'schedule_starts_at' => $bounds['start'], 'schedule_ends_at' => $bounds['end'], 'confirmed_count_at_eligibility' => 2, 'quota' => 2, 'event_version' => 1, 'source_event_id' => 'prestige:web-test:shift-eligible', 'eligible_at' => $now, 'sync_status' => 'eligible', 'created_at' => $now, 'updated_at' => $now]);
        }
        $assignments = DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligibleId)->get();
        if ($assignments->count() > 5 || $assignments->where('status', '!=', 'active')->isNotEmpty()) {
            throw new RuntimeException('The Prestige web-test Operator assignments are inconsistent.');
        }
        foreach ($profiles as $profile) {
            if (! $assignments->contains(fn (object $assignment): bool => $assignment->operator_profile_id === $profile->id && $assignment->status === 'active')) {
                DB::table('operator_shift_assignments')->insert(['id' => (string) Str::uuid(), 'operator_eligible_shift_id' => $eligibleId, 'operator_profile_id' => $profile->id, 'assigned_by_user_id' => $profile->user_id, 'status' => 'active', 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
