<?php

declare(strict_types=1);

namespace Tests\Feature\Doctor;

use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RadiologistFixtures;
use Tests\TestCase;

final class RadiologistWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    private function issued(bool $converted = false): array
    {
        $f = RadiologistFixtures::create($converted);
        $this->actingAs($f['admin']);
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'credential', 'user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC-ONLY', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password'])->assertOk();
        $w = $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'issue', 'user' => $f['doctor']->id, 'password' => 'password'])->assertOk()->json();
        $this->actingAs($f['doctor']);
        $this->get($w['url'])->assertRedirect('/radiologist/workspaces/'.$w['id']);

        return [$f, $w];
    }

    private function payload(array $f, int $revision): array
    {
        return ['revision' => $revision, 'binding' => $f['binding'], 'handoff' => $f['handoff']];
    }

    public function test_direct_original_report_journey_and_immutable_finalization_blocks_reopen(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->get($base)->assertOk()->assertSee('SYNTHETIC^ONLY')->assertDontSee('AI report');
        $this->get($base.'/viewer')->assertOk();
        $this->get($base.'/dicom')->assertOk()->assertContent($f['bytes']);
        $this->postJson($base.'/quality', $this->payload($f, 0) + ['decision' => 'usable'])->assertOk()->assertJsonPath('revision', 1);
        $this->postJson($base.'/draft', $this->payload($f, 1) + ['findings' => 'Synthetic authored findings', 'conclusion' => 'Synthetic authored conclusion', 'recommendations' => ''])->assertOk()->assertJsonPath('revision', 2);
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'password' => 'wrong', 'key' => 'test-final'])->assertForbidden();
        $this->assertSame(0, DB::table('doctor_finalizations')->count());
        $final = $this->payload($f, 2) + ['attest' => true, 'password' => 'password', 'key' => 'test-final'];
        $this->postJson($base.'/finalize', $final)->assertOk()->assertJsonPath('state', 'finalized');
        $this->postJson($base.'/finalize', $final)->assertOk();
        $this->assertSame(1, DB::table('doctor_finalizations')->count());
        $this->postJson($base.'/draft', $this->payload($f, 2) + ['findings' => 'Overwrite', 'conclusion' => 'Overwrite', 'recommendations' => ''])->assertForbidden();
        $this->get($base)->assertOk()->assertSee('Final internal');
        $this->actingAs($f['admin']);
        try {
            app(RadiographerWorkspace::class)->manage($f['reference'], 'reopen', ['reason' => 'Synthetic attempt', 'handoff' => $f['handoff']]);
            $this->fail('Reopened final case');
        } catch (\DomainException) {
        }
        $this->assertSame($f['handoff'], DB::table('operator_radiographer_workspaces')->value('active_handoff'));
        $this->assertSame(hash('sha256', $f['bytes']), DB::table('image_gateway_studies')->where('id', $f['study'])->value('checksum'));
        $this->assertSame($f['doctor']->id, DB::table('doctor_finalizations')->value('author_id'));
        $audit = json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC-MRN', 'SYNTHETIC^ONLY', 'Synthetic authored', 'test-final', 'object_key'] as $s) {
            $this->assertStringNotContainsString($s, $audit);
        }
        $this->assertSame(0, DB::table('image_gateway_ai_jobs')->count());
    }

    public function test_converted_original_and_repeat_pending_preserves_draft_and_blocks_finalize(): void
    {
        [$f,$w] = $this->issued(true);
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->get($base.'/dicom')->assertOk()->assertContent($f['bytes']);
        $this->postJson($base.'/draft', $this->payload($f, 0) + ['findings' => '', 'conclusion' => '', 'recommendations' => ''])->assertOk();
        $this->postJson($base.'/quality', $this->payload($f, 1) + ['decision' => 'repeat_required', 'reason' => 'other'])->assertForbidden();
        $this->postJson($base.'/quality', $this->payload($f, 1) + ['decision' => 'repeat_required', 'reason' => 'other', 'explanation' => 'Synthetic artifact'])->assertOk()->assertJsonPath('state', 'repeat_pending');
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'password' => 'password', 'key' => 'repeat-final'])->assertForbidden();
        $this->assertSame(1, DB::table('doctor_report_versions')->count());
    }
}
