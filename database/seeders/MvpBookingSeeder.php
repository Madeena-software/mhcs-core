<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Modules\Member\Application\Services\Mvp03SiteReferenceService;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Time\Clock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MvpBookingSeeder extends Seeder
{
    private const SYNTHETIC_PRIMARY_SCHEDULE_START = '2026-08-13 03:00:00';

    private const SYNTHETIC_SECONDARY_SCHEDULE_START = '2026-08-13 05:00:00';

    private const SYNTHETIC_SCHEDULE_END = '2026-08-22 16:59:59';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new Mvp03Exception('MvpBookingSeeder is limited to local and testing environments.');
        }

        $member = DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('users.email', 'mvp-member-one@example.test')
            ->select('members.id')
            ->first();
        if ($member === null) {
            throw new Mvp03Exception('Run MvpMemberSeeder before MvpBookingSeeder.');
        }

        $refs = app(Mvp03SiteReferenceService::class)->bootstrap(
            'synthetic-operator-org-mvp03',
            'Synthetic Operator Organization',
            'synthetic-operator-site-mvp03',
            'SYN-MVP03',
            'Lokasi Sintetis MVP-03',
            'Asia/Jakarta',
        );
        $this->offering('SYN-CHEST-A', 'Sesi Foto Radiografi Dasar', '12.5000', true, false);
        $this->offering('SYN-CHEST-B', 'Sesi Foto Radiografi dengan Peninjauan', '25.7500', true, true);
        $rateId = app(Mvp03PointService::class)->ensureInitialLocalRate();
        $this->schedule($refs['site_id'], 'SYN-CHEST-A', self::SYNTHETIC_PRIMARY_SCHEDULE_START, self::SYNTHETIC_SCHEDULE_END);
        $this->schedule($refs['site_id'], 'SYN-CHEST-B', self::SYNTHETIC_SECONDARY_SCHEDULE_START, self::SYNTHETIC_SCHEDULE_END);
        app(Mvp03PointService::class)->creditPersonalForLocalTesting((string) $member->id, '100.0000', 'mvp03:synthetic-personal-credit:mvp-member-one');

        $this->command?->info('MVP-03 synthetic catalogue, schedules, active point rate, and personal funding are ready.');
        $this->command?->info('Safe next step: open /member/services after completing the synthetic Member profile.');
        $this->command?->info('Synthetic point rate ID: '.$rateId);
    }

    private function offering(string $code, string $name, string $price, bool $includesAi, bool $includesDoctor): string
    {
        $existing = DB::table('service_offerings')->where('code', $code)->first();
        if ($existing !== null) {
            if ($existing->name !== $name || (string) PointAmount::fromString((string) $existing->point_price) !== (string) PointAmount::fromString($price) || (bool) $existing->includes_ai !== $includesAi || (bool) $existing->includes_doctor !== $includesDoctor || ! $existing->active) {
                throw new Mvp03Exception('An existing synthetic service offering is inconsistent.');
            }

            return (string) $existing->id;
        }

        $id = (string) Str::uuid();
        $now = app(Clock::class)->now();
        DB::table('service_offerings')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'includes_ai' => $includesAi,
            'includes_doctor' => $includesDoctor,
            'point_price' => $price,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('member.service-offering.bootstrap', 'service-offering', $id, ['code' => $code]);

        return $id;
    }

    private function schedule(string $siteId, string $serviceCode, string $startsAt, string $endsAt): void
    {
        $service = DB::table('service_offerings')->where('code', $serviceCode)->firstOrFail();
        $existing = DB::table('shift_schedules')->where('examination_site_id', $siteId)->where('service_offering_id', $service->id)->where('starts_at', $startsAt)->first();
        if ($existing !== null) {
            if ($existing->ends_at !== $endsAt || (int) $existing->quota !== 5 || $existing->status !== 'open') {
                throw new Mvp03Exception('An existing synthetic schedule is inconsistent.');
            }

            return;
        }

        $id = (string) Str::uuid();
        $now = app(Clock::class)->now();
        DB::table('shift_schedules')->insert([
            'id' => $id,
            'examination_site_id' => $siteId,
            'service_offering_id' => $service->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'quota' => 5,
            'status' => 'open',
            'eligible_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('member.schedule.bootstrap', 'shift-schedule', $id, ['site_id' => $siteId, 'service_code' => $serviceCode, 'quota' => 5]);
    }

    private function audit(string $action, string $targetType, string $targetId, array $metadata): void
    {
        app(AuditStore::class)->append(AuditEvent::fromContext(
            AuthenticatedContext::anonymous()->forPurpose('member.local-bootstrap'),
            $action,
            'member',
            'success',
            app(Clock::class)->now(),
            $targetType,
            $targetId,
            metadata: $metadata,
        ));
    }
}
