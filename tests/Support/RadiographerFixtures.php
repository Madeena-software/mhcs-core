<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class RadiographerFixtures
{
    public static function create(array $sourceTags = []): array
    {
        $f = AdminOperationsFixtures::create(['member.schedule.read', 'member.booking.read', 'operator.audit.read', 'operator.radiographer-workspace.issue', 'operator.radiographer-workspace.revoke', 'operator.radiographer-workspace.reassign', 'operator.radiographer-workspace.reopen']);
        $i = StudyWorklistFixtures::intake($f);
        $capture = StudyWorklistFixtures::capture($f, $i, 'completed');
        $study = StudyWorklistFixtures::study($capture);
        $worker = User::factory()->create(['email' => 'synthetic-radiographer-'.Str::lower(Str::random(6)).'@example.test']);
        DB::table('operator_workspace_managers')->insert(['user_id' => $f['admin']->id, 'site_id' => $f['operatorSiteId'], 'active' => true]);
        DB::table('operator_radiographer_eligibility')->insert(['id' => (string) Str::uuid(), 'user_id' => $worker->id, 'site_id' => $f['operatorSiteId'], 'revision' => 1, 'status' => 'verified', 'evidence_reference' => 'SYNTHETIC-QUALIFICATION', 'verifying_authority' => 'SYNTHETIC-ONLY', 'verified_at' => now()]);
        $bytes = SyntheticDicom::bytes($sourceTags);
        $object = app(PrivateObjectStore::class)->put($bytes, new AuthenticatedContext(actorId: LocalId::fromString($worker->id), operationId: CorrelationId::random(), purpose: 'radiographer.workspace'), 'radiographer.workspace');
        $uids = [];
        foreach (['study', 'series', 'sop'] as $kind) {
            $uids[$kind.'_instance_uid'] = '2.25.'.Uuid::uuid5(Uuid::NAMESPACE_DNS, 'grabber:'.$kind.':'.$study)->getInteger()->toString();
        }
        DB::table('image_gateway_studies')->where('id', $study)->update($uids + ['object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes]);
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['dicom_status' => 'success']);
        $member = DB::table('bookings')->where('id', $i['booking'])->value('member_id');
        DB::table('members')->where('id', $member)->update(['medical_record_number' => 'SYNTHETIC-MRN', 'name' => 'SYNTHETIC^ONLY', 'birth_date' => '1990-01-01', 'administrative_gender' => 'female']);
        DB::table('operator_workspace_protocols')->insert(['id' => (string) Str::uuid(), 'study_id' => $study, 'revision' => 1, 'source_checksum' => $object->checksum, 'reference' => 'B2-SYNTHETIC-ONLY', 'projection' => 'PA', 'description' => 'Synthetic chest', 'accession' => 'SYNTHETIC-EXAM', 'study_reference' => 'SYNTHETIC-STUDY', 'laterality' => 'not_applicable', 'complete' => true, 'approved' => true, 'verified_by' => 'SYNTHETIC-PROTOCOL-OWNER', 'verified_at' => now()]);
        app(AuditStore::class)->append(AuditEvent::fromContext(
            new AuthenticatedContext(actorId: LocalId::fromString($f['admin']->id)), 'grabber.dicom.uploaded', 'grabber', 'success', new \DateTimeImmutable, targetId: $i['admission'], metadata: ['study_id' => $study, 'capture_id' => $capture, 'admission_id' => $i['admission'], 'checksum' => $object->checksum]));
        $reference = DB::table('image_gateway_studies')->where('id', $study)->value('display_reference');

        return $f + compact('i', 'capture', 'study', 'worker', 'member', 'bytes', 'reference');
    }

    public static function converted(array $f, bool $supplied = false, string $manifestSex = 'female', array $sourceTags = []): array
    {
        $job = 'synthetic-job-'.$f['capture'];
        $uids = [];
        $tags = [];
        foreach (['study' => '0020000d', 'series' => '0020000e', 'sop' => '00080018'] as $kind => $tag) {
            $uids[$kind.'_instance_uid'] = '2.25.'.Uuid::uuid5(Uuid::NAMESPACE_DNS, 'mpips:'.$kind.':'.$job)->getInteger()->toString();
            $tags[$tag] = ['UI', $supplied ? ['study' => '2.25.101', 'series' => '2.25.201', 'sop' => '2.25.301'][$kind] : $uids[$kind.'_instance_uid']];
        }
        $tags['00020003'] = $tags['00080018'];
        $bytes = SyntheticDicom::bytes($sourceTags + $tags);
        $context = new AuthenticatedContext(actorId: LocalId::fromString($f['worker']->id), operationId: CorrelationId::random(), purpose: 'radiographer.workspace');
        $store = app(PrivateObjectStore::class);
        $object = $store->put($bytes, $context, 'radiographer.workspace');
        $manifest = ['patient' => ['member_id' => $f['member'], 'medical_record_number' => 'SYNTHETIC-MRN', 'name' => 'SYNTHETIC^ONLY', 'birth_date' => '1990-01-01', 'sex' => $manifestSex], 'examination' => ['service_request_id' => $f['i']['booking']]];
        if ($supplied) {
            $manifest['dicom'] = ['study_instance_uid' => '2.25.101', 'series_instance_uid' => '2.25.201', 'sop_instance_uid' => '2.25.301'];
        }
        $manifest['examination']['study_description'] = 'Synthetic chest';
        $manifest['capture'] = ['body_part_examined' => 'CHEST', 'projection' => 'PA', 'laterality' => 'U', 'detector_type' => 'TRX'];
        $manifest['site'] = ['site_id' => DB::table('operator_sites')->where('id', $f['operatorSiteId'])->value('operator_site_id'), 'organization_id' => DB::table('operator_sites')->where('id', $f['operatorSiteId'])->value('organization_id')];
        $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
        $signed = app(ManifestSigner::class)->sign(new ConversionManifest($f['capture'], str_repeat('a', 64), str_repeat('b', 64), hash('sha256', $raw), 1, new \DateTimeImmutable, 'synthetic-correlation', config('mhcs.security.manifest_key_id')));
        $signature = json_encode($signed->toArray(), JSON_THROW_ON_ERROR);
        foreach (['manifest' => $raw, 'manifest_signature' => $signature] as $type => $body) {
            $o = $store->put($body, $context, 'radiographer.workspace');
            DB::table('image_gateway_capture_objects')->insert(['id' => (string) Str::uuid(), 'capture_set_id' => $f['capture'], 'object_type' => $type, 'object_index' => 0, 'object_key' => (string) $o->key, 'checksum' => $o->checksum, 'bytes' => $o->bytes, 'format' => 'application/json', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('image_gateway_capture_sets')->where('id', $f['capture'])->update(['conversion_job_id' => $job, 'manifest_checksum' => hash('sha256', $raw), 'signature_checksum' => hash('sha256', $signature), 'radiograph_checksum' => str_repeat('a', 64), 'gain_checksum' => str_repeat('b', 64)]);
        DB::table('image_gateway_studies')->where('id', $f['study'])->update($uids + ['object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes]);
        DB::table('operator_workspace_protocols')->where('study_id', $f['study'])->update(['source_checksum' => $object->checksum]);

        return array_replace($f, ['bytes' => $bytes]);
    }

    public static function overlay(): array
    {
        return ['studyDescription' => 'Synthetic chest', 'bodyPart' => 'CHEST', 'projection' => 'PA', 'laterality' => 'not_applicable', 'reason' => 'Synthetic visual verification'];
    }

    public static function qc(string $outcome = 'Layak'): array
    {
        return ['outcome' => $outcome, 'reason' => $outcome === 'Layak' ? '' : 'Synthetic escalation', 'checks' => array_fill_keys(['identity', 'positioning', 'coverage', 'orientation', 'exposure', 'artifacts', 'completeness'], true), 'rendered' => true];
    }
}
