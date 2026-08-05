<?php

declare(strict_types=1);

namespace App\Modules\Operator\Infrastructure;

use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver as Contract;
use App\Shared\Context\AuthenticatedContext;
use Illuminate\Support\Facades\DB;

final class TrustedOperatorSiteContextResolver implements Contract
{
    public function matches(AuthenticatedContext $context, string $operatorSiteId, string $permission): bool
    {
        if (
            $context->actorId === null
            || $context->siteId === null
            || trim($operatorSiteId) === ''
            || trim($permission) === ''
            || ! in_array('operator', $context->roles, true)
            || ! in_array($permission, $context->permissions, true)
        ) {
            return false;
        }

        $profile = DB::table('operator_profiles')
            ->where('user_id', (string) $context->actorId)
            ->where('active', true)
            ->first();
        $site = DB::table('operator_sites')
            ->where('id', (string) $context->siteId)
            ->where('active', true)
            ->first();

        return $profile !== null
            && $site !== null
            && (string) $site->operator_site_id === trim($operatorSiteId)
            && DB::table('operator_site_assignments')
                ->where('operator_profile_id', $profile->id)
                ->where('operator_site_id', $site->id)
                ->where('active', true)
                ->exists();
    }
}
