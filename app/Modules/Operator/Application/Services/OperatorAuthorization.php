<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final readonly class OperatorAuthorization
{
    public const ROLE = 'operator';

    public const PORTAL_ACCESS = 'operator.portal.access';

    public const SITE_READ = 'operator.site.read';

    public const SITE_MANAGE = 'operator.site.manage';

    public const PROFILE_READ = 'operator.profile.read';

    public const PROFILE_MANAGE = 'operator.profile.manage';

    public const ASSIGNMENT_READ = 'operator.assignment.read';

    public const ASSIGNMENT_MANAGE = 'operator.assignment.manage';

    public const SHIFT_READ = 'operator.shift.read';

    public const SHIFT_MANAGE = 'operator.shift.manage';

    public const ATTENDANCE_READ = 'operator.attendance.read';

    public const ARRIVAL_RECORD = 'operator.arrival.record';

    public const IDENTITY_VERIFY = 'operator.identity.verify';

    public const AUDIT_READ = 'operator.audit.read';

    public function __construct(private AuthenticatedContextProvider $context) {}

    /** @return array{context: AuthenticatedContext, user: User, profile: OperatorProfile} */
    public function portal(): array
    {
        $context = $this->current('operator.portal');
        $user = Auth::user();

        if (! $user instanceof User || (string) $context->actorId !== (string) $user->getAuthIdentifier()) {
            throw new OperatorException('operator_access_denied', 'Operator access is unavailable.');
        }

        if (! $user->canAuthenticate() || ! in_array(self::ROLE, $context->roles, true) || ! $this->has($context, self::PORTAL_ACCESS)) {
            throw new OperatorException('operator_access_denied', 'Operator access is unavailable.');
        }

        $profile = OperatorProfile::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('active', true)
            ->first();
        if ($profile === null) {
            throw new OperatorException('operator_access_denied', 'Operator access is unavailable.');
        }

        return ['context' => $context, 'user' => $user, 'profile' => $profile];
    }

    public function siteRead(): AuthenticatedContext
    {
        return $this->admin(self::SITE_READ, 'operator.site.read');
    }

    public function siteManage(): AuthenticatedContext
    {
        return $this->admin(self::SITE_MANAGE, 'operator.site.manage');
    }

    public function profileRead(): AuthenticatedContext
    {
        return $this->admin(self::PROFILE_READ, 'operator.profile.read');
    }

    public function profileManage(): AuthenticatedContext
    {
        return $this->admin(self::PROFILE_MANAGE, 'operator.profile.manage');
    }

    public function assignmentRead(): AuthenticatedContext
    {
        return $this->admin(self::ASSIGNMENT_READ, 'operator.assignment.read');
    }

    public function assignmentManage(): AuthenticatedContext
    {
        return $this->admin(self::ASSIGNMENT_MANAGE, 'operator.assignment.manage');
    }

    public function shiftRead(): AuthenticatedContext
    {
        return $this->admin(self::SHIFT_READ, 'operator.shift.read');
    }

    public function auditRead(): AuthenticatedContext
    {
        return $this->admin(self::AUDIT_READ, 'operator.audit.read');
    }

    public function shiftManage(): AuthenticatedContext
    {
        return $this->admin(self::SHIFT_MANAGE, 'operator.shift.manage');
    }

    public function portalSite(array $portal): OperatorSite
    {
        /** @var AuthenticatedContext $context */
        $context = $portal['context'];
        if ($context->siteId === null) {
            throw new OperatorException('active_site_required', 'Select an active site before continuing.');
        }

        $site = OperatorSite::query()
            ->whereKey((string) $context->siteId)
            ->where('active', true)
            ->first();
        if ($site === null || ! DB::table('operator_site_assignments')
            ->where('operator_profile_id', $portal['profile']->getKey())
            ->where('operator_site_id', $site->getKey())
            ->where('active', true)
            ->exists()) {
            throw new OperatorException('active_site_required', 'Select an authorized active site before continuing.');
        }

        return $site;
    }

    /** @return array{context: AuthenticatedContext, user: User, profile: OperatorProfile} */
    public function identity(): array
    {
        $portal = $this->portal();
        if (! $this->has($portal['context'], self::IDENTITY_VERIFY)) {
            throw new OperatorException('operator_identity_denied', 'Identity verification authorization is unavailable.');
        }

        return [
            ...$portal,
            'context' => $portal['context']->forPurpose('operator.identity'),
        ];
    }

    public function current(string $purpose): AuthenticatedContext
    {
        $context = $this->context->current();
        if ($context->actorId === null || $context->operationId === null) {
            throw new OperatorException('operator_access_denied', 'A trusted Operator context is required.');
        }

        return $context->purpose === $purpose ? $context : $context->forPurpose($purpose);
    }

    public function has(AuthenticatedContext $context, string $permission): bool
    {
        return in_array($permission, $context->permissions, true);
    }

    private function admin(string $permission, string $purpose): AuthenticatedContext
    {
        $context = $this->current($purpose);
        if (! in_array('administrator', $context->roles, true) || ! $this->has($context, $permission)) {
            throw new OperatorException('operator_admin_denied', 'Operator administration authorization is required.');
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $user->canAuthenticate()) {
            throw new OperatorException('operator_admin_denied', 'Operator administration authorization is unavailable.');
        }

        return $context;
    }
}
