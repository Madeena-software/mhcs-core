<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorPaperConsentContract;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorPaperConsentConfirmationService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorPaperConsentContract $memberConsent,
    ) {}

    /** @return array<string, mixed> */
    public function view(string $caseId): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $summary = DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('bookings.id', $case->booking_id)
            ->select([
                'bookings.id as booking_id',
                'bookings.status as booking_status',
                'members.name as member_name',
                'members.medical_record_number',
                'service_offerings.name as service_name',
            ])
            ->first();
        if ($summary === null) {
            throw new OperatorException('paper_consent_unavailable', 'The paper-consent booking is unavailable.');
        }

        $consent = $this->memberConsent->view(
            $this->context($identity['context'], (string) $case->id),
            $site->operator_site_id,
            (string) $case->member_schedule_id,
            (string) $case->booking_id,
            (string) $case->id,
        );

        return [
            'case' => [
                'case_id' => (string) $case->id,
                'booking_id' => (string) $case->booking_id,
                'schedule_id' => (string) $case->member_schedule_id,
                'site_id' => (string) $site->getKey(),
                'state' => (string) $case->state,
                'decided_at' => (string) $case->decided_at,
            ],
            'summary' => [
                'booking_id' => (string) $summary->booking_id,
                'booking_status' => (string) $summary->booking_status,
                'member_name' => (string) $summary->member_name,
                'medical_record_number' => (string) $summary->medical_record_number,
                'service_name' => (string) $summary->service_name,
            ],
            'consent' => $consent,
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(
        string $caseId,
        string $formName,
        string $formVersion,
        string $signerType,
        bool $signatureConfirmed,
        string $signedAt,
        string $operationId,
        ?UploadedFile $scan = null,
    ): array {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('paper_consent_invalid', 'A valid paper-consent operation is required.');
        }

        try {
            return $this->memberConsent->confirm(
                $this->context($identity['context'], (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
                $formName,
                $formVersion,
                $signerType,
                $signatureConfirmed,
                $signedAt,
                $operationId,
                $scan,
            );
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('paper_consent_unavailable', 'The paper consent could not be confirmed.', $exception);
        }
    }

    /** @return array{0: array<string, mixed>, 1: object, 2: object} */
    private function matchedCase(string $caseId): array
    {
        $identity = $this->authorization->identity();
        $site = $this->authorization->portalSite($identity);
        if (! Str::isUuid(trim($caseId))) {
            throw new OperatorException('paper_consent_unavailable', 'The paper-consent case is unavailable.');
        }
        $case = DB::table('operator_identity_verifications')
            ->where('operator_identity_verifications.id', trim($caseId))
            ->where('operator_identity_verifications.operator_site_id', $site->getKey())
            ->where('operator_identity_verifications.operator_profile_id', $identity['profile']->getKey())
            ->where('operator_identity_verifications.state', 'matched')
            ->whereNull('operator_identity_verifications.active_claim_operator_profile_id')
            ->first();
        if ($case === null) {
            throw new OperatorException('paper_consent_unavailable', 'Only a matched identity case can confirm paper consent.');
        }

        return [$identity, $site, $case];
    }

    private function context(AuthenticatedContext $base, string $caseId): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: $base->actorId,
            operationId: $base->operationId,
            sessionId: $base->sessionId,
            roles: $base->roles,
            permissions: $base->permissions,
            siteId: $base->siteId,
            caseId: LocalId::fromString($caseId),
            purpose: OperatorPaperConsentContract::PURPOSE,
        );
    }
}
