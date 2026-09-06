<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Shared\Security\ProtectedIdentifierService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionMpipsGrabberRehearsalContext extends Command
{
    protected $signature = 'mhcs:provision-grabber-rehearsal
                            {--json : Output in machine-readable JSON}
                            {--site-code=SITE-REHEARSAL-01 : Unique site code identifier}
                            {--site-name=Klinik Pratama Rehearsal : Display name for the site}
                            {--grabber-id=GRABBER-REHEARSAL-01 : Unique Grabber client identifier}
                            {--patient-name=Siti Walkin Rehearsal : Synthetic patient display name}
                            {--patient-nik=900000000088 : Synthetic de-identified patient NIK}
                            {--base-url=http://127.0.0.1:8023 : Base URL for MHCS Grabber API}
                            {--env-out= : Path to write local gitignored MPIPS environment variables with 0600 permissions}
                            {--token-file= : Path to write raw token with 0600 permissions}
                            {--no-env-file : Do not write to default gitignored environment file}';

    protected $description = 'Provision repeatable local context for MPIPS Grabber rehearsal (site, shift, admission, 4-digit locator, and Grabber client credentials).';

    public function handle(
        GrabberClientService $grabberClientService,
        RadiographySessionLocatorService $locatorService,
        ProtectedIdentifierService $protectedIdentifierService
    ): int {
        $isJson = (bool) $this->option('json');
        $siteCode = (string) $this->option('site-code');
        $siteName = (string) $this->option('site-name');
        $grabberId = (string) $this->option('grabber-id');
        $patientName = (string) $this->option('patient-name');
        $patientNik = (string) $this->option('patient-nik');
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $envOut = (string) $this->option('env-out');
        $tokenFile = (string) $this->option('token-file');
        $noEnvFile = (bool) $this->option('no-env-file');

        $now = now();
        $tz = new DateTimeZone('Asia/Jakarta');
        $today = new DateTimeImmutable('now', $tz);
        $shiftStart = $today->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $shiftEnd = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        // 1. Organization & Site References
        $orgStableId = 'org-rehearsal-01';
        $orgLocal = DB::table('operator_organization_refs')->where('operator_organization_id', $orgStableId)->first();
        if ($orgLocal === null) {
            $orgLocalId = (string) Str::uuid();
            DB::table('operator_organization_refs')->insert([
                'id' => $orgLocalId,
                'operator_organization_id' => $orgStableId,
                'name' => 'Organisasi Rehearsal MPIPS',
                'source_version' => '1',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $orgLocalId = (string) $orgLocal->id;
        }

        $site = DB::table('operator_sites')->where('operator_site_id', $siteCode)->first();
        if ($site === null) {
            $siteLocalId = (string) Str::uuid();
            DB::table('operator_sites')->insert([
                'id' => $siteLocalId,
                'operator_site_id' => $siteCode,
                'organization_id' => $orgStableId,
                'organization_name' => 'Organisasi Rehearsal MPIPS',
                'code' => $siteCode,
                'display_name' => $siteName,
                'address_line' => 'Jl. Rehearsal Loopback No. 1, Jakarta',
                'timezone' => 'Asia/Jakarta',
                'active' => true,
                'source_version' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $siteLocalId = (string) $site->id;
            DB::table('operator_sites')->where('id', $siteLocalId)->update([
                'display_name' => $siteName,
                'active' => true,
                'updated_at' => $now,
            ]);
        }

        $siteRef = DB::table('examination_site_refs')->where('operator_site_id', $siteCode)->first();
        if ($siteRef === null) {
            $siteReferenceId = (string) Str::uuid();
            DB::table('examination_site_refs')->insert([
                'id' => $siteReferenceId,
                'operator_site_id' => $siteCode,
                'operator_organization_ref_id' => $orgLocalId,
                'code' => $siteCode,
                'display_name' => $siteName,
                'timezone' => 'Asia/Jakarta',
                'source_version' => '1',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $siteReferenceId = (string) $siteRef->id;
        }

        // Service offering
        $service = DB::table('service_offerings')->where('code', 'RAD-CHEST')->first();
        if ($service === null) {
            $serviceId = (string) Str::uuid();
            DB::table('service_offerings')->insert([
                'id' => $serviceId,
                'code' => 'RAD-CHEST',
                'name' => 'Radiography Chest Rehearsal',
                'includes_ai' => true,
                'includes_doctor' => false,
                'point_price' => '2.5000',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $serviceId = (string) $service->id;
        }

        // 2. Operator User & Profile
        $operatorUser = User::query()->where('email', 'operator-rehearsal@mhcs.test')->first();
        if ($operatorUser === null) {
            $operatorUser = User::factory()->create([
                'email' => 'operator-rehearsal@mhcs.test',
            ]);
        }

        $operatorProfile = DB::table('operator_profiles')->where('user_id', $operatorUser->id)->first();
        if ($operatorProfile === null) {
            $profileId = (string) Str::uuid();
            DB::table('operator_profiles')->insert([
                'id' => $profileId,
                'user_id' => $operatorUser->id,
                'display_name' => 'Operator Rehearsal',
                'employee_code' => 'OPR-REHEARSAL-001',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $profileId = (string) $operatorProfile->id;
        }

        // Assign operator to site
        DB::table('operator_site_assignments')->updateOrInsert(
            [
                'operator_profile_id' => $profileId,
                'operator_site_id' => $siteLocalId,
            ],
            [
                'id' => (string) Str::uuid(),
                'active' => true,
                'assigned_by_user_id' => $operatorUser->id,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        // Assign permissions
        $permissions = [
            'operator.portal.access',
            'operator.shift.manage',
            'operator.arrival.record',
            'operator.identity.verify',
            'operator.consent.manage',
            'operator.check-in.issue',
            'operator.queue.manage',
            'operator.study.view',
        ];
        foreach ($permissions as $perm) {
            DB::table('authorization_permission_assignments')->updateOrInsert(
                ['user_id' => $operatorUser->id, 'permission' => $perm],
                ['id' => (string) Str::uuid(), 'active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // 3. Shift Schedule & Eligible Shift
        $existingShift = DB::table('shift_schedules')
            ->where('examination_site_id', $siteReferenceId)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existingShift !== null) {
            $scheduleId = (string) $existingShift->id;
            $scheduleDisplayReference = (string) $existingShift->display_reference;
        } else {
            $scheduleId = (string) Str::uuid();
            $scheduleDisplayReference = 'JAD-'.strtoupper(substr($scheduleId, 0, 8));
            DB::table('shift_schedules')->insert([
                'id' => $scheduleId,
                'display_reference' => $scheduleDisplayReference,
                'examination_site_id' => $siteReferenceId,
                'service_offering_id' => $serviceId,
                'starts_at' => $shiftStart,
                'ends_at' => $shiftEnd,
                'quota' => 100,
                'status' => 'open',
                'eligible_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Close any older open shifts for this site to ensure unambiguous auto-resolution
        DB::table('shift_schedules')
            ->where('examination_site_id', $siteReferenceId)
            ->where('id', '!=', $scheduleId)
            ->whereIn('status', ['open', 'in_progress'])
            ->update(['status' => 'closed', 'updated_at' => $now]);

        $eligibleShift = DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->first();
        if ($eligibleShift === null) {
            $eligibleId = (string) Str::uuid();
            DB::table('operator_eligible_shifts')->insert([
                'id' => $eligibleId,
                'member_schedule_id' => $scheduleId,
                'operator_site_id' => $siteCode,
                'schedule_starts_at' => $shiftStart,
                'schedule_ends_at' => $shiftEnd,
                'confirmed_count_at_eligibility' => 1,
                'quota' => 100,
                'event_version' => 1,
                'source_event_id' => 'rehearsal:shift-eligible:'.$scheduleId,
                'eligible_at' => $now,
                'sync_status' => 'eligible',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $eligibleId = (string) $eligibleShift->id;
        }

        $shiftAssignment = DB::table('operator_shift_assignments')
            ->where('operator_eligible_shift_id', $eligibleId)
            ->where('operator_profile_id', $profileId)
            ->first();
        if ($shiftAssignment === null) {
            DB::table('operator_shift_assignments')->insert([
                'id' => (string) Str::uuid(),
                'operator_eligible_shift_id' => $eligibleId,
                'operator_profile_id' => $profileId,
                'assigned_by_user_id' => $operatorUser->id,
                'status' => 'active',
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. De-identified Member & Booking
        $protected = $protectedIdentifierService->protect($patientNik);
        $existingMember = DB::table('members')->where('nik_lookup_digest', $protected['lookup_digest'])->first();
        if ($existingMember !== null) {
            $memberId = (string) $existingMember->id;
            $mrn = (string) $existingMember->medical_record_number;
        } else {
            $memberUser = User::factory()->create([
                'email' => 'patient-'.Str::lower(Str::random(8)).'@rehearsal.test',
            ]);
            $memberId = (string) Str::uuid();
            $mrn = 'MRN-'.strtoupper(substr($memberId, 0, 8));

            DB::table('members')->insert([
                'id' => $memberId,
                'user_id' => $memberUser->id,
                'family_id' => null,
                'medical_record_number' => $mrn,
                'identity_status' => 'verified',
                'identity_document_type' => 'ktp',
                'encrypted_nik' => $protected['encrypted_display'],
                'nik_lookup_digest' => $protected['lookup_digest'],
                'name' => $patientName,
                'birth_date' => '1990-05-15',
                'administrative_gender' => 'female',
                'registration_source' => 'operator_walkin',
                'phone' => null,
                'current_address' => 'Jl. Rehearsal Sintetis No. 42',
                'emergency_contact_name' => 'Kontak Rehearsal',
                'emergency_contact_relationship' => 'Keluarga',
                'emergency_contact_phone' => '0800000000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Point exchange rate
        $rate = DB::table('point_exchange_rates')->where('status', 'active')->first();
        if ($rate === null) {
            $rateId = (string) Str::uuid();
            DB::table('point_exchange_rates')->insert([
                'id' => $rateId,
                'rupiah_per_point' => 10000,
                'status' => 'active',
                'effective_at' => $now,
                'configured_by_admin_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $rateId = (string) $rate->id;
        }

        // Booking
        $bookingId = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'member_id' => $memberId,
            'shift_schedule_id' => $scheduleId,
            'service_offering_id' => $serviceId,
            'examination_site_id_snapshot' => $siteReferenceId,
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => 'RAD-CHEST',
            'point_cost_snapshot' => '2.5000',
            'point_exchange_rate_id' => $rateId,
            'includes_ai_snapshot' => true,
            'includes_doctor_snapshot' => false,
            'site_code_snapshot' => $siteCode,
            'site_name_snapshot' => $siteName,
            'site_timezone_snapshot' => 'Asia/Jakarta',
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);

        // Paper Ticket
        $ticketId = (string) Str::uuid();
        $ticketNumber = 'T-'.strtoupper(substr($ticketId, 0, 6));
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $bookingId,
            'operator_site_id' => $siteLocalId,
            'member_schedule_id' => $scheduleId,
            'operator_profile_id' => $profileId,
            'ticket_number' => $ticketNumber,
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Queue Admission (stage=xray, state=waiting)
        $admissionId = (string) Str::uuid();
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $siteLocalId,
            'member_schedule_id' => $scheduleId,
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'waiting',
            'operator_profile_id' => null,
            'ready_at' => $now,
            'claimed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 5. Four-digit locator code allocation
        $locator = $locatorService->allocate(
            $admissionId,
            $siteLocalId,
            $scheduleId
        );
        $locatorCode = $locator->locator_code;

        // 6. Grabber Client & Token
        $existingClient = DB::table('grabber_clients')->where('grabber_id', $grabberId)->first();
        if ($existingClient !== null) {
            DB::table('grabber_clients')->where('id', $existingClient->id)->delete();
        }

        $createdGrabber = $grabberClientService->create(
            $grabberId,
            'Local Rehearsal MPIPS Grabber',
            $siteLocalId
        );
        $rawToken = $createdGrabber['raw_token'];

        $writtenEnvFile = null;
        if ($envOut !== '' || ! $noEnvFile) {
            $targetEnvOut = $envOut !== '' ? $envOut : base_path('mpips-grabber.env');
            $envDir = dirname($targetEnvOut);
            if (! is_dir($envDir)) {
                mkdir($envDir, 0700, true);
            }
            $envLines = [
                '# MPIPS Grabber Local Rehearsal Credentials (mode 0600)',
                '# Generated: '.now()->toIso8601String(),
                "MHCS_GRABBER_BASE_URL={$baseUrl}",
                "MHCS_GRABBER_TOKEN={$rawToken}",
                "MHCS_GRABBER_ID={$grabberId}",
                "MHCS_GRABBER_REHEARSAL_LOCATOR={$locatorCode}",
            ];
            $oldUmask = umask(0077);
            file_put_contents($targetEnvOut, implode("\n", $envLines)."\n", LOCK_EX);
            chmod($targetEnvOut, 0600);
            umask($oldUmask);
            $writtenEnvFile = $targetEnvOut;
        }

        $writtenTokenFile = null;
        if ($tokenFile !== '') {
            $tokenDir = dirname($tokenFile);
            if (! is_dir($tokenDir)) {
                mkdir($tokenDir, 0700, true);
            }
            $oldUmask = umask(0077);
            file_put_contents($tokenFile, $rawToken."\n", LOCK_EX);
            chmod($tokenFile, 0600);
            umask($oldUmask);
            $writtenTokenFile = $tokenFile;
        }

        $result = [
            'status' => 'ready',
            'site' => [
                'id' => $siteLocalId,
                'code' => $siteCode,
                'name' => $siteName,
            ],
            'shift' => [
                'id' => $scheduleId,
                'display_reference' => $scheduleDisplayReference,
                'starts_at' => $shiftStart,
                'ends_at' => $shiftEnd,
            ],
            'patient' => [
                'id' => $memberId,
                'name' => $patientName,
                'mrn' => $mrn,
                'deidentified_nik' => $patientNik,
            ],
            'queue' => [
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'admission_id' => $admissionId,
                'stage' => 'xray',
                'state' => 'waiting',
            ],
            'radiography_session' => [
                'locator_id' => (string) $locator->id,
                'locator_code' => $locatorCode,
                'status' => 'active',
            ],
            'grabber' => [
                'grabber_id' => $grabberId,
                'site_id' => $siteLocalId,
                'token' => '[REDACTED]',
                'token_present' => true,
                'env_file' => $writtenEnvFile,
                'token_file' => $writtenTokenFile,
            ],
            'endpoints' => [
                'manifest_url' => "/api/v1/grabber/radiography-sessions/{$locatorCode}/manifest",
                'manifest_lookup_url' => "/api/v1/grabber/manifest/{$locatorCode}",
                'upload_url' => "/api/v1/grabber/radiography-sessions/{$locatorCode}/dicom",
            ],
        ];

        if ($isJson) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('=== MHCS Core MPIPS Grabber Rehearsal Context Provisioned ===');
        $this->line("Site Code        : {$siteCode} ({$siteLocalId})");
        $this->line("Site Name        : {$siteName}");
        $this->line("Shift ID         : {$scheduleId} ({$scheduleDisplayReference})");
        $this->line("Patient MRN      : {$mrn} ({$patientName})");
        $this->line("Admission ID     : {$admissionId}");
        $this->line("Locator Code     : {$locatorCode}");
        $this->line("Grabber ID       : {$grabberId}");
        $this->line('Grabber Token    : [REDACTED]');
        if ($writtenEnvFile !== null) {
            $this->line("MPIPS Env File   : {$writtenEnvFile}");
        }
        if ($writtenTokenFile !== null) {
            $this->line("Token File       : {$writtenTokenFile}");
        }
        $this->line('');
        $this->comment('API Endpoints:');
        $this->line("Manifest GET     : /api/v1/grabber/radiography-sessions/{$locatorCode}/manifest");
        $this->line("Manifest Lookup  : /api/v1/grabber/manifest/{$locatorCode}");
        $this->line("Upload POST      : /api/v1/grabber/radiography-sessions/{$locatorCode}/dicom");

        return self::SUCCESS;
    }
}
