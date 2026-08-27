<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Fixed, short-lived context switch owned by the nonclinical console boundary. */
final class NonclinicalValidationContextProvider implements AuthenticatedContextProvider
{
    private const SYSTEM_ACTOR = '00000000-0000-4000-8000-000000000000';

    private string $mode = 'system';

    private string $purpose = 'production.validation-context.account-provision';

    private ?string $memberId = null;

    public function current(): AuthenticatedContext
    {
        if ($this->mode === 'member' && $this->memberId !== null) {
            return new AuthenticatedContext(
                actorId: LocalId::fromString($this->memberId),
                operationId: new CorrelationId('nonclinical-validation:'.NonclinicalValidationContext::KEY),
                purpose: 'authenticated-session',
            );
        }

        return new AuthenticatedContext(
            actorId: LocalId::fromString(self::SYSTEM_ACTOR),
            operationId: new CorrelationId('nonclinical-validation:'.NonclinicalValidationContext::KEY),
            roles: ['system'],
            purpose: $this->purpose,
        );
    }

    public function accountProvisioning(): void
    {
        $this->mode = 'system';
        $this->purpose = 'production.validation-context.account-provision';
    }

    public function memberRegistration(): void
    {
        $this->mode = 'system';
        $this->purpose = 'member.nonclinical-validation';
    }

    public function pointFunding(): void
    {
        $this->mode = 'system';
        $this->purpose = 'member.nonclinical-validation.point-funding';
    }

    public function operatorProvisioning(): void
    {
        $this->mode = 'system';
        $this->purpose = 'production.validation-context.operator-context-provision';
    }

    public function bindValidationMember(string $memberId): void
    {
        if (! preg_match('/\A[0-9a-f-]{36}\z/i', $memberId)) {
            throw new LogicException('The fixed validation Member identity is invalid.');
        }
        if ($this->memberId !== null && $this->memberId !== $memberId) {
            throw new LogicException('The validation Member identity cannot be rebound.');
        }
        if ($this->memberId === null && ! $this->ownedMember($memberId)) {
            throw new LogicException('The validation Member identity is not an owned validation principal.');
        }

        $this->memberId ??= $memberId;
        $this->mode = 'member';
    }

    public function memberBooking(): void
    {
        if ($this->memberId === null) {
            throw new LogicException('The validation Member identity must be bound before booking.');
        }

        $this->mode = 'member';
    }

    private function ownedMember(string $memberId): bool
    {
        $events = DB::table('audit_events')
            ->where('action', 'production.validation-context.member-account.provisioned')
            ->where('target_type', 'App\\Models\\User')
            ->where('target_id', $memberId)
            ->where('outcome', 'success')
            ->get();

        return $events->count() === 1 && json_decode((string) $events->first()->metadata, true) === [
            'validation_context' => NonclinicalValidationContext::KEY,
            'nonclinical' => true,
            'principal_type' => 'member',
        ];
    }
}
