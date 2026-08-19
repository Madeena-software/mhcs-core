<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Member\Application\Services\MedicalRecordNumberGenerator;
use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Member\Application\Services\Mvp03SiteReferenceService;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
use App\Modules\Member\Domain\Enums\PointEntryType;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class PrestigeClinicSeeder extends Seeder
{
    public const ORGANIZATION_ID = 'org-prestige';

    public const ORGANIZATION_NAME = 'CV Prestige';

    public const OPERATOR_SITE_ID = 'site-prestige';

    public const SITE_CODE = 'PRES-01';

    public const SITE_DISPLAY_NAME = 'Rumah Skrining CV Prestige';

    public const SITE_TIMEZONE = 'Asia/Jakarta';

    private const EMPLOYEE_COUNT = 37;

    /** @var list<string> */
    private const ADMIN_PERMISSIONS = [
        'member.admin.access',
        'member.account.read',
        'member.account.manage',
        'member.audit.read',
        'member.catalogue.read',
        'member.catalogue.manage',
        'member.schedule.read',
        'member.schedule.manage',
        'member.booking.read',
        'member.booking.manage',
        'member.booking.audit.read',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            throw new RuntimeException('PrestigeClinicSeeder is limited to local, testing, or authorized production bootstrap.');
        }

        $employees = $this->loadEmployees();
        $adminUser = $this->seedAdmin();
        $siteId = $this->seedSite();
        $this->seedOperators($adminUser, $siteId);
        $schedules = $this->seedCatalogueAndSchedules($siteId);
        $rateId = app(Mvp03PointService::class)->ensureInitialLocalRate($adminUser->getKey());
        $this->seedMembersAndBookings($employees, $schedules, $rateId);

        $this->command?->info('Prestige Clinic dataset seeded successfully.');
    }

    private function seedAdmin(): User
    {
        $adminEmail = env('SUPER_ADMIN_EMAIL', 'mvp-admin@example.test');
        $adminPassword = env('SUPER_ADMIN_PASSWORD', 'madeenaadmin');

        $existing = User::query()->where('email', $adminEmail)->first();
        if ($existing !== null) {
            $this->ensureAdminClaims($existing->getKey());
            MvpCredentialFile::reset($adminEmail, $adminPassword);

            return $existing;
        }

        $userId = (string) Str::uuid();
        $now = now();

        DB::transaction(function () use ($userId, $adminEmail, $adminPassword, $now): void {
            DB::table('users')->insert([
                'id' => $userId,
                'email' => $adminEmail,
                'email_verified_at' => $now,
                'password' => Hash::make($adminPassword),
                'remember_token' => null,
                'account_status' => 'active',
                'login_enabled' => true,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('authorization_role_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'role' => 'administrator',
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::ADMIN_PERMISSIONS as $permission) {
                DB::table('authorization_permission_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'permission' => $permission,
                    'assigned_by_user_id' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        MvpCredentialFile::reset($adminEmail, $adminPassword);

        return User::query()->whereKey($userId)->firstOrFail();
    }

    private function ensureAdminClaims(string $userId): void
    {
        $now = now();
        if (! DB::table('authorization_role_assignments')->where('user_id', $userId)->where('role', 'administrator')->exists()) {
            DB::table('authorization_role_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'role' => 'administrator',
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::ADMIN_PERMISSIONS as $permission) {
            if (! DB::table('authorization_permission_assignments')->where('user_id', $userId)->where('permission', $permission)->exists()) {
                DB::table('authorization_permission_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'permission' => $permission,
                    'assigned_by_user_id' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedSite(): string
    {
        $site = DB::table('operator_sites')->where('operator_site_id', self::OPERATOR_SITE_ID)->first();
        if ($site !== null) {
            app(Mvp04OperatorSiteReferenceService::class)->synchronize(
                self::ORGANIZATION_ID,
                self::ORGANIZATION_NAME,
                self::OPERATOR_SITE_ID,
                self::SITE_CODE,
                self::SITE_DISPLAY_NAME,
                self::SITE_TIMEZONE,
                true,
                'v1',
            );

            return (string) $site->id;
        }

        $localId = (string) Str::uuid();
        $now = now();
        DB::table('operator_sites')->insert([
            'id' => $localId,
            'operator_site_id' => self::OPERATOR_SITE_ID,
            'organization_id' => self::ORGANIZATION_ID,
            'organization_name' => self::ORGANIZATION_NAME,
            'code' => self::SITE_CODE,
            'display_name' => self::SITE_DISPLAY_NAME,
            'address_line' => 'Yogyakarta',
            'timezone' => self::SITE_TIMEZONE,
            'active' => true,
            'source_version' => 'v1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(Mvp04OperatorSiteReferenceService::class)->synchronize(
            self::ORGANIZATION_ID,
            self::ORGANIZATION_NAME,
            self::OPERATOR_SITE_ID,
            self::SITE_CODE,
            self::SITE_DISPLAY_NAME,
            self::SITE_TIMEZONE,
            true,
            'v1',
        );

        app(Mvp03SiteReferenceService::class)->bootstrap(
            self::ORGANIZATION_ID,
            self::ORGANIZATION_NAME,
            self::OPERATOR_SITE_ID,
            self::SITE_CODE,
            self::SITE_DISPLAY_NAME,
            self::SITE_TIMEZONE,
        );

        return $localId;
    }

    private function seedOperators(User $adminUser, string $siteId): void
    {
        $operatorFile = base_path('research/prestige/operator.txt');
        $operatorLines = file_exists($operatorFile) ? file($operatorFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $emails = [];
        $password = 'operator123';

        foreach ($operatorLines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'password:')) {
                $password = trim(substr($line, strlen('password:')));
            } elseif (filter_var($line, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($line);
            }
        }

        if (empty($emails)) {
            $emails = [
                'operatorprestigesatu@madeena-xray.com',
                'operatorprestigedua@madeena-xray.com',
                'operatorprestigetiga@madeena-xray.com',
                'operatorprestigeempat@madeena-xray.com',
                'operatorprestigelima@madeena-xray.com',
            ];
        }

        $now = now();
        foreach ($emails as $index => $email) {
            $oprNum = $index + 1;
            $displayName = "Operator Prestige $oprNum";
            $employeeCode = sprintf('OPR-PRES-%02d', $oprNum);

            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $userId = (string) Str::uuid();
                DB::table('users')->insert([
                    'id' => $userId,
                    'email' => $email,
                    'email_verified_at' => $now,
                    'password' => Hash::make($password),
                    'remember_token' => null,
                    'account_status' => 'active',
                    'login_enabled' => true,
                    'must_change_password' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $user = User::query()->whereKey($userId)->firstOrFail();
            }

            // Role assignment
            if (! DB::table('authorization_role_assignments')->where('user_id', $user->getKey())->where('role', OperatorAuthorization::ROLE)->exists()) {
                DB::table('authorization_role_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->getKey(),
                    'role' => OperatorAuthorization::ROLE,
                    'assigned_by_user_id' => $adminUser->getKey(),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Operator permissions
            foreach ([
                OperatorAuthorization::PORTAL_ACCESS,
                OperatorAuthorization::SITE_READ,
                OperatorAuthorization::SITE_MANAGE,
                OperatorAuthorization::ASSIGNMENT_READ,
                OperatorAuthorization::ASSIGNMENT_MANAGE,
                OperatorAuthorization::SHIFT_READ,
                OperatorAuthorization::SHIFT_MANAGE,
                OperatorAuthorization::ATTENDANCE_READ,
                OperatorAuthorization::ARRIVAL_RECORD,
                OperatorAuthorization::IDENTITY_VERIFY,
                OperatorAuthorization::AUDIT_READ,
                OperatorAuthorization::PROTOCOL_READ,
                OperatorAuthorization::PROTOCOL_MANAGE,
            ] as $perm) {
                if (! DB::table('authorization_permission_assignments')->where('user_id', $user->getKey())->where('permission', $perm)->exists()) {
                    DB::table('authorization_permission_assignments')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->getKey(),
                        'permission' => $perm,
                        'assigned_by_user_id' => $adminUser->getKey(),
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Profile
            $profile = DB::table('operator_profiles')->where('user_id', $user->getKey())->first();
            $profileId = $profile ? (string) $profile->id : (string) Str::uuid();
            if ($profile === null) {
                DB::table('operator_profiles')->insert([
                    'id' => $profileId,
                    'user_id' => $user->getKey(),
                    'display_name' => $displayName,
                    'employee_code' => $employeeCode,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Site assignment
            if (! DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $siteId)->exists()) {
                DB::table('operator_site_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'operator_profile_id' => $profileId,
                    'operator_site_id' => $siteId,
                    'active' => true,
                    'assigned_by_user_id' => $adminUser->getKey(),
                    'assigned_at' => $now,
                    'revoked_at' => null,
                    'reason' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            MvpCredentialFile::append($email, $password);
        }
    }

    /** @return list<object> */
    private function seedCatalogueAndSchedules(string $siteId): array
    {
        $now = now();
        $audit = app(AuditStore::class);
        $clock = app(Clock::class);

        $offeringA = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->first();
        if ($offeringA === null) {
            $idA = (string) Str::uuid();
            DB::table('service_offerings')->insert([
                'id' => $idA,
                'code' => 'SYN-CHEST-A',
                'name' => 'Sesi Foto Radiografi Dasar',
                'includes_ai' => true,
                'includes_doctor' => false,
                'point_price' => '12.5000',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $offeringA = DB::table('service_offerings')->where('id', $idA)->first();
        }

        $offeringB = DB::table('service_offerings')->where('code', 'SYN-CHEST-B')->first();
        if ($offeringB === null) {
            $idB = (string) Str::uuid();
            DB::table('service_offerings')->insert([
                'id' => $idB,
                'code' => 'SYN-CHEST-B',
                'name' => 'Sesi Foto Radiografi dengan Peninjauan',
                'includes_ai' => true,
                'includes_doctor' => true,
                'point_price' => '25.7500',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $offeringB = DB::table('service_offerings')->where('id', $idB)->first();
        }

        $siteRefId = DB::table('examination_site_refs')->where('operator_site_id', self::OPERATOR_SITE_ID)->value('id');
        if (! is_string($siteRefId)) {
            $siteRefId = (string) Str::uuid();
            DB::table('examination_site_refs')->insert([
                'id' => $siteRefId,
                'organization_id' => self::ORGANIZATION_ID,
                'organization_name' => self::ORGANIZATION_NAME,
                'operator_site_id' => self::OPERATOR_SITE_ID,
                'code' => self::SITE_CODE,
                'display_name' => self::SITE_DISPLAY_NAME,
                'timezone' => self::SITE_TIMEZONE,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->removeObsoleteSchedules($siteRefId, (string) $offeringA->id);

        // Prestige rehearsal: 27 and 28 August 2026, 01:00–10:00.
        $scheduleDates = [
            ['start' => '2026-08-27 01:00:00', 'end' => '2026-08-27 10:00:00'],
            ['start' => '2026-08-28 01:00:00', 'end' => '2026-08-28 10:00:00'],
        ];

        $schedules = [];
        $profiles = DB::table('operator_profiles')->get();

        foreach ($scheduleDates as $date) {
            $sched = DB::table('shift_schedules')
                ->where('examination_site_id', $siteRefId)
                ->where('service_offering_id', $offeringA->id)
                ->where('starts_at', $date['start'])
                ->first();

            if ($sched === null) {
                $schedId = (string) Str::uuid();
                DB::table('shift_schedules')->insert([
                    'id' => $schedId,
                    'display_reference' => 'JAD-'.Str::upper(Str::random(8)),
                    'examination_site_id' => $siteRefId,
                    'service_offering_id' => (string) $offeringA->id,
                    'starts_at' => $date['start'],
                    'ends_at' => $date['end'],
                    'quota' => self::EMPLOYEE_COUNT,
                    'status' => 'open',
                    'eligible_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $sched = DB::table('shift_schedules')->where('id', $schedId)->first();
            } else {
                DB::table('shift_schedules')->where('id', $sched->id)->update([
                    'ends_at' => $date['end'],
                    'quota' => self::EMPLOYEE_COUNT,
                    'status' => 'open',
                    'updated_at' => $now,
                ]);
                $sched = DB::table('shift_schedules')->where('id', $sched->id)->first();
            }

            // Eligible shift & operator assignments
            $eligible = DB::table('operator_eligible_shifts')->where('member_schedule_id', $sched->id)->first();
            $eligibleId = $eligible ? (string) $eligible->id : (string) Str::uuid();
            if ($eligible === null) {
                DB::table('operator_eligible_shifts')->insert([
                    'id' => $eligibleId,
                    'member_schedule_id' => $sched->id,
                    'operator_site_id' => self::OPERATOR_SITE_ID,
                    'schedule_starts_at' => $sched->starts_at,
                    'schedule_ends_at' => $sched->ends_at,
                    'confirmed_count_at_eligibility' => 0,
                    'quota' => (int) $sched->quota,
                    'event_version' => 1,
                    'source_event_id' => 'prestige:shift-eligible:'.$sched->id,
                    'eligible_at' => $now,
                    'sync_status' => 'eligible',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('operator_eligible_shifts')->where('id', $eligibleId)->update([
                    'schedule_starts_at' => $sched->starts_at,
                    'schedule_ends_at' => $sched->ends_at,
                    'quota' => self::EMPLOYEE_COUNT,
                    'updated_at' => $now,
                ]);
            }

            foreach ($profiles as $prof) {
                if (! DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligibleId)->where('operator_profile_id', $prof->id)->exists()) {
                    DB::table('operator_shift_assignments')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_eligible_shift_id' => $eligibleId,
                        'operator_profile_id' => (string) $prof->id,
                        'assigned_by_user_id' => (string) $prof->user_id,
                        'status' => 'active',
                        'assigned_at' => $now,
                        'revoked_at' => null,
                        'reason' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $schedules[] = $sched;
        }

        return $schedules;
    }

    private function removeObsoleteSchedules(string $siteRefId, string $offeringId): void
    {
        $obsolete = DB::table('shift_schedules')
            ->where('examination_site_id', $siteRefId)
            ->where('service_offering_id', $offeringId)
            ->whereIn('starts_at', ['2026-08-14 01:00:00', '2026-08-26 01:00:00'])
            ->get(['id']);

        foreach ($obsolete as $schedule) {
            $scheduleId = (string) $schedule->id;
            foreach ([
                ['bookings', 'shift_schedule_id'],
                ['local_imaging_orders', 'shift_schedule_id'],
                ['operator_paper_tickets', 'member_schedule_id'],
                ['operator_queue_admissions', 'member_schedule_id'],
                ['operator_arrivals', 'member_schedule_id'],
                ['operator_identity_verifications', 'member_schedule_id'],
                ['member_paper_questionnaires', 'member_schedule_id'],
                ['member_vital_signs_assessments', 'member_schedule_id'],
                ['image_gateway_capture_sets', 'member_schedule_id'],
            ] as [$table, $column]) {
                if (DB::table($table)->where($column, $scheduleId)->exists()) {
                    throw new RuntimeException('An obsolete Prestige schedule has downstream records and needs separate cleanup.');
                }
            }

            $eligibleIds = DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->pluck('id');
            if ($eligibleIds->isNotEmpty()) {
                DB::table('operator_shift_assignments')->whereIn('operator_eligible_shift_id', $eligibleIds)->delete();
                DB::table('operator_eligible_shifts')->whereIn('id', $eligibleIds)->delete();
            }
            DB::table('shift_schedules')->where('id', $scheduleId)->delete();
        }
    }

    /** @return list<array{no: string, name: string, place: string, birth_date: string, address: string, nik: string}> */
    private function loadEmployees(): array
    {
        $configuredPath = getenv('PRESTIGE_EMPLOYEE_CSV');
        $csvPath = is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : base_path('research/prestige/data-karyawan-cv-prestige.csv');
        if (! is_file($csvPath) || ! is_readable($csvPath)) {
            throw new RuntimeException('Prestige employee CSV is missing or unreadable.');
        }

        $fp = fopen($csvPath, 'r');
        if ($fp === false) {
            throw new RuntimeException('Prestige employee CSV could not be opened.');
        }

        $months = [
            'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
            'Mei' => '05', 'Jun' => '06', 'Jul' => '07', 'Agu' => '08',
            'Sep' => '09', 'Okt' => '10', 'Nov' => '11', 'Des' => '12',
        ];

        try {
            $header = fgetcsv($fp, 0, ',', '"', '\\');
            if (! is_array($header) || count($header) < 6) {
                throw new RuntimeException('Prestige employee CSV header is invalid.');
            }

            $membersData = [];
            $numbers = [];
            $niks = [];
            while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
                if (count($row) < 6) {
                    throw new RuntimeException('Prestige employee CSV contains a malformed row.');
                }

                [$no, $name, $place, $rawDate, $address, $nik] = array_map(static fn (string $value): string => trim($value), array_slice($row, 0, 6));
                if ($no === '' || $name === '' || $place === '' || $rawDate === '' || $address === '' || $nik === '') {
                    throw new RuntimeException('Prestige employee CSV contains an incomplete row.');
                }
                if (! preg_match('/^\d+$/', $no) || ! preg_match('/^\d{10,20}$/', $nik) || isset($numbers[$no]) || isset($niks[$nik])) {
                    throw new RuntimeException('Prestige employee CSV contains duplicate or invalid identifiers.');
                }

                $dateParts = explode('-', $rawDate);
                if (count($dateParts) !== 3 || ! isset($months[$dateParts[1]]) || ! ctype_digit($dateParts[0]) || ! ctype_digit($dateParts[2])) {
                    throw new RuntimeException('Prestige employee CSV contains an invalid birth date.');
                }
                $day = (int) $dateParts[0];
                $year = (int) $dateParts[2];
                $fullYear = $year > 30 ? 1900 + $year : 2000 + $year;
                $month = (int) $months[$dateParts[1]];
                if (! checkdate($month, $day, $fullYear)) {
                    throw new RuntimeException('Prestige employee CSV contains an invalid birth date.');
                }

                $numbers[$no] = true;
                $niks[$nik] = true;
                $membersData[] = [
                    'no' => $no,
                    'name' => $name,
                    'place' => $place,
                    'birth_date' => sprintf('%04d-%02d-%02d', $fullYear, $month, $day),
                    'address' => $address,
                    'nik' => $nik,
                ];
            }
        } finally {
            fclose($fp);
        }

        if (count($membersData) !== self::EMPLOYEE_COUNT) {
            throw new RuntimeException('Prestige employee CSV must contain exactly 37 valid employee rows.');
        }

        return $membersData;
    }

    /** @param list<array{no: string, name: string, place: string, birth_date: string, address: string, nik: string}> $membersData @param list<object> $schedules */
    private function seedMembersAndBookings(array $membersData, array $schedules, string $rateId): void
    {
        $identifiers = app(ProtectedIdentifierService::class);
        $mrn = app(MedicalRecordNumberGenerator::class);
        $objects = app(PrivateObjectStore::class);
        $pointService = app(Mvp03PointService::class);
        $now = now();

        $offering = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->firstOrFail();

        foreach ($membersData as $item) {
            $nik = $item['nik'];
            $name = $item['name'];
            $email = "{$nik}@prestige.madeena-xray.com";
            $password = $nik;

            $existing = User::query()->where('email', $email)->first();
            if ($existing === null) {
                $protected = $identifiers->protect($nik);
                $userId = (string) Str::uuid();
                $memberId = (string) Str::uuid();

                $context = new AuthenticatedContext(
                    actorId: LocalId::fromString($userId),
                    operationId: CorrelationId::random(),
                    roles: ['administrator'],
                    permissions: ['member.registration.manage', 'member.identity.verify'],
                    purpose: 'member.registration',
                );
                $identityObject = $objects->put('synthetic-ktp-'.hash('sha256', $nik), $context, 'member.registration');
                $profileObject = $objects->put('synthetic-profile-'.hash('sha256', $nik), $context, 'member.registration');

                DB::transaction(function () use ($userId, $memberId, $email, $password, $name, $item, $protected, $mrn, $identityObject, $profileObject, $now): void {
                    DB::table('users')->insert([
                        'id' => $userId,
                        'email' => $email,
                        'email_verified_at' => $now,
                        'password' => Hash::make($password),
                        'remember_token' => null,
                        'account_status' => 'active',
                        'login_enabled' => true,
                        'must_change_password' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('members')->insert([
                        'id' => $memberId,
                        'user_id' => $userId,
                        'family_id' => null,
                        'medical_record_number' => $mrn->generate(),
                        'identity_status' => 'verified',
                        'identity_document_type' => 'ktp',
                        'encrypted_nik' => $protected['encrypted_display'],
                        'nik_lookup_digest' => $protected['lookup_digest'],
                        'name' => $name,
                        'birth_date' => $item['birth_date'],
                        'administrative_gender' => 'unspecified',
                        'registration_source' => 'administrator',
                        'phone' => null,
                        'current_address' => $item['address'],
                        'emergency_contact_name' => null,
                        'emergency_contact_relationship' => null,
                        'emergency_contact_phone' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach ([
                        ['type' => 'ktp', 'object' => $identityObject],
                        ['type' => 'profile_photo', 'object' => $profileObject],
                    ] as $asset) {
                        /** @var PrivateObject $object */
                        $object = $asset['object'];
                        DB::table('member_verification_assets')->insert([
                            'id' => (string) Str::uuid(),
                            'member_id' => $memberId,
                            'type' => $asset['type'],
                            'private_object_key' => (string) $object->key,
                            'checksum' => $object->checksum,
                            'bytes' => $object->bytes,
                            'format' => 'text/plain',
                            'review_status' => 'approved',
                            'is_current' => true,
                            'uploaded_by_user_id' => $userId,
                            'reviewed_by_user_id' => $userId,
                            'reviewed_at' => $now,
                            'replaces_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });

                $member = DB::table('members')->where('id', $memberId)->first();
            } else {
                $member = DB::table('members')->where('user_id', $existing->id)->first();
            }

            if ($member === null) {
                continue;
            }

            // Credit points for testing
            $pointService->creditPersonalForLocalTesting(
                (string) $member->id,
                '100.0000',
                'prestige:credit:'.hash('sha256', $nik),
            );

            foreach ($schedules as $selectedSchedule) {
                $existingBooking = DB::table('bookings')
                    ->where('member_id', $member->id)
                    ->where('shift_schedule_id', $selectedSchedule->id)
                    ->first();

                if ($existingBooking !== null) {
                    continue;
                }

                $bookingId = (string) Str::uuid();
                DB::table('bookings')->insert([
                    'id' => $bookingId,
                    'member_id' => (string) $member->id,
                    'shift_schedule_id' => (string) $selectedSchedule->id,
                    'service_offering_id' => (string) $offering->id,
                    'examination_site_id_snapshot' => (string) $selectedSchedule->examination_site_id,
                    'booking_type' => 'b2c',
                    'funding_source' => 'personal',
                    'status' => 'confirmed',
                    'service_code_snapshot' => (string) $offering->code,
                    'point_cost_snapshot' => (string) $offering->point_price,
                    'point_exchange_rate_id' => $rateId,
                    'includes_ai_snapshot' => (bool) $offering->includes_ai,
                    'includes_doctor_snapshot' => (bool) $offering->includes_doctor,
                    'site_code_snapshot' => self::SITE_CODE,
                    'site_name_snapshot' => self::SITE_DISPLAY_NAME,
                    'site_timezone_snapshot' => self::SITE_TIMEZONE,
                    'created_at' => $now,
                    'confirmed_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('point_ledger_entries')->insert([
                    'id' => (string) Str::uuid(),
                    'member_id' => (string) $member->id,
                    'booking_id' => $bookingId,
                    'funding_source' => 'personal',
                    'entry_type' => PointEntryType::Charge->value,
                    'point_delta' => '-'.$offering->point_price,
                    'source_reference' => 'booking:'.$bookingId.':personal-charge',
                    'reverses_id' => null,
                    'created_at' => $now,
                ]);
            }

            MvpCredentialFile::append("Member {$item['no']}: {$name} (NIK: {$nik})", "Username: {$nik} | Password: {$nik}");
        }
    }
}
