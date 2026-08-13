<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorPaperQuestionnaireContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class Mvp04PaperQuestionnaireService implements OperatorPaperQuestionnaireContract
{
    private const FORM_VERSION = 'V1';

    private const OPERATOR_PERMISSION = 'operator.portal.access';

    private const UPLOAD_PURPOSE = 'member.paper-questionnaire.upload';

    /** @var list<string> */
    private const UPLOAD_FORMATS = ['image/jpeg', 'image/png'];

    public function __construct(
        private TrustedOperatorSiteContextResolver $trustedSite,
        private PrivateObjectStore $objects,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @return array{questionnaire_id: string, completed_at: string} */
    public function record(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $operatorProfileId,
        string $memberId,
        string $bookingId,
        string $scheduleId,
        string $operationId,
        UploadedFile $photo,
    ): array {
        $this->assertContext($context, $operatorSiteId, $operatorProfileId, $operationId);
        $upload = $this->validatedUpload($photo);
        $storedObject = null;

        try {
            return DB::transaction(function () use (&$storedObject, $context, $operatorSiteId, $operatorProfileId, $memberId, $bookingId, $scheduleId, $operationId, $upload): array {
                $site = DB::table('examination_site_refs')
                    ->where('operator_site_id', $operatorSiteId)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();
                $booking = DB::table('bookings')
                    ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
                    ->where('bookings.id', $bookingId)
                    ->where('bookings.member_id', $memberId)
                    ->where('bookings.shift_schedule_id', $scheduleId)
                    ->where('bookings.status', 'checked_in')
                    ->where('bookings.examination_site_id_snapshot', $site?->id)
                    ->where('shift_schedules.examination_site_id', $site?->id)
                    ->lockForUpdate()
                    ->first();
                $profile = DB::table('operator_profiles')
                    ->where('id', $operatorProfileId)
                    ->where('user_id', (string) $context->actorId)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($site === null || $booking === null || $profile === null || DB::table('member_paper_questionnaires')->where('booking_id', $bookingId)->lockForUpdate()->exists()) {
                    throw new Mvp03Exception('The paper questionnaire is unavailable.');
                }

                $storedObject = $this->objects->put(
                    $upload['contents'],
                    $context->forPurpose(self::UPLOAD_PURPOSE),
                    self::UPLOAD_PURPOSE,
                );
                $now = $this->clock->now();
                $questionnaireId = (string) Str::uuid();
                DB::table('member_paper_questionnaires')->insert([
                    'id' => $questionnaireId,
                    'member_id' => $memberId,
                    'booking_id' => $bookingId,
                    'member_schedule_id' => $scheduleId,
                    'examination_site_id' => (string) $site->id,
                    'operator_site_id' => $operatorSiteId,
                    'operator_profile_id' => $operatorProfileId,
                    'completed_at' => $now,
                    'form_version' => self::FORM_VERSION,
                    'private_photo_object_key' => (string) $storedObject->key,
                    'private_photo_checksum' => $storedObject->checksum,
                    'private_photo_bytes' => $storedObject->bytes,
                    'private_photo_format' => $upload['format'],
                    'operation_id' => $operationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $metadata = [
                    'questionnaire_id' => $questionnaireId,
                    'booking_id' => $bookingId,
                    'examination_site_id' => (string) $site->id,
                    'operator_site_id' => $operatorSiteId,
                    'operator_profile_id' => $operatorProfileId,
                    'form_version' => self::FORM_VERSION,
                    'completed_at_utc' => $now->format(DATE_ATOM),
                    'has_private_photo' => true,
                ];
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    'member.paper-questionnaire.completed',
                    'member',
                    'success',
                    $now,
                    'member-paper-questionnaire',
                    $questionnaireId,
                    metadata: $metadata,
                ));
                $this->outbox->record(new VersionedDomainEvent(
                    LocalId::fromString((string) Str::uuid()),
                    'member.paper-questionnaire-completed',
                    1,
                    $now,
                    $metadata,
                    LocalId::fromString($questionnaireId),
                    $context->operationId,
                ));

                return ['questionnaire_id' => $questionnaireId, 'completed_at' => $now->format(DATE_ATOM)];
            });
        } catch (Mvp03Exception $exception) {
            $this->deleteQuietly($storedObject);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteQuietly($storedObject);

            throw new Mvp03Exception('The paper questionnaire could not be recorded.', previous: $exception);
        }
    }

    private function assertContext(AuthenticatedContext $context, string $operatorSiteId, string $operatorProfileId, string $operationId): void
    {
        if (
            $context->purpose !== self::PURPOSE
            || $context->actorId === null
            || $context->operationId === null
            || ! Str::isUuid($operatorProfileId)
            || ! Str::isUuid($operationId)
            || ! in_array('operator', $context->roles, true)
            || ! in_array(self::OPERATOR_PERMISSION, $context->permissions, true)
            || ! $this->trustedSite->matches($context, $operatorSiteId, self::OPERATOR_PERMISSION)
        ) {
            throw new Mvp03Exception('A trusted Operator questionnaire context is required.');
        }
    }

    /** @return array{contents: string, checksum: string, bytes: int, format: string} */
    private function validatedUpload(UploadedFile $photo): array
    {
        if (! $photo->isValid()) {
            throw new Mvp03Exception('The paper questionnaire photo is invalid.');
        }

        $path = $photo->getRealPath();
        $bytes = is_string($path) && is_file($path) ? filesize($path) : false;
        $contents = is_string($path) && is_file($path) ? file_get_contents($path) : false;
        if (! is_int($bytes) || $bytes < 1 || $bytes > (int) config('mhcs.upload.max_file_bytes') || ! is_string($contents) || strlen($contents) !== $bytes) {
            throw new Mvp03Exception('The paper questionnaire photo exceeds the approved boundary.');
        }

        $format = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $expectedType = $format === 'image/jpeg' ? IMAGETYPE_JPEG : ($format === 'image/png' ? IMAGETYPE_PNG : null);
        if (! in_array($format, self::UPLOAD_FORMATS, true) || $expectedType === null || ! is_array($image = @getimagesizefromstring($contents)) || ($image[2] ?? null) !== $expectedType) {
            throw new Mvp03Exception('The paper questionnaire photo format is not supported.');
        }

        return [
            'contents' => $contents,
            'checksum' => hash('sha256', $contents),
            'bytes' => $bytes,
            'format' => $format,
        ];
    }

    private function deleteQuietly(?PrivateObject $object): void
    {
        if ($object === null) {
            return;
        }

        try {
            $this->objects->delete($object);
        } catch (Throwable) {
        }
    }
}
