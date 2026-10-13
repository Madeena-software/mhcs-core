<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\ArrivalReferenceIntegrity;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Shared\Authorization\AdminOperationalRead;
use App\Shared\Context\AuthenticatedContext;
use Illuminate\Support\Facades\DB;

final class AdminSessionArrivalRead implements AdminSessionArrivals
{
    public function authorize(): AuthenticatedContext
    {
        $context = app(OperatorAuthorization::class)->auditRead();
        if (! in_array('member.schedule.read', $context->permissions, true)) {
            throw new \RuntimeException('access_unconfirmed');
        }

        return $context;
    }

    public function mapping(array $reference): string
    {
        $sites = DB::table('operator_sites')->where('operator_site_id', $reference['stable'])->get();
        if ($sites->count() !== 1) {
            throw new \RuntimeException('integrity_unavailable');
        }
        $site = $sites->first();
        foreach (['organization_id' => 'organization', 'timezone' => 'timezone', 'source_version' => 'version', 'active' => 'active'] as $field => $key) {
            if ((string) $site->$field !== (string) $reference[$key]) {
                throw new \RuntimeException('integrity_unavailable');
            }
        }

        return $site->id;
    }

    public function read(string $facility, string $operatorSite, array $sessions, array $metadata): array
    {
        $gate = app(AdminOperationalRead::class);
        $permission = fn () => $this->authorize();
        if (! $gate->allowed($permission)) {
            return $gate->read($permission, fn () => null, 'operator.admin-session-arrivals.read', 'operator', 'arrivals', metadata: ['request_kind' => $metadata['request_kind'] ?? 'initial']);
        }
        try {
            $gate->authorize($permission);
            $rows = DB::table('operator_arrivals')->whereIn('member_schedule_id', $sessions)->select('booking_id', 'member_schedule_id', 'operator_site_id', 'status')->distinct()->get();
            foreach ($rows as $row) {
                if ($row->operator_site_id !== $operatorSite || $row->status !== 'recorded') {
                    throw new \RuntimeException('integrity_unavailable');
                }
            }
            $tuples = $rows->map(fn ($r) => ['booking_id' => $r->booking_id, 'member_schedule_id' => $r->member_schedule_id])->all();
            $integrity = app(ArrivalReferenceIntegrity::class)->validate($facility, $sessions, $tuples, $metadata);
            if ($integrity['state'] !== 'available') {
                throw new \RuntimeException('integrity_unavailable');
            }

            return $gate->read($permission, function () use ($rows, $sessions) {
                $values = array_fill_keys($sessions, 0);
                foreach ($rows->groupBy('member_schedule_id') as $id => $group) {
                    $values[$id] = $group->unique('booking_id')->count();
                }

                return $values;
            }, 'operator.admin-session-arrivals.read', 'operator', 'arrivals', $facility, $metadata);
        } catch (\Throwable) {
            return $gate->read($permission, fn () => throw new \RuntimeException('integrity_unavailable'), 'operator.admin-session-arrivals.read', 'operator', 'arrivals', $facility, $metadata);
        }
    }
}
