<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveRadiologistAccess;
use App\Modules\Operator\Application\Contracts\PendingRadiographerHandoff;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RadiologistLoginAccess implements InteractiveRadiologistAccess
{
    public function destination(User $user, ?string $intended): ?string
    {
        if (! $user->canAuthenticate() || ! is_string($intended) || ! preg_match('~^/radiologist/entry/([a-f0-9-]{36})\\?~', $intended, $m)) {
            return null;
        }
        $request = Request::create(url($intended));
        if (! $request->hasValidSignature() || ! ctype_digit((string) $request->query('generation'))) {
            return null;
        }

        $allowed = DB::transaction(function () use ($user, $m, $request) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $w = DB::table('doctor_workspaces')->where('id', $m[1])->where('user_id', $user->id)->first();
            $now = app(Clock::class)->now();
            if (! $fresh || ! $fresh->canAuthenticate() || ! $w || $w->revoked || $w->generation !== (int) $request->query('generation')
                || new DateTimeImmutable($w->starts_at) > $now || new DateTimeImmutable($w->expires_at) <= $now) {
                return false;
            }
            if (! app(PendingRadiographerHandoff::class)->isCurrent($w->handoff_id, $w->binding)) {
                return false;
            }
            $e = DB::table('doctor_eligibility')->where('user_id', $user->id)->where('site_id', $w->site_id)->where('service_id', $w->service_id)->where('modality', $w->modality)->orderByDesc('revision')->lockForUpdate()->first();

            return $e && $e->id === $w->eligibility_id && $e->status === 'verified' && $e->evidence_reference && $e->verifier_id
                && new DateTimeImmutable($e->verified_at) <= $now && new DateTimeImmutable($e->valid_until) > $now;
        });

        // Entry independently rechecks full clinical scope and original-source integrity after login.
        return $allowed ? $intended : null;
    }
}
