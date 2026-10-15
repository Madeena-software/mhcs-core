<?php

declare(strict_types=1);

namespace Tests\Feature\Radiographer;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiographerFixtures;
use Tests\TestCase;

final class RadiographerIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    private function workspace(array $f): array
    {
        $this->actingAs($f['admin']);
        $w = app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertRedirect();

        return $w;
    }

    public static function convertedPaths(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('convertedPaths')]
    public function test_signed_converted_manifest_and_supplied_uids_complete_handoff(bool $supplied): void
    {
        $f = RadiographerFixtures::converted(RadiographerFixtures::create(), $supplied);
        $w = $this->workspace($f);
        $service = app(RadiographerWorkspace::class);
        $r = $service->read($w['id'], 1);
        $this->assertSame('converted', $r['source']['path']);
        $this->assertSame('source-snapshot', $r['autofill']['projection']['authority']);
        $this->assertSame('fallback', $r['autofill']['laterality']['authority']);
        $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $this->assertSame('pending_radiologist_review', $service->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'converted'], 1)['state']);
        $this->assertSame($supplied ? '2.25.101' : $r['actual']['studyInstanceUid'], $r['actual']['studyInstanceUid']);
        $this->assertSame(hash('sha256', $f['bytes']), DB::table('image_gateway_studies')->where('id', $f['study'])->value('checksum'));
    }

    public function test_signed_source_demographic_conflict_is_denied_even_when_owner_sex_is_unknown(): void
    {
        $f = RadiographerFixtures::converted(RadiographerFixtures::create(), false, 'male');
        DB::table('members')->where('id', $f['member'])->update(['administrative_gender' => 'unspecified']);
        $w = $this->workspace($f);
        $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertForbidden()->assertDontSee('DICM');
    }

    public static function badSources(): array
    {
        return array_map(fn ($s) => [$s], ['checksum', 'patient', 'dob', 'alias', 'receipt', 'tuple', 'manifest', 'manifest_signature', 'site_version', 'unverified_identity']);
    }

    #[DataProvider('badSources')]
    public function test_unsafe_source_never_delivers_original_bytes(string $case): void
    {
        $f = RadiographerFixtures::create();
        if (str_starts_with($case, 'manifest')) {
            $f = RadiographerFixtures::converted($f);
        }
        $w = $this->workspace($f);
        match ($case) {
            'checksum' => DB::table('image_gateway_studies')->update(['checksum' => str_repeat('f', 64)]),
            'patient' => DB::table('members')->where('id', $f['member'])->update(['name' => 'DIFFERENT-SYNTHETIC']),
            'dob' => DB::table('members')->where('id', $f['member'])->update(['birth_date' => '1991-01-01']),
            'site_version' => DB::table('examination_site_refs')->update(['source_version' => 'stale']),
            'unverified_identity' => DB::table('members')->where('id', $f['member'])->update(['identity_status' => 'pending']),
            'alias' => DB::table('image_gateway_studies')->update(['study_instance_uid' => '2.25.999']),
            'receipt' => DB::table('audit_events')->where('action', 'grabber.dicom.uploaded')->delete(),
            'tuple' => DB::table('operator_radiographer_workspaces')->update(['booking_id' => 'wrong']),
            'manifest' => DB::table('image_gateway_capture_sets')->update(['manifest_checksum' => str_repeat('f', 64)]),
            'manifest_signature' => DB::table('image_gateway_capture_objects')->where('object_type', 'manifest_signature')->update(['checksum' => str_repeat('f', 64)]),
        };
        $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertForbidden()->assertDontSee('DICM')->assertDontSee('SYNTHETIC-MRN');
        $this->assertSame(0, DB::table('operator_radiographer_handoffs')->count());
    }

    public static function incompleteQc(): array
    {
        return array_map(fn ($s) => [$s], ['not_layak', 'escalate', 'missing_check', 'not_rendered', 'no_protocol', 'incomplete_set', 'unsupported_projection']);
    }

    #[DataProvider('incompleteQc')]
    public function test_incomplete_qc_or_protocol_denies_handoff(string $case): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->workspace($f);
        if ($case === 'no_protocol') {
            DB::table('operator_workspace_protocols')->delete();
        }
        if ($case === 'incomplete_set') {
            DB::table('operator_workspace_protocols')->update(['complete' => false]);
        }
        if ($case === 'unsupported_projection') {
            DB::table('operator_workspace_protocols')->update(['projection' => 'LAT']);
        }
        $service = app(RadiographerWorkspace::class);
        try {
            $r = $service->read($w['id'], 1);
        } catch (\DomainException) {
            $this->assertSame('no_protocol', $case);

            return;
        }
        $qc = RadiographerFixtures::qc(match ($case) {
            'not_layak' => 'Tidak Layak','escalate' => 'Perlu Eskalasi',default => 'Layak'
        });
        if ($case === 'missing_check') {
            $qc['checks']['coverage'] = false;
        }
        if ($case === 'not_rendered') {
            $qc['rendered'] = false;
        }
        $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => $qc], 1);
        $this->expectException(\DomainException::class);
        $service->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'incomplete'], 1);
    }

    public function test_audit_failure_withholds_bytes_and_rolls_back_qc_and_handoff(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->workspace($f);
        $service = app(RadiographerWorkspace::class);
        $r = $service->read($w['id'], 1);
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $e): void
            {
                throw new \RuntimeException('synthetic-audit-failure');
            }
        });
        $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertStatus(503)->assertDontSee('DICM');
        try {
            $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
            $this->fail('Unaudited write succeeded');
        } catch (\DomainException) {
        }
        $this->assertSame(0, DB::table('operator_radiographer_workspaces')->value('revision'));
        $this->assertSame(0, DB::table('operator_radiographer_handoffs')->count());
    }

    public function test_stale_revision_extra_identity_and_changed_protocol_cannot_handoff(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->workspace($f);
        $service = app(RadiographerWorkspace::class);
        $r = $service->read($w['id'], 1);
        $input = ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()];
        $bad = $input;
        $bad['overlay']['patientId'] = 'forged';
        try {
            $service->save($w['id'], $bad, 1);
            $this->fail('Identity edit accepted');
        } catch (\DomainException) {
        }
        $service->save($w['id'], $input, 1);
        try {
            $service->save($w['id'], $input, 1);
            $this->fail('Stale save accepted');
        } catch (\DomainException) {
        }
        DB::table('operator_workspace_protocols')->update(['revision' => 2]);
        $this->expectException(\DomainException::class);
        $service->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'stale'], 1);
    }

    public function test_revocation_during_required_audit_withholds_original(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->workspace($f);
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $e): void
            {
                if ($e->action === 'radiographer-workspace.dicom-delivery-authorized') {
                    DB::table('operator_radiographer_workspaces')->update(['revoked' => true, 'generation' => 2]);
                }
                (new DatabaseAuditStore)->append($e);
            }
        });
        $response = $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->baseResponse;
        ob_start();
        try {
            $response->sendContent();
            $this->assertStringNotContainsString('DICM', ob_get_contents());
        } finally {
            ob_end_clean();
        }
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_verified_synthetic_reopen_preserves_history_and_expiry(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->workspace($f);
        $service = app(RadiographerWorkspace::class);
        $r = $service->read($w['id'], 1);
        $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $h = $service->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'first'], 1);
        $expiry = DB::table('operator_radiographer_workspaces')->value('expires_at');
        $this->app->instance(ClinicalStatusSource::class, new class implements ClinicalStatusSource
        {
            public function readWhileLocked(string $case, string $binding, string $handoff): ?array
            {
                return ['case_id' => $case, 'binding' => $binding, 'handoff_id' => $handoff, 'status' => 'not_finalized', 'verified' => true, 'current' => true, 'coordinated' => true, 'complete_sources' => true, 'sources' => ['synthetic-only'], 'source_reference' => 'synthetic-reporting', 'revision' => '1', 'observed_at' => now()->toAtomString()];
            }
        });
        $this->actingAs($f['admin']);
        $service->manage($f['reference'], 'reopen', ['handoff' => $h['handoff'], 'reason' => 'SYNTHETIC-ONLY']);
        $this->assertSame($expiry, DB::table('operator_radiographer_workspaces')->value('expires_at'));
        $this->assertSame(2, DB::table('operator_radiographer_workspaces')->value('cycle'));
        $this->assertSame(1, DB::table('operator_radiographer_handoffs')->count());
        $this->assertSame(1, DB::table('operator_radiographer_history')->where('action', 'qc-recorded')->count());
        $this->actingAs($f['worker']);
        $service->save($w['id'], ['revision' => 2, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $new = $service->handoff($w['id'], ['revision' => 3, 'binding' => $r['binding'], 'key' => 'second'], 1);
        $this->assertNotSame($h['handoff'], $new['handoff']);
        $this->assertSame(2, DB::table('operator_radiographer_handoffs')->count());
    }
}
