<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveRadiographerAccess;
use App\Shared\Time\Clock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RadiographerLoginAccess implements InteractiveRadiographerAccess
{
    public function destination(User $user, ?string $intended): ?string
    {
        if (! $user->canAuthenticate() || ! is_string($intended) || ! preg_match('~^/radiographer/entry/([a-f0-9-]{36})\\?~', $intended, $matches)) {
            return null;
        }
        $request = Request::create(url($intended));
        if (! $request->hasValidSignature()) {
            return null;
        }
        $w = DB::table('operator_radiographer_workspaces')->where('id', $matches[1])->where('user_id', $user->id)->where('revoked', false)->first();
        $now = app(Clock::class)->now();
        if (! $w || (int) $request->query('generation') !== $w->generation || new \DateTimeImmutable($w->starts_at) > $now || new \DateTimeImmutable($w->expires_at) <= $now) {
            return null;
        }
        $e = DB::table('operator_radiographer_eligibility')->where('user_id', $user->id)->where('site_id', $w->site_id)->orderByDesc('revision')->first();
        if (! $e || $e->status !== 'verified' || ! $e->verifying_authority || ! $e->evidence_reference || ! DB::table('operator_sites')->where('id', $w->site_id)->where('active', true)->exists()) {
            return null;
        }

        return $intended;
    }
}
