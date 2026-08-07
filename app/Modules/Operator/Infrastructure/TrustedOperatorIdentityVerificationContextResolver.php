<?php

declare(strict_types=1);

namespace App\Modules\Operator\Infrastructure;

use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver as Contract;
use App\Shared\Context\AuthenticatedContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TrustedOperatorIdentityVerificationContextResolver implements Contract
{
    public function resolve(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->siteId === null
            || $context->caseId === null
            || (string) $context->caseId !== trim($caseId)
            || ! Str::isUuid(trim($caseId))
            || ! in_array('operator', $context->roles, true)
            || ! in_array('operator.portal.access', $context->permissions, true)
            || ! in_array('operator.identity.verify', $context->permissions, true)
            || ! in_array($context->purpose, [
                'operator.identity.lookup',
                'operator.identity.view',
                'operator.identity.previous',
                'operator.identity.asset',
            ], true)
        ) {
            return null;
        }

        $actorId = (string) $context->actorId;
        $stableSiteId = trim($operatorSiteId);
        if (! DB::table('users')
            ->where('id', $actorId)
            ->where('account_status', 'active')
            ->where('login_enabled', true)
            ->where('must_change_password', false)
            ->exists()) {
            return null;
        }

        $profileId = DB::table('operator_profiles')
            ->where('user_id', $actorId)
            ->where('active', true)
            ->value('id');
        if (! is_string($profileId) || trim($profileId) === '') {
            return null;
        }

        if (
            ! DB::table('authorization_role_assignments')
                ->where('user_id', $actorId)
                ->where('role', 'operator')
                ->where('active', true)
                ->exists()
            || ! DB::table('authorization_permission_assignments')
                ->where('user_id', $actorId)
                ->where('permission', 'operator.identity.verify')
                ->where('active', true)
                ->exists()
            || ! DB::table('authorization_permission_assignments')
                ->where('user_id', $actorId)
                ->where('permission', 'operator.portal.access')
                ->where('active', true)
                ->exists()
        ) {
            return null;
        }

        $site = DB::table('operator_sites')
            ->where('id', (string) $context->siteId)
            ->where('operator_site_id', $stableSiteId)
            ->where('active', true)
            ->first();
        if ($site === null) {
            return null;
        }

        $case = DB::table('operator_identity_verifications')
            ->where('id', trim($caseId))
            ->where('operator_profile_id', $profileId)
            ->where('operator_site_id', $site->id)
            ->where('active_claim_operator_profile_id', $profileId)
            ->where('state', 'open')
            ->first();
        if ($case === null || (string) $case->member_schedule_id !== trim($scheduleId) || (string) $case->booking_id !== trim($bookingId)) {
            return null;
        }

        $booking = DB::table('bookings')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('bookings.id', trim($bookingId))
            ->where('bookings.shift_schedule_id', trim($scheduleId))
            ->where('bookings.status', 'arrived')
            ->where('examination_site_refs.operator_site_id', $stableSiteId)
            ->whereColumn('bookings.examination_site_id_snapshot', 'examination_site_refs.id')
            ->select(['bookings.id as booking_id', 'bookings.member_id', 'shift_schedules.id as schedule_id'])
            ->first();
        if ($booking === null || (string) $booking->booking_id !== (string) $case->booking_id) {
            return null;
        }

        $arrival = DB::table('operator_arrivals')
            ->where('id', (string) $case->arrival_id)
            ->where('booking_id', (string) $booking->booking_id)
            ->where('operator_site_id', $site->id)
            ->where('status', 'recorded')
            ->first();
        if ($arrival === null) {
            return null;
        }

        if (! DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $site->id)->where('active', true)->exists()) {
            return null;
        }

        if (! DB::table('operator_shift_assignments')
            ->join('operator_eligible_shifts', 'operator_eligible_shifts.id', '=', 'operator_shift_assignments.operator_eligible_shift_id')
            ->where('operator_shift_assignments.operator_profile_id', $profileId)
            ->where('operator_shift_assignments.status', 'active')
            ->where('operator_eligible_shifts.member_schedule_id', trim($scheduleId))
            ->where('operator_eligible_shifts.operator_site_id', $stableSiteId)
            ->where('operator_eligible_shifts.sync_status', 'eligible')
            ->exists()) {
            return null;
        }

        return [
            'case_id' => (string) $case->id,
            'arrival_id' => (string) $arrival->id,
            'booking_id' => (string) $booking->booking_id,
            'schedule_id' => (string) $booking->schedule_id,
            'member_id' => (string) $booking->member_id,
            'operator_site_id' => $stableSiteId,
            'operator_site_local_id' => (string) $site->id,
            'operator_profile_id' => (string) $profileId,
            'prior_photos_revealed' => DB::table('operator_identity_verification_events')
                ->where('verification_id', (string) $case->id)
                ->where('event_type', 'previous_photos_revealed')
                ->exists(),
        ];
    }

    public function resolveForConsent(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->siteId === null
            || $context->caseId === null
            || (string) $context->caseId !== trim($caseId)
            || ! Str::isUuid(trim($caseId))
            || $context->purpose !== 'operator.paper-consent.confirm'
            || ! in_array('operator', $context->roles, true)
            || ! in_array('operator.portal.access', $context->permissions, true)
            || ! in_array('operator.identity.verify', $context->permissions, true)
        ) {
            return null;
        }

        $actorId = (string) $context->actorId;
        $stableSiteId = trim($operatorSiteId);
        if (! DB::table('users')
            ->where('id', $actorId)
            ->where('account_status', 'active')
            ->where('login_enabled', true)
            ->where('must_change_password', false)
            ->exists()) {
            return null;
        }

        $profileId = DB::table('operator_profiles')
            ->where('user_id', $actorId)
            ->where('active', true)
            ->value('id');
        if (! is_string($profileId) || trim($profileId) === '') {
            return null;
        }

        if (
            ! DB::table('authorization_role_assignments')->where('user_id', $actorId)->where('role', 'operator')->where('active', true)->exists()
            || ! DB::table('authorization_permission_assignments')->where('user_id', $actorId)->where('permission', 'operator.identity.verify')->where('active', true)->exists()
            || ! DB::table('authorization_permission_assignments')->where('user_id', $actorId)->where('permission', 'operator.portal.access')->where('active', true)->exists()
        ) {
            return null;
        }

        $site = DB::table('operator_sites')
            ->where('id', (string) $context->siteId)
            ->where('operator_site_id', $stableSiteId)
            ->where('active', true)
            ->first();
        if ($site === null) {
            return null;
        }

        $case = DB::table('operator_identity_verifications')
            ->where('id', trim($caseId))
            ->where('operator_profile_id', $profileId)
            ->where('operator_site_id', $site->id)
            ->whereNull('active_claim_operator_profile_id')
            ->where('state', 'matched')
            ->first();
        if ($case === null || (string) $case->member_schedule_id !== trim($scheduleId) || (string) $case->booking_id !== trim($bookingId)) {
            return null;
        }

        $booking = DB::table('bookings')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('bookings.id', trim($bookingId))
            ->where('bookings.shift_schedule_id', trim($scheduleId))
            ->where('bookings.status', 'arrived')
            ->where('examination_site_refs.operator_site_id', $stableSiteId)
            ->whereColumn('bookings.examination_site_id_snapshot', 'examination_site_refs.id')
            ->select(['bookings.id as booking_id', 'bookings.member_id', 'shift_schedules.id as schedule_id'])
            ->first();
        if ($booking === null || (string) $booking->booking_id !== (string) $case->booking_id) {
            return null;
        }

        if (! DB::table('operator_arrivals')
            ->where('id', (string) $case->arrival_id)
            ->where('booking_id', (string) $booking->booking_id)
            ->where('operator_site_id', $site->id)
            ->where('status', 'recorded')
            ->exists()) {
            return null;
        }

        if (! DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $site->id)->where('active', true)->exists()) {
            return null;
        }

        if (! DB::table('operator_shift_assignments')
            ->join('operator_eligible_shifts', 'operator_eligible_shifts.id', '=', 'operator_shift_assignments.operator_eligible_shift_id')
            ->where('operator_shift_assignments.operator_profile_id', $profileId)
            ->where('operator_shift_assignments.status', 'active')
            ->where('operator_eligible_shifts.member_schedule_id', trim($scheduleId))
            ->where('operator_eligible_shifts.operator_site_id', $stableSiteId)
            ->where('operator_eligible_shifts.sync_status', 'eligible')
            ->exists()) {
            return null;
        }

        return [
            'case_id' => (string) $case->id,
            'arrival_id' => (string) $case->arrival_id,
            'booking_id' => (string) $booking->booking_id,
            'schedule_id' => (string) $booking->schedule_id,
            'member_id' => (string) $booking->member_id,
            'operator_site_id' => $stableSiteId,
            'operator_site_local_id' => (string) $site->id,
            'operator_profile_id' => (string) $profileId,
        ];
    }
}
