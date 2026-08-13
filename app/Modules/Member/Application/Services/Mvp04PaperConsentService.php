<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorPaperConsentContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class Mvp04PaperConsentService implements OperatorPaperConsentContract
{
    public const FORM_NAME = 'Informed Consent';

    public const FORM_VERSION = 'V1';

    public const SIGNER_TYPE = 'member';

    public const PURPOSE = OperatorPaperConsentContract::PURPOSE;

    private const UPLOAD_PURPOSE = 'operator.paper-consent.upload';

    /** @var list<string> */
    private const UPLOAD_FORMATS = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private TrustedOperatorIdentityVerificationContextResolver $trustedCase,
        private IdempotencyStore $idempotency,
        private PrivateObjectStore $objects,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function confirm(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $formName,
        string $formVersion,
        string $signerType,
        bool $signatureConfirmed,
        string $signedAt,
        string $idempotencyId,
        ?UploadedFile $scan = null,
    ): array {
        $this->assertInput($context, $formName, $formVersion, $signerType, $signatureConfirmed, $idempotencyId);
        $signed = $this->instant($signedAt);
        $upload = $this->validatedUpload($scan);
        $payload = [
            'operator_site_id' => trim($operatorSiteId),
            'schedule_id' => trim($scheduleId),
            'booking_id' => trim($bookingId),
            'case_id' => trim($caseId),
            'form_name' => $formName,
            'form_version' => $formVersion,
            'signer_type' => $signerType,
            'signature_confirmed' => $signatureConfirmed,
            'signed_at' => $signed->format(DATE_ATOM),
            'scan' => $upload === null ? null : [
                'checksum' => $upload['checksum'],
                'bytes' => $upload['bytes'],
                'format' => $upload['format'],
            ],
        ];
        $storedObject = null;

        try {
            return $this->idempotency->run(
                trim($idempotencyId),
                'member.paper-consent.confirm',
                $payload,
                function () use (&$storedObject, $context, $operatorSiteId, $scheduleId, $bookingId, $caseId, $formName, $formVersion, $signerType, $signed, $idempotencyId, $upload): array {
                    return DB::transaction(function () use (&$storedObject, $context, $operatorSiteId, $scheduleId, $bookingId, $caseId, $formName, $formVersion, $signerType, $signed, $idempotencyId, $upload): array {
                        $assertion = $this->trustedCase->resolveForConsent($context, $operatorSiteId, $scheduleId, $bookingId, $caseId);
                        if ($assertion === null) {
                            throw new MemberIdentityException('The trusted paper-consent context is unavailable.');
                        }

                        $case = DB::table('operator_identity_verifications')->where('id', $caseId)->lockForUpdate()->first();
                        $booking = DB::table('bookings')
                            ->where('id', $bookingId)
                            ->where('shift_schedule_id', $scheduleId)
                            ->where('status', 'arrived')
                            ->lockForUpdate()
                            ->first();
                        $schedule = $booking === null ? null : DB::table('shift_schedules')->where('id', $scheduleId)->lockForUpdate()->first();
                        $site = $booking === null ? null : DB::table('examination_site_refs')
                            ->where('id', $booking->examination_site_id_snapshot)
                            ->where('operator_site_id', $operatorSiteId)
                            ->where('active', true)
                            ->first();
                        $member = $booking === null ? null : DB::table('members')->where('id', $booking->member_id)->lockForUpdate()->first();

                        if (
                            $case === null
                            || $case->state !== 'matched'
                            || $booking === null
                            || $booking->member_id !== $assertion['member_id']
                            || $schedule === null
                            || $schedule->examination_site_id !== $booking->examination_site_id_snapshot
                            || $site === null
                            || $member === null
                            || $case->decided_at === null
                        ) {
                            throw new MemberIdentityException('The arrived, identity-verified booking is unavailable.');
                        }

                        if (DB::table('examination_consents')->where('booking_id', $bookingId)->lockForUpdate()->exists()) {
                            throw new MemberIdentityException('Paper consent has already been confirmed for this booking.');
                        }

                        if ($upload !== null) {
                            $storedObject = $this->objects->put(
                                $upload['contents'],
                                $context->forPurpose(self::UPLOAD_PURPOSE),
                                self::UPLOAD_PURPOSE,
                            );
                        }

                        $now = $this->clock->now();
                        $consentId = (string) Str::uuid();
                        DB::table('examination_consents')->insert([
                            'id' => $consentId,
                            'member_id' => (string) $member->id,
                            'booking_id' => $bookingId,
                            'examination_site_id' => (string) $site->id,
                            'operator_site_id' => $operatorSiteId,
                            'form_name' => $formName,
                            'form_version' => $formVersion,
                            'signer_type' => $signerType,
                            'signer_member_id' => (string) $member->id,
                            'signature_confirmed' => true,
                            'signed_at' => $signed,
                            'confirmed_by_operator_id' => $assertion['operator_profile_id'],
                            'recorded_at' => $now,
                            'idempotency_id' => $idempotencyId,
                            'private_scan_object_key' => $storedObject === null ? null : (string) $storedObject->key,
                            'private_scan_checksum' => $storedObject?->checksum,
                            'private_scan_bytes' => $storedObject?->bytes,
                            'private_scan_format' => $upload['format'] ?? null,
                            'status' => 'confirmed',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $metadata = [
                            'consent_id' => $consentId,
                            'booking_id' => $bookingId,
                            'examination_site_id' => (string) $site->id,
                            'operator_site_id' => $operatorSiteId,
                            'operator_id' => $assertion['operator_profile_id'],
                            'form_name' => $formName,
                            'form_version' => $formVersion,
                            'signer_type' => $signerType,
                            'signature_confirmed' => true,
                            'signed_at' => $signed->format(DATE_ATOM),
                            'recorded_at' => $now->format(DATE_ATOM),
                            'has_private_scan' => $storedObject !== null,
                        ];
                        $this->audit->append(AuditEvent::fromContext(
                            $context,
                            'member.booking.paper-consent.confirmed',
                            'member',
                            'success',
                            $signed,
                            'booking',
                            $bookingId,
                            metadata: $metadata,
                        ));
                        $this->outbox->record(new VersionedDomainEvent(
                            LocalId::fromString((string) Str::uuid()),
                            'member.paper-consent-confirmed',
                            1,
                            $signed,
                            [
                                'consent_id' => $consentId,
                                'member_id' => (string) $member->id,
                                'booking_id' => $bookingId,
                                'examination_site_id' => (string) $site->id,
                                'operator_site_id' => $operatorSiteId,
                                'operator_id' => $assertion['operator_profile_id'],
                                'form_name' => $formName,
                                'form_version' => $formVersion,
                                'signer_type' => $signerType,
                                'signature_confirmed' => true,
                                'signed_at' => $signed->format(DATE_ATOM),
                            ],
                            LocalId::fromString($consentId),
                            $context->operationId,
                        ));

                        return [
                            'consent_id' => $consentId,
                            'member_id' => (string) $member->id,
                            'booking_id' => $bookingId,
                            'examination_site_id' => (string) $site->id,
                            'operator_site_id' => $operatorSiteId,
                            'confirmed_by_operator_id' => $assertion['operator_profile_id'],
                            'form_name' => $formName,
                            'form_version' => $formVersion,
                            'signer_type' => $signerType,
                            'signature_confirmed' => true,
                            'signed_at' => $signed->format(DATE_ATOM),
                            'recorded_at' => $now->format(DATE_ATOM),
                            'status' => 'confirmed',
                            'has_private_scan' => $storedObject !== null,
                        ];
                    });
                },
            )->result;
        } catch (IdempotencyConflict|MemberIdentityException $exception) {
            if ($storedObject !== null) {
                $this->deleteQuietly($storedObject);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($storedObject !== null) {
                $this->deleteQuietly($storedObject);
            }

            throw new MemberIdentityException('Paper consent could not be confirmed.', previous: $exception);
        }
    }

    /** @return array<string, mixed>|null */
    public function view(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array {
        $assertion = $this->trustedCase->resolveForConsent($context, $operatorSiteId, $scheduleId, $bookingId, $caseId);
        if ($assertion === null) {
            throw new MemberIdentityException('The trusted paper-consent context is unavailable.');
        }

        $consent = DB::table('examination_consents')->where('booking_id', $bookingId)->first();

        return $consent === null ? null : [
            'consent_id' => (string) $consent->id,
            'form_name' => (string) $consent->form_name,
            'form_version' => (string) $consent->form_version,
            'signer_type' => (string) $consent->signer_type,
            'signature_confirmed' => (bool) $consent->signature_confirmed,
            'signed_at' => (new DateTimeImmutable((string) $consent->signed_at, new DateTimeZone('UTC')))->format('Y-m-d'),
            'recorded_at' => (string) $consent->recorded_at,
            'has_private_scan' => $consent->private_scan_object_key !== null,
        ];
    }

    private function assertInput(
        AuthenticatedContext $context,
        string $formName,
        string $formVersion,
        string $signerType,
        bool $signatureConfirmed,
        string $idempotencyId,
    ): void {
        if ($context->purpose !== self::PURPOSE || $context->actorId === null || $context->operationId === null) {
            throw new MemberIdentityException('A trusted paper-consent context is required.');
        }
        if ($formName !== self::FORM_NAME || $formVersion !== self::FORM_VERSION) {
            throw new MemberIdentityException('Only the approved paper-consent form is supported.');
        }
        if ($signerType !== self::SIGNER_TYPE || ! $signatureConfirmed) {
            throw new MemberIdentityException('Only a confirmed Member signature is supported.');
        }
        if (! Str::isUuid(trim($idempotencyId))) {
            throw new MemberIdentityException('A valid paper-consent operation is required.');
        }
    }

    /** @return array{contents: string, checksum: string, bytes: int, format: string} */
    private function validatedUpload(?UploadedFile $scan): array
    {
        if ($scan === null) {
            throw new MemberIdentityException('A private signed-paper upload is required.');
        }
        if (! $scan->isValid()) {
            throw new MemberIdentityException('The private signed-paper upload is invalid.');
        }

        $path = $scan->getRealPath();
        $bytes = is_string($path) && is_file($path) ? filesize($path) : false;
        $contents = is_string($path) && is_file($path) ? file_get_contents($path) : false;
        if (! is_int($bytes) || $bytes < 1 || $bytes > (int) config('mhcs.upload.max_file_bytes') || ! is_string($contents) || strlen($contents) !== $bytes) {
            throw new MemberIdentityException('The private signed-paper upload exceeds the approved boundary.');
        }

        $format = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! is_string($format) || ! in_array($format, self::UPLOAD_FORMATS, true)) {
            throw new MemberIdentityException('The private signed-paper upload format is not supported.');
        }
        if ($format === 'application/pdf' && ! str_starts_with($contents, '%PDF-')) {
            throw new MemberIdentityException('The private signed-paper upload content is invalid.');
        }
        if (in_array($format, ['image/jpeg', 'image/png'], true)) {
            $image = @getimagesizefromstring($contents);
            $expectedType = $format === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if (! is_array($image) || ($image[2] ?? null) !== $expectedType) {
                throw new MemberIdentityException('The private signed-paper upload content is invalid.');
            }
        }

        return [
            'contents' => $contents,
            'checksum' => hash('sha256', $contents),
            'bytes' => $bytes,
            'format' => $format,
        ];
    }

    private function instant(string $value): DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            throw new MemberIdentityException('Paper-consent signing date must use YYYY-MM-DD.');
        }
        try {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new MemberIdentityException('Paper-consent signing date is invalid.');
            }

            return $date;
        } catch (Throwable $exception) {
            if ($exception instanceof MemberIdentityException) {
                throw $exception;
            }

            throw new MemberIdentityException('Paper-consent signing date is invalid.', previous: $exception);
        }
    }

    private function deleteQuietly(PrivateObject $object): void
    {
        try {
            $this->objects->delete($object);
        } catch (Throwable) {
        }
    }
}
