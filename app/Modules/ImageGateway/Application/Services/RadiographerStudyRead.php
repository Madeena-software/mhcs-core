<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\Doctor\Application\Contracts\RadiologistAccess;
use App\Modules\ImageGateway\Application\Contracts\RadiographerStudySource;
use App\Modules\ImageGateway\Application\Contracts\RadiologistStudySource;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Modules\ImageGateway\Infrastructure\Dicom\BoundedDicomVerifier;
use App\Modules\Member\Application\Contracts\RadiographerCaseIdentity;
use App\Modules\Member\Application\Contracts\RadiologistCaseIdentity;
use App\Modules\Operator\Application\Contracts\RadiographerAccess;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class RadiographerStudyRead implements RadiographerStudySource, RadiologistStudySource
{
    public function selection(string $reference): ?object
    {
        try {
            return DB::transaction(function () use ($reference) {
                $access = app(RadiographerAccess::class);
                $access->authorizeManager();
                $row = DB::table('image_gateway_studies as s')->join('image_gateway_capture_sets as c', 'c.id', '=', 's.capture_set_id')
                    ->where('s.display_reference', $reference)->lockForUpdate()->select(['s.id as study_id', 'c.booking_id', 'c.admission_id', 'c.operator_site_id as site_id'])->first();
                if ($row) {
                    $access->authorizeManager($row->site_id);
                }
                app(AuditStore::class)->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current(), 'radiographer-workspace.read', 'image-gateway', 'success', app(Clock::class)->now(), targetType: 'study-selection'));

                return $row;
            });
        } catch (\Throwable) {
            app(RadiographerAccess::class)->denied();
            throw new DomainException('workspace_unavailable');
        }
    }

    public function inspect(string $workspace, int $generation): array
    {
        return app(RadiographerAccess::class)->run($workspace, 'dicom-inspected', function ($w) {
            return $this->inspectSource($w, app(RadiographerCaseIdentity::class)->resolve($w->id, $w->generation), 'radiographer.workspace');
        }, $generation);
    }

    public function handoffForStudy(string $reference): ?string
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('unavailable');
        }
        app(RadiologistAccess::class)->authorizeManager();
        $s = DB::table('image_gateway_studies as s')->join('image_gateway_capture_sets as c', 'c.id', '=', 's.capture_set_id')->where('s.display_reference', $reference)->lockForUpdate()->select(['s.id', 'c.operator_site_id'])->first();
        if (! $s) {
            return null;
        }
        app(RadiologistAccess::class)->authorizeManager($s->operator_site_id);

        return app(RadiologistHandoff::class)->forStudy($s->id);
    }

    public function assignment(string $handoff): array
    {
        return DB::transaction(function () use ($handoff) {
            $h = app(RadiologistHandoff::class)->selection($handoff);
            $identity = app(RadiologistCaseIdentity::class)->forAssignment($handoff);
            $source = $this->inspectSource($h, $identity, 'radiologist.workspace');
            if (! hash_equals($h->binding, $source['binding']) || ! in_array($source['actual']['modality'] ?? null, ['DX', 'CR'], true)) {
                throw new DomainException('source_changed');
            }
            app(AuditStore::class)->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current()->forPurpose('radiologist.workspace'), 'radiologist-workspace.assignment-source-inspected', 'image-gateway', 'success', app(Clock::class)->now(), targetType: 'handoff', targetId: $handoff));

            return ['binding' => $source['binding'], 'modality' => $source['actual']['modality']];
        });
    }

    public function inspectClinical(string $workspace, int $generation): array
    {
        return app(RadiologistAccess::class)->run($workspace, 'dicom-inspected', function ($w) {
            $w->protocol = app(RadiologistHandoff::class)->protocol($w->id, $w->generation);
            $identity = app(RadiologistCaseIdentity::class)->resolveClinical($w->id, $w->generation);
            $verified = $this->inspectSource($w, $identity, 'radiologist.workspace');
            if (! hash_equals($w->binding, $verified['binding'])) {
                throw new DomainException('source_changed');
            }

            return $verified;
        }, $generation);
    }

    private function inspectSource(object $w, array $identity, string $purpose): array
    {
        $s = DB::table('image_gateway_studies')->where('id', $w->study_id)->lockForUpdate()->first();
        $c = $s ? DB::table('image_gateway_capture_sets')->where('id', $s->capture_set_id)->lockForUpdate()->first() : null;
        if (! $s || ! $c || $s->format !== 'application/dicom'
            || $c->booking_id !== $w->booking_id || $c->admission_id !== $w->admission_id || $c->operator_site_id !== $w->site_id
            || (int) $c->radiograph_count !== 1 || $c->status !== 'accepted' || ! $c->accepted_at || $c->dicom_status !== 'success'
            || $w->member_schedule_id !== $c->member_schedule_id) {
            throw new DomainException('source_unverified');
        }
        $p = $w->protocol;
        $expected = ['patientId' => $identity['patientId'], 'patientName' => $identity['patientName']];
        foreach (['birthDate', 'sex'] as $key) {
            if ($identity[$key] !== null) {
                $expected[$key] = $identity[$key];
            }
        }
        $bytes = $this->readObject($s, $purpose);
        $actual = app(BoundedDicomVerifier::class)->verify($bytes, $s->checksum, (int) $s->bytes, $expected);
        foreach (['accessionNumber' => 'accession', 'studyId' => 'study_reference'] as $field => $column) {
            if ($actual[$field] && (! $p || $actual[$field] !== $p->$column)) {
                throw new DomainException('identity_unverified');
            }
        }
        $source = $this->reconcile($s, $c, $actual, $identity, $purpose);
        $claim = DB::table('image_gateway_workspace_sop_claims')->where('sop_uid', $actual['sopInstanceUid'])->lockForUpdate()->first();
        if ($claim && ($claim->study_id !== $s->id || $claim->checksum !== $s->checksum)) {
            throw new DomainException('duplicate_instance');
        }
        if (! $claim) {
            DB::table('image_gateway_workspace_sop_claims')->insert(['sop_uid' => $actual['sopInstanceUid'], 'study_id' => $s->id, 'checksum' => $s->checksum]);
        }
        // Modality is derived from checksum-bound bytes; keep existing handoff hashes stable.
        $bindingActual = $actual;
        unset($bindingActual['modality']);
        $binding = hash('sha256', json_encode([$s, $c, $identity, $p, $bindingActual, $source], JSON_THROW_ON_ERROR));
        $verified = ['actual' => $actual, 'identity' => $identity, 'source' => $source, 'protocol' => $p ? (array) $p : null, 'binding' => $binding];
        if (! DB::table('image_gateway_workspace_verifications')->where('binding', $binding)->exists()) {
            DB::table('image_gateway_workspace_verifications')->insert(['id' => (string) Str::uuid(), 'study_id' => $s->id, 'binding' => $binding, 'snapshot' => json_encode($verified, JSON_THROW_ON_ERROR), 'verified_at' => app(Clock::class)->now()]);
        }

        return $verified + ['bytes' => $bytes, 'display_reference' => $s->display_reference];
    }

    private function readObject(object $row, string $purpose): string
    {
        if ($row->bytes > BoundedDicomVerifier::MAX_BYTES || $row->bytes < 1) {
            throw new DomainException('source_unverified');
        }
        $context = app(AuthenticatedContextProvider::class)->current()->forPurpose($purpose);
        $store = app(PrivateObjectStore::class);
        $object = new PrivateObject(OpaqueObjectKey::fromString($row->object_key), $row->checksum, (int) $row->bytes, new DateTimeImmutable($row->created_at));
        $grant = $store->grant($object, $context, $purpose, $purpose, app(Clock::class)->now()->modify('+60 seconds'));
        $bytes = $store->get($grant, $context, $purpose, $purpose);
        if (strlen($bytes) !== (int) $row->bytes || ! hash_equals($row->checksum, hash('sha256', $bytes))) {
            throw new DomainException('source_unverified');
        }

        return $bytes;
    }

    private function reconcile(object $s, object $c, array $actual, array $identity, string $purpose): array
    {
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $manifestRows = DB::table('image_gateway_capture_objects')->where('capture_set_id', $c->id)->whereIn('object_type', ['manifest', 'manifest_signature'])->lockForUpdate()->get()->keyBy('object_type');
        $manifest = null;
        if ($c->conversion_job_id) {
            if ($manifestRows->count() !== 2) {
                throw new DomainException('manifest_unverified');
            }
            $raw = $this->readObject($manifestRows['manifest'], $purpose);
            $signature = $this->readObject($manifestRows['manifest_signature'], $purpose);
            $signed = app(ManifestSigner::class)->verify(SignedManifest::fromArray(json_decode($signature, true, flags: JSON_THROW_ON_ERROR)));
            if ($signed->conversionJobId !== $c->id || ! hash_equals($signed->metadataChecksum, hash('sha256', $raw))
                || ! hash_equals($signed->radiographChecksum, (string) $c->radiograph_checksum) || ! hash_equals($signed->gainChecksum, (string) $c->gain_checksum)
                || $c->manifest_checksum !== hash('sha256', $raw) || $c->signature_checksum !== hash('sha256', $signature)) {
                throw new DomainException('manifest_unverified');
            }
            $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['patient']['member_id'] ?? null) !== $identity['member_reference']
                || ($manifest['patient']['medical_record_number'] ?? null) !== $identity['patientId']
                || ($manifest['patient']['name'] ?? null) !== $identity['patientName']
                || ($manifest['examination']['service_request_id'] ?? null) !== $c->booking_id
                || ($manifest['site']['site_id'] ?? null) !== $identity['source_site_id']
                || ($manifest['site']['organization_id'] ?? null) !== $identity['source_organization_id']
                || (! empty($manifest['patient']['birth_date']) && $identity['birthDate'] !== null && str_replace('-', '', $manifest['patient']['birth_date']) !== $identity['birthDate'])
                || (! empty($manifest['patient']['sex']) && $identity['sex'] !== null && match ($manifest['patient']['sex']) {
                    'female' => 'F', 'male' => 'M', 'other' => 'O', default => null
                } !== $identity['sex'])) {
                throw new DomainException('manifest_unverified');
            }
            $sourceSex = match ($manifest['patient']['sex'] ?? null) {
                'female' => 'F', 'male' => 'M', 'other' => 'O', default => null
            };
            $sourceBirth = str_replace('-', '', (string) ($manifest['patient']['birth_date'] ?? ''));
            if (($sourceSex !== null && ! empty($actual['sex']) && $sourceSex !== $actual['sex'])
                || ($sourceBirth !== '' && ! empty($actual['birthDate']) && $sourceBirth !== $actual['birthDate'])
                || ! in_array($manifest['patient']['sex'] ?? null, [null, '', 'unknown', 'female', 'male', 'other'], true)) {
                throw new DomainException('manifest_unverified');
            }
            foreach (['study', 'series', 'sop'] as $kind) {
                $column = $kind.'_instance_uid';
                $field = $kind.'InstanceUid';
                $uid = $manifest['dicom'][$column] ?? '2.25.'.Uuid::uuid5($namespace, 'mpips:'.$kind.':'.$c->conversion_job_id)->getInteger()->toString();
                if ($actual[$field] !== $uid) {
                    throw new DomainException('uid_unverified');
                }
                $alias = '2.25.'.Uuid::uuid5($namespace, 'mpips:'.$kind.':'.$c->conversion_job_id)->getInteger()->toString();
                if ($uid !== $s->$column && $alias !== $s->$column) {
                    throw new DomainException('uid_unverified');
                }
            }

            $fields = [];
            foreach (['studyDescription' => ['examination', 'study_description'], 'bodyPart' => ['capture', 'body_part_examined'], 'projection' => ['capture', 'projection'], 'laterality' => ['capture', 'laterality'], 'detectorType' => ['capture', 'detector_type']] as $field => [$group, $key]) {
                $value = $manifest[$group][$key] ?? null;
                $embedded = $actual[$field] ?? null;
                $authority = $value === null || $value === '' ? 'unknown' : 'source-snapshot';
                if (in_array($value, ['U'], true)) {
                    $authority = 'fallback';
                }
                if ($value && $embedded && $value !== $embedded) {
                    $authority = 'conflicting';
                }
                $fields[$field] = ['value' => $value, 'embedded_value' => $embedded, 'authority' => $authority, 'source_reference' => hash('sha256', $raw), 'source_revision' => 1];
            }

            return ['path' => 'converted', 'manifest_digest' => hash('sha256', $raw), 'acquisition_time' => 'unknown', 'technical_fields' => $fields];
        }
        // A known alias algorithm alone is insufficient: require attributable receipt evidence.
        $receipts = DB::table('audit_events')->where('action', 'grabber.dicom.uploaded')->where('outcome', 'success')->where('target_id', $c->admission_id)->get(['metadata']);
        $receipt = $receipts->contains(function ($r) use ($s, $c) {
            $m = json_decode($r->metadata, true);

            return ($m['study_id'] ?? null) === $s->id && ($m['capture_id'] ?? null) === $c->id && ($m['checksum'] ?? null) === $s->checksum && ($m['admission_id'] ?? null) === $c->admission_id;
        });
        if (! $receipt) {
            throw new DomainException('receipt_unverified');
        }
        foreach (['study', 'series', 'sop'] as $kind) {
            $column = $kind.'_instance_uid';
            $alias = '2.25.'.Uuid::uuid5($namespace, 'grabber:'.$kind.':'.$s->id)->getInteger()->toString();
            if ($alias !== $s->$column) {
                throw new DomainException('uid_unverified');
            }
        }

        return ['path' => 'direct', 'aliases' => ['study' => $s->study_instance_uid, 'series' => $s->series_instance_uid, 'sop' => $s->sop_instance_uid], 'acquisition_time' => 'unknown'];
    }
}
