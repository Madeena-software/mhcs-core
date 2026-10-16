<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveRadiologistAccess;
use Illuminate\Http\Request;

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

        // Full owner authorization occurs after login at entry; a locator grants no clinical data.
        return $intended;
    }
}
