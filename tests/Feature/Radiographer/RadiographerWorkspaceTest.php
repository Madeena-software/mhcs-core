<?php

declare(strict_types=1);

namespace Tests\Feature\Radiographer;

use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RadiographerFixtures;
use Tests\TestCase;

final class RadiographerWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    public function test_issue_signed_entry_and_exact_actor_revocation(): void
    {
        $f = RadiographerFixtures::create();
        $this->actingAs($f['admin']);
        $w = app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id, 'hours' => 24]);
        $this->assertStringContainsString('signature=', $w['url']);
        $this->get($w['url'])->assertForbidden();
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertRedirect('/radiographer/workspaces/'.$w['id']);
        $this->actingAs($f['admin']);
        app(RadiographerWorkspace::class)->manage($f['reference'], 'revoke', []);
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertForbidden();
        $this->assertSame(1, DB::table('operator_radiographer_history')->where('action', 'revoked')->count());
    }

    public function test_original_view_overlay_qc_handoff_replay_and_unknown_reopen(): void
    {
        $f = RadiographerFixtures::create();
        $this->actingAs($f['admin']);
        $service = app(RadiographerWorkspace::class);
        $w = $service->manage($f['reference'], 'issue', ['user' => $f['worker']->id, 'hours' => 24]);
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertRedirect();
        $read = $service->read($w['id'], 1);
        $this->assertSame('2.25.100', $read['actual']['studyInstanceUid']);
        $this->assertSame('embedded-observed', $read['autofill']['projection']['authority']);
        $this->get('/operator/studies/'.$f['study'].'/download')->assertForbidden();
        $this->get('/operator/studies/'.$f['study'].'/ai-report')->assertForbidden();
        $this->get('/radiographer/workspaces/'.$w['id'])->assertOk()->assertDontSee('Download DICOM')->assertDontSee('PRIVATE_KEY_SENTINEL');
        $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertOk()->assertHeader('Content-Type', 'application/dicom')->assertContent($f['bytes']);
        $saved = $service->save($w['id'], ['revision' => 0, 'binding' => $read['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $this->assertSame(1, $saved['revision']);
        $input = ['revision' => 1, 'binding' => $read['binding'], 'key' => 'synthetic-retry'];
        $h = $service->handoff($w['id'], $input, 1);
        $this->assertSame($h, $service->handoff($w['id'], $input, 1));
        $this->assertSame(1, DB::table('operator_radiographer_handoffs')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'radiographer-workspace.handed-off')->count());
        $this->assertTrue($service->read($w['id'], 1)['read_only']);
        try {
            $service->save($w['id'], ['revision' => 1, 'binding' => $read['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
            $this->fail('Post-handoff write succeeded');
        } catch (\DomainException) {
        }
        $this->actingAs($f['admin']);
        try {
            $service->manage($f['reference'], 'reopen', ['reason' => 'Synthetic reopen', 'handoff' => $h['handoff']]);
            $this->fail('Unknown clinical status permitted reopen');
        } catch (\DomainException) {
        }
        $this->assertSame($h['handoff'], DB::table('operator_radiographer_workspaces')->value('active_handoff'));
        $this->assertSame(hash('sha256', $f['bytes']), DB::table('image_gateway_studies')->where('id', $f['study'])->value('checksum'));
        $this->assertSame(0, DB::table('image_gateway_ai_jobs')->count());
        $audit = json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC-MRN', 'SYNTHETIC^ONLY', 'Synthetic visual verification', $w['url'], 'object_key'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $audit);
        }
    }

    public function test_additive_migration_round_trip_preserves_original_study(): void
    {
        $f = RadiographerFixtures::create();
        $before = (array) DB::table('image_gateway_studies')->where('id', $f['study'])->first();
        $migration = require database_path('migrations/2026_10_15_000001_create_radiographer_workspace_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('operator_radiographer_workspaces'));
        $this->assertSame($before, (array) DB::table('image_gateway_studies')->where('id', $f['study'])->first());
        $migration->up();
        $this->assertTrue(Schema::hasTable('operator_radiographer_workspaces'));
    }

    public function test_guest_entry_is_neutral_and_never_discloses_case(): void
    {
        $this->get('/radiographer/workspaces/unknown')->assertStatus(403)->assertDontSee('SYNTHETIC-MRN');
    }
}
