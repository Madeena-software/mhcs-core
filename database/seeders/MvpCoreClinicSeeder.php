<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Member\Domain\Enums\PointEntryType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MvpCoreClinicSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('MvpCoreClinicSeeder is limited to local and testing environments.');
        }

        if (! DB::table('users')->where('email', 'mvp-admin@example.test')->exists()) {
            $this->call(MvpAdminSeeder::class);
        }
        if (DB::table('users')->whereIn('email', [
            'mvp-member-one@example.test',
            'mvp-member-two@example.test',
            'mvp-member-three@example.test',
            'mvp-member-four@example.test',
            'mvp-member-five@example.test',
        ])->count() < 5) {
            $this->call(MvpMemberSeeder::class);
        }
        if (! DB::table('shift_schedules')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('examination_site_refs.operator_site_id', 'synthetic-operator-site-mvp03')
            ->exists()) {
            $this->call(MvpBookingSeeder::class);
        }
        $this->call(MvpOperatorSeeder::class);

        [$bookingId, $scheduleId, $siteId] = $this->confirmedBooking();
        $this->command?->info('Synthetic clinic-core bookings are ready; primary booking: '.$bookingId);
        $this->command?->info('Synthetic front-desk NIK lookup: 900000000101');
        $this->command?->info('Use /operator/attendance/'.$scheduleId.'?at=2026-08-13T03:30:00+00:00 for the front-desk walkthrough.');
        $this->command?->info('Use /lcd/'.$siteId.' on the separate LCD browser after calling a ticket.');
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function confirmedBooking(): array
    {
        $members = DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->whereIn('users.email', [
                'mvp-member-one@example.test',
                'mvp-member-two@example.test',
                'mvp-member-three@example.test',
                'mvp-member-four@example.test',
                'mvp-member-five@example.test',
            ])
            ->select(['members.id', 'users.email'])
            ->get()
            ->keyBy('email');
        $schedule = DB::table('shift_schedules')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->join('service_offerings', 'service_offerings.id', '=', 'shift_schedules.service_offering_id')
            ->where('examination_site_refs.operator_site_id', 'synthetic-operator-site-mvp03')
            ->orderBy('shift_schedules.starts_at')
            ->select([
                'shift_schedules.*',
                'examination_site_refs.code as site_code',
                'examination_site_refs.display_name as site_name',
                'examination_site_refs.timezone as site_timezone',
                'service_offerings.code as service_code',
                'service_offerings.point_price',
                'service_offerings.includes_ai',
                'service_offerings.includes_doctor',
            ])
            ->first();
        $siteId = DB::table('operator_sites')->where('operator_site_id', 'synthetic-operator-site-mvp03')->value('id');
        if ($members->count() !== 5 || $schedule === null || ! is_string($siteId)) {
            throw new RuntimeException('The synthetic Member, schedule, or Operator site is unavailable.');
        }

        $rateId = DB::table('point_exchange_rates')->where('status', 'active')->orderByDesc('effective_at')->value('id');
        if (! is_string($rateId)) {
            throw new RuntimeException('The synthetic point exchange rate is unavailable.');
        }

        $bookingIds = [];
        foreach ($members as $member) {
            if ((string) $member->email !== 'mvp-member-one@example.test') {
                app(Mvp03PointService::class)->creditPersonalForLocalTesting(
                    (string) $member->id,
                    '100.0000',
                    'mvp03:synthetic-personal-credit:'.str_replace('@example.test', '', (string) $member->email),
                );
            }
            $bookingIds[] = $this->ensureConfirmedBooking((string) $member->id, $schedule, $rateId);
        }

        return [(string) $bookingIds[0], (string) $schedule->id, $siteId];
    }

    private function ensureConfirmedBooking(string $memberId, object $schedule, string $rateId): string
    {
        $existing = DB::table('bookings')
            ->where('member_id', $memberId)
            ->where('shift_schedule_id', $schedule->id)
            ->first();
        if ($existing !== null) {
            $this->ensurePersonalCharge((string) $existing->id, $memberId, (string) $schedule->point_price);

            return (string) $existing->id;
        }

        $bookingId = (string) Str::uuid();
        $now = now();
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'member_id' => $memberId,
            'shift_schedule_id' => (string) $schedule->id,
            'service_offering_id' => (string) $schedule->service_offering_id,
            'examination_site_id_snapshot' => (string) $schedule->examination_site_id,
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => (string) $schedule->service_code,
            'point_cost_snapshot' => (string) $schedule->point_price,
            'point_exchange_rate_id' => $rateId,
            'includes_ai_snapshot' => (bool) $schedule->includes_ai,
            'includes_doctor_snapshot' => (bool) $schedule->includes_doctor,
            'site_code_snapshot' => (string) $schedule->site_code,
            'site_name_snapshot' => (string) $schedule->site_name,
            'site_timezone_snapshot' => (string) $schedule->site_timezone,
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ensurePersonalCharge($bookingId, $memberId, (string) $schedule->point_price);

        return $bookingId;
    }

    private function ensurePersonalCharge(string $bookingId, string $memberId, string $pointCost): void
    {
        if (DB::table('point_ledger_entries')
            ->where('booking_id', $bookingId)
            ->where('entry_type', PointEntryType::Charge->value)
            ->exists()) {
            return;
        }

        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'funding_source' => 'personal',
            'entry_type' => PointEntryType::Charge->value,
            'point_delta' => '-'.$pointCost,
            'source_reference' => 'booking:'.$bookingId.':personal-charge',
            'reverses_id' => null,
            'created_at' => now(),
        ]);
    }
}
