<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\RadiographerCaseIdentity;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Modules\Operator\Application\Contracts\RadiographerAccess;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RadiographerCaseIdentityRead implements RadiographerCaseIdentity
{
    public function resolve(string $workspace, int $generation): array
    {
        return app(RadiographerAccess::class)->run($workspace, 'read', fn ($w) => $this->read($w->booking_id, $w->member_schedule_id, $w->site_id), $generation);
    }

    private function read(string $booking, string $schedule, string $site): array
    {
        $b = DB::table('bookings')->where('id', $booking)->lockForUpdate()->first();
        $s = DB::table('shift_schedules')->where('id', $schedule)->lockForUpdate()->first();
        $f = $b ? DB::table('examination_site_refs')->where('id', $b->examination_site_id_snapshot)->lockForUpdate()->first() : null;
        $organization = $f ? DB::table('operator_organization_refs')->where('id', $f->operator_organization_ref_id)->lockForUpdate()->first() : null;
        $m = $b ? DB::table('members')->where('id', $b->member_id)->lockForUpdate()->first() : null;
        if (! $b || ! $s || ! $f || ! $organization || ! $organization->active || ! $m || ! $f->active || $m->identity_status !== 'verified'
            || $b->shift_schedule_id !== $schedule || $s->examination_site_id !== $f->id
            || ! $m->medical_record_number || ! $m->name) {
            throw new DomainException('identity_unverified');
        }

        $mapped = app(AdminSessionArrivals::class)->mapping(['stable' => $f->operator_site_id, 'organization' => $organization->operator_organization_id, 'timezone' => $f->timezone, 'version' => $f->source_version, 'active' => $f->active]);
        if ($mapped !== $site) {
            throw new DomainException('identity_unverified');
        }

        return ['patientId' => $m->medical_record_number, 'patientName' => $m->name,
            'birthDate' => $m->birth_date ? str_replace('-', '', substr($m->birth_date, 0, 10)) : null,
            'sex' => match (strtolower((string) $m->administrative_gender)) {
                'female', 'f' => 'F', 'male', 'm' => 'M', 'other' => 'O', default => null
            },
            'source_site_id' => $f->operator_site_id, 'source_organization_id' => $organization->operator_organization_id, 'member_reference' => $m->id, 'facility_reference' => $f->id];
    }
}
