<?php

declare(strict_types=1);

namespace App\Modules\Operator\Infrastructure;

use App\Shared\Authorization\ActiveSiteResolver;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use Illuminate\Support\Facades\DB;

final class OperatorActiveSiteResolver implements ActiveSiteResolver
{
    public function resolve(AuthenticatedContext $context): ?LocalId
    {
        if ($context->actorId === null || ! function_exists('session') || ! app()->bound('session.store')) {
            return null;
        }

        $siteId = session()->get('operator.active_site_id');
        if (! is_string($siteId) || trim($siteId) === '') {
            return null;
        }

        $profileId = DB::table('operator_profiles')
            ->where('user_id', (string) $context->actorId)
            ->where('active', true)
            ->value('id');
        if (! is_string($profileId)) {
            return null;
        }

        $exists = DB::table('operator_site_assignments')
            ->join('operator_sites', 'operator_sites.id', '=', 'operator_site_assignments.operator_site_id')
            ->where('operator_site_assignments.operator_profile_id', $profileId)
            ->where('operator_site_assignments.operator_site_id', $siteId)
            ->where('operator_site_assignments.active', true)
            ->where('operator_sites.active', true)
            ->exists();

        return $exists ? LocalId::fromString($siteId) : null;
    }
}
