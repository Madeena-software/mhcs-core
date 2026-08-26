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
                $provider->useSystem('production.validation-context.account-provision');
                $accounts = $this->app->make(NonclinicalValidationAccountProvisioningService::class)->provision($secret);
                $provider->useSystem('member.nonclinical-validation');
                $member = $this->app->make(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData(self::OPERATION, $accounts['member_user_id']));

                $existingBookings = DB::table('bookings')->where('member_id', $member->memberId)->lockForUpdate()->get();
                if ($existingBookings->count() > 1 || ($existingBookings->isNotEmpty() && $existingBookings->first()->status !== 'confirmed')) {
                    throw new RuntimeException('The validation booking state is inconsistent.');
                }
                $replayedBooking = $existingBookings->isNotEmpty();
                $candidate = $this->candidate($replayedBooking ? (string) $existingBookings->first()->shift_schedule_id : null);

                $provider->useSystem('member.nonclinical-validation.point-funding');
                $funding = $this->app->make(Mvp03PointService::class)->ensureNonclinicalValidationBookingFunding($candidate->id);

                Auth::guard('web')->setUser(User::query()->findOrFail($member->userId));
                $provider->useMember($member->userId);
                $booking = $this->app->make(Mvp03BookingService::class)->createForCurrentMember($candidate->id, self::OPERATION.':booking-v1', $funding['point_cost']);
                Auth::guard('web')->logout();

                $provider->useSystem('production.validation-context.operator-context-provision');
                $operator = $this->app->make(NonclinicalValidationOperatorContextProvisioningService::class)->provision($accounts['operator_user_id'], $candidate->id, $candidate->operator_site_id, $candidate->eligible_id);

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
}
