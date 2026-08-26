<?php

declare(strict_types=1);

namespace App\Console\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\NonclinicalValidationMemberRegistrationData;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Operator\Application\Services\NonclinicalValidationOperatorContextProvisioningService;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use App\Shared\Validation\NonclinicalValidationAccountProvisioningService;
use App\Shared\Validation\NonclinicalValidationContext;
use App\Shared\Validation\NonclinicalValidationContextProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final readonly class NonclinicalValidationContextProvisioningService
{
    private const OPERATION = 'nonclinical-validation:'.NonclinicalValidationContext::KEY;

    public function __construct(private Application $app, private Clock $clock) {}

    /** @return array<string, string|bool> */
    public function provision(string $secret): array
    {
        if (trim($secret) === '' || ! $this->app->environment(['testing', 'production'])) {
            throw new RuntimeException('Validation provisioning is unavailable in this environment.');
        }

        $provider = new NonclinicalValidationContextProvider;
        $this->app->instance(AuthenticatedContextProvider::class, $provider);
        $original = Auth::user();
        try {
            return DB::transaction(function () use ($provider, $secret): array {
                $provider->accountProvisioning();
                $accounts = $this->app->make(NonclinicalValidationAccountProvisioningService::class)->provision($secret);
                $provider->memberRegistration();
                $member = $this->app->make(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData(self::OPERATION, $accounts['member_user_id']));

                $existingBookings = DB::table('bookings')->where('member_id', $member->memberId)->lockForUpdate()->get();
                if ($existingBookings->count() > 1 || ($existingBookings->isNotEmpty() && $existingBookings->first()->status !== 'confirmed')) {
                    throw new RuntimeException('The validation booking state is inconsistent.');
                }
                $replayedBooking = $existingBookings->isNotEmpty();
                $candidate = $this->candidate($replayedBooking ? (string) $existingBookings->first()->shift_schedule_id : null);

                $provider->pointFunding();
                $funding = $this->app->make(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($candidate->id);

                Auth::guard('web')->setUser(User::query()->findOrFail($member->userId));
                $provider->bindValidationMember($member->userId);
                $provider->memberBooking();
                $booking = $this->app->make(Mvp03BookingService::class)->createForCurrentMember($candidate->id, self::OPERATION.':booking-v1', $funding['point_cost']);
                Auth::guard('web')->logout();

                $provider->operatorProvisioning();
                $operator = $this->app->make(NonclinicalValidationOperatorContextProvisioningService::class)->provision($accounts['operator_user_id'], $candidate->id, $candidate->operator_site_id, $candidate->eligible_id);
                $this->assertFinalState($member->userId, $accounts['operator_user_id'], $candidate, $booking['booking_id'], $secret);

                return [
                    'validation_context_key' => NonclinicalValidationContext::KEY,
                    'environment_guard' => 'PASS', 'authorization_guard' => 'PASS',
                    'validation_member_state' => $accounts['member_state'], 'validation_operator_state' => $accounts['operator_state'],
                    'operator_minimum_permissions' => 'PASS', 'operator_profile_state' => $operator['profile_state'],
                    'operator_site_assignment' => 'PASS', 'operator_shift_assignment' => 'PASS',
                    'booking_state' => $booking['status'] === 'confirmed' ? ($replayedBooking ? 'EXISTING_VALID' : 'CREATED') : 'INVALID',
                    'arrival_state' => 'NOT_EXECUTED', 'ticket_state' => 'NOT_EXECUTED',
                    'basic_examination_state' => 'NOT_EXECUTED', 'xray_admission_state' => 'NOT_EXECUTED',
                    'capture_present' => false, 'validation_operator_login_ready' => true,
                    'audit_marker' => 'PASS', 'application_records_retention' => 'RETAINED', 'validation_context_provisioning' => 'PASS',
                ];
            });
        } finally {
            if ($original === null) {
                Auth::guard('web')->logout();
            } else {
                Auth::guard('web')->setUser($original);
            }
            $this->app->forgetInstance(AuthenticatedContextProvider::class);
        }
    }

    private function candidate(?string $scheduleId = null): object
    {
        $now = $this->clock->now();
        $rows = DB::table('shift_schedules as s')
            ->join('examination_site_refs as r', 'r.id', '=', 's.examination_site_id')
            ->join('operator_sites as os', function ($join): void {
                $join->on('os.operator_site_id', '=', 'r.operator_site_id')->where('os.active', true);
            })
            ->join('operator_eligible_shifts as e', function ($join): void {
                $join->on('e.member_schedule_id', '=', 's.id')->on('e.operator_site_id', '=', 'r.operator_site_id')->where('e.sync_status', 'eligible')->whereColumn('e.quota', 's.quota');
            })
            ->when($scheduleId === null, fn ($query) => $query->where('s.status', 'open')->where('s.starts_at', '>', $now))
            ->whereColumn('s.ends_at', '>', 's.starts_at')
            ->where('r.active', true)->whereNotNull('s.eligible_at')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))->from('service_offerings as so')->whereColumn('so.id', 's.service_offering_id')->where('so.active', true)->where('so.point_price', '>', 0);
            })
            ->when($scheduleId === null, fn ($query) => $query->whereRaw('(select count(*) from bookings b where b.shift_schedule_id = s.id and b.status in (\'pending_payment\',\'confirmed\',\'arrived\',\'checked_in\',\'in_progress\',\'postponed\')) < s.quota'))
            ->when($scheduleId !== null, fn ($query) => $query->where('s.id', $scheduleId))
            ->orderBy('s.starts_at')->orderBy('s.id')
            ->select('s.id', 'r.operator_site_id', 'e.id as eligible_id')->first();
        if ($rows === null) {
            throw new RuntimeException('No legitimate validation schedule is available.');
        }

        return $rows;
    }

    private function assertFinalState(string $memberUserId, string $operatorUserId, object $candidate, string $bookingId, string $secret): void
    {
        $this->assertPrincipalOwnership($memberUserId, 'member');
        $this->assertPrincipalOwnership($operatorUserId, 'operator');
        $operator = DB::table('users')->where('id', $operatorUserId)->first();
        if ($operator === null || $operator->account_status !== 'active' || ! (bool) $operator->login_enabled || (bool) $operator->must_change_password || ! Hash::check($secret, (string) $operator->password)) {
            throw new RuntimeException('The validation Operator login state is inconsistent.');
        }
        $member = DB::table('members')->where('user_id', $memberUserId)->get();
        $marker = DB::table('member_external_identifiers')->where('namespace', NonclinicalValidationContext::MARKER_NAMESPACE)->where('value', NonclinicalValidationContext::KEY)->get();
        $booking = DB::table('bookings')->where('id', $bookingId)->where('member_id', $member->first()?->id)->where('shift_schedule_id', $candidate->id)->where('status', 'confirmed')->where('booking_type', 'b2c')->where('funding_source', 'personal')->get();
        $profile = DB::table('operator_profiles')->where('user_id', $operatorUserId)->where('active', true)->get();
        $site = DB::table('operator_sites')->where('operator_site_id', $candidate->operator_site_id)->where('active', true)->first();

        if ($member->count() !== 1 || $marker->count() !== 1 || $member->first()->id !== $marker->first()->member_id || $booking->count() !== 1 || $profile->count() !== 1 || $site === null) {
            throw new RuntimeException('The validation context terminal state is inconsistent.');
        }
        if ($member->first()->identity_status !== 'nonclinical_validation' || $member->first()->registration_source !== 'nonclinical_validation' || $member->first()->identity_document_type !== null || $member->first()->encrypted_nik !== null || $member->first()->nik_lookup_digest !== null || DB::table('member_verification_assets')->where('member_id', $member->first()->id)->exists()) {
            throw new RuntimeException('The validation Member terminal state is inconsistent.');
        }
        $schedule = DB::table('shift_schedules')->where('id', $candidate->id)->first();
        $siteRef = $schedule === null ? null : DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->where('active', true)->first();
        $service = $schedule === null ? null : DB::table('service_offerings')->where('id', $schedule->service_offering_id)->where('active', true)->first();
        $eligible = DB::table('operator_eligible_shifts')->where('id', $candidate->eligible_id)->where('member_schedule_id', $candidate->id)->where('operator_site_id', $candidate->operator_site_id)->where('sync_status', 'eligible')->where('schedule_starts_at', $schedule?->starts_at)->where('schedule_ends_at', $schedule?->ends_at)->where('quota', $schedule?->quota)->get();
        if ($schedule === null || $siteRef === null || $service === null || $eligible->count() !== 1 || $booking->first()->service_offering_id !== $service->id || $booking->first()->examination_site_id_snapshot !== $siteRef->id || $booking->first()->service_code_snapshot !== $service->code || $booking->first()->site_code_snapshot !== $siteRef->code || $booking->first()->site_name_snapshot !== $siteRef->display_name || $booking->first()->site_timezone_snapshot !== $siteRef->timezone) {
            throw new RuntimeException('The validation schedule relationship is inconsistent.');
        }
        $cost = (string) $booking->first()->point_cost_snapshot;
        $funding = DB::table('point_ledger_entries')->where('member_id', $member->first()->id)->where('source_reference', 'nonclinical-validation:'.NonclinicalValidationContext::KEY.':booking-funding-v1')->whereNull('booking_id')->where('funding_source', 'personal')->where('entry_type', 'credit')->where('point_delta', $cost)->get();
        $charge = DB::table('point_ledger_entries')->where('booking_id', $bookingId)->where('funding_source', 'personal')->where('entry_type', 'charge')->where('source_reference', 'booking:'.$bookingId.':personal-charge')->where('point_delta', '-'.$cost)->get();
        if ($funding->count() !== 1 || $charge->count() !== 1 || abs((float) DB::table('point_ledger_entries')->where('member_id', $member->first()->id)->sum('point_delta')) > 0.00001 || DB::table('local_imaging_orders')->where('booking_id', $bookingId)->where('member_id', $member->first()->id)->where('shift_schedule_id', $candidate->id)->where('examination_site_id', $siteRef->id)->where('service_code_snapshot', $service->code)->where('status', 'authored')->count() !== 1) {
            throw new RuntimeException('The validation booking terminal state is inconsistent.');
        }
        if (! $this->ownedOperatorMarker('operator-profile.provisioned', (string) $profile->first()->id) || DB::table('operator_site_assignments')->where('operator_profile_id', $profile->first()->id)->where('operator_site_id', $site->id)->where('active', true)->whereNull('assigned_by_user_id')->count() !== 1 || DB::table('operator_shift_assignments')->where('operator_profile_id', $profile->first()->id)->where('operator_eligible_shift_id', $candidate->eligible_id)->where('status', 'active')->whereNull('assigned_by_user_id')->count() !== 1 || ! $this->ownedOperatorMarker('site-assignment.provisioned', (string) DB::table('operator_site_assignments')->where('operator_profile_id', $profile->first()->id)->where('operator_site_id', $site->id)->where('active', true)->value('id')) || ! $this->ownedOperatorMarker('shift-assignment.provisioned', (string) DB::table('operator_shift_assignments')->where('operator_profile_id', $profile->first()->id)->where('operator_eligible_shift_id', $candidate->eligible_id)->where('status', 'active')->value('id'))) {
            throw new RuntimeException('The validation Operator terminal state is inconsistent.');
        }
        foreach (['operator_arrivals', 'operator_identity_verifications', 'examination_consents', 'operator_paper_tickets', 'member_vital_signs_assessments', 'member_paper_questionnaires'] as $table) {
            if (DB::table($table)->where('booking_id', $bookingId)->exists()) {
                throw new RuntimeException('The validation context progressed beyond provisioning.');
            }
        }
        if (DB::table('operator_queue_admissions as admissions')->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')->where('tickets.booking_id', $bookingId)->exists() || DB::table('operator_vital_signs_executions as executions')->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'executions.operator_queue_admission_id')->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')->where('tickets.booking_id', $bookingId)->exists()) {
            throw new RuntimeException('The validation context progressed beyond provisioning.');
        }
    }

    private function assertPrincipalOwnership(string $userId, string $type): void
    {
        $events = DB::table('audit_events')->where('action', 'production.validation-context.'.$type.'-account.provisioned')->where('target_id', $userId)->where('outcome', 'success')->get();
        if ($events->count() !== 1 || json_decode((string) $events->first()->metadata, true) !== ['validation_context' => NonclinicalValidationContext::KEY, 'nonclinical' => true, 'principal_type' => $type]) {
            throw new RuntimeException('The validation principal ownership is inconsistent.');
        }
    }

    private function ownedOperatorMarker(string $suffix, string $targetId): bool
    {
        if ($targetId === '') {
            return false;
        }
        $events = DB::table('audit_events')->where('action', 'production.validation-context.'.$suffix)->where('target_id', $targetId)->where('outcome', 'success')->get();

        return $events->count() === 1 && json_decode((string) $events->first()->metadata, true) === ['validation_context' => NonclinicalValidationContext::KEY, 'nonclinical' => true, 'provisioning_actor' => 'system', 'human_assignment_performed' => false];
    }
}
