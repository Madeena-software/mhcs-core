<?php

declare(strict_types=1);

namespace Tests\Unit\Doctor;

use App\Models\User;
use App\Modules\Doctor\Application\Services\ClinicalFinalizationGuard;
use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use App\Modules\ImageGateway\Application\Contracts\RadiologistStudySource;
use App\Modules\ImageGateway\Application\Services\RadiographerStudyRead;
use App\Modules\Member\Application\Contracts\RadiologistCaseIdentity;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiologistFixtures;
use Tests\Support\SyntheticRadiologistSource;
use Tests\TestCase;

/** Isolated command/auth tests with an explicit synthetic Gateway source; not imaging evidence. */
final class RadiologistCommandsTest extends TestCase
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
        $f = RadiologistFixtures::commands();
        $this->app->instance(RadiologistStudySource::class, new SyntheticRadiologistSource($f));
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

    public function test_command_lifecycle_and_immutable_finalization_blocks_reopen(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $excludedBefore = [DB::table('outbox_messages')->count(), DB::table('point_ledger_entries')->count()];
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
        $this->assertSame($excludedBefore, [DB::table('outbox_messages')->count(), DB::table('point_ledger_entries')->count()]);
    }

    public function test_repeat_pending_preserves_draft_and_blocks_finalize(): void
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

    public function test_default_and_maximum_duration_and_no_renewal(): void
    {
        [$f,$w] = $this->issued();
        $row = DB::table('doctor_workspaces')->first();
        $this->assertSame(86400, (new \DateTimeImmutable($row->expires_at))->getTimestamp() - (new \DateTimeImmutable($row->starts_at))->getTimestamp());
        $before = $row->expires_at;
        $this->get('/radiologist/workspaces/'.$w['id'])->assertOk();
        $this->assertSame($before, DB::table('doctor_workspaces')->value('expires_at'));
        $this->actingAs($f['admin']);
        foreach ([0, -1, 73] as $hours) {
            $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'reassign', 'generation' => 1, 'user' => $f['doctor']->id, 'hours' => $hours, 'password' => 'password'])->assertForbidden();
        }
        $result = $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'reassign', 'generation' => 1, 'user' => $f['doctor']->id, 'hours' => 72, 'password' => 'password'])->assertOk()->json();
        $row = DB::table('doctor_workspaces')->first();
        $this->assertSame(259200, (new \DateTimeImmutable($row->expires_at))->getTimestamp() - (new \DateTimeImmutable($row->starts_at))->getTimestamp());
        $this->actingAs($f['doctor']);
        $this->get($w['url'])->assertForbidden();
        $this->get($result['url'])->assertRedirect();
        $this->app->instance(Clock::class, new FrozenClock(new \DateTimeImmutable($row->expires_at)));
        $this->get('/radiologist/workspaces/'.$w['id'])->assertForbidden()->assertDontSee('SYNTHETIC-MRN');
    }

    #[DataProvider('accessChanges')]
    public function test_existing_session_and_prepared_output_lose_access(string $change): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $response = $this->get($base.'/dicom')->assertOk();
        match ($change) {
            'account' => DB::table('users')->where('id', $f['doctor']->id)->update(['account_status' => 'suspended']),
            'password' => DB::table('users')->where('id', $f['doctor']->id)->update(['password' => Hash::make('changed-password')]),
            'eligibility' => DB::table('doctor_eligibility')->update(['status' => 'revoked']),
            'expired-eligibility' => DB::table('doctor_eligibility')->update(['valid_until' => now()->subSecond()]),
            'scope' => DB::table('doctor_eligibility')->update(['modality' => 'CR']),
            'facility' => DB::table('doctor_eligibility')->update(['site_id' => (string) Str::uuid()]),
            'service' => DB::table('doctor_eligibility')->update(['service_id' => (string) Str::uuid()]),
            'future-verification' => DB::table('doctor_eligibility')->update(['verified_at' => now()->addHour()]),
            'missing-evidence' => DB::table('doctor_eligibility')->update(['evidence_reference' => '']),
            'not-issued-yet' => DB::table('doctor_workspaces')->update(['starts_at' => now()->addSecond()]),
            'handoff' => DB::table('operator_radiographer_workspaces')->update(['active_handoff' => null]),
            'revoke' => DB::table('doctor_workspaces')->update(['revoked' => true, 'generation' => 2]),
        };
        ob_start();
        $response->baseResponse->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $response->baseResponse->getStatusCode());
        $this->assertStringNotContainsString($f['bytes'], $body);
        $this->get($base)->assertForbidden()->assertDontSee('SYNTHETIC-MRN');
        try {
            app(RadiologistWorkspace::class)->read($w['id'], 1);
            $this->fail('Owner bypass');
        } catch (\DomainException) {
        }
    }

    public static function accessChanges(): array
    {
        return array_map(fn ($x) => [$x], ['account', 'password', 'eligibility', 'expired-eligibility', 'scope', 'facility', 'service', 'future-verification', 'missing-evidence', 'not-issued-yet', 'handoff', 'revoke']);
    }

    public function test_wrong_doctor_guest_and_missing_entry_session_are_denied(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertForbidden();
        $this->get($base)->assertForbidden();
        $this->actingAs($f['doctor']);
        $this->flushSession();
        $this->get($base)->assertForbidden();
        Auth::logout();
        $this->get($base)->assertForbidden();
    }

    public function test_reassignment_preserves_prior_drafts_and_requires_new_authorship(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->postJson($base.'/draft', $this->payload($f, 0) + ['findings' => 'Earlier Doctor findings', 'conclusion' => 'Earlier conclusion', 'recommendations' => ''])->assertOk();
        $replacement = User::factory()->create();
        $this->actingAs($f['admin']);
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'credential', 'user' => $replacement->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password'])->assertOk();
        $next = $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'reassign', 'generation' => 1, 'user' => $replacement->id, 'password' => 'password'])->assertOk()->json();
        $this->actingAs($replacement);
        $this->get($next['url'])->assertRedirect();
        $this->get($base)->assertOk()->assertDontSee('Earlier Doctor findings');
        $this->postJson($base.'/quality', $this->payload($f, 2) + ['decision' => 'usable'])->assertOk();
        $this->postJson($base.'/finalize', $this->payload($f, 3) + ['attest' => true, 'password' => 'password', 'key' => 'adopt-old'])->assertForbidden();
        $this->postJson($base.'/draft', $this->payload($f, 3) + ['findings' => 'Independent findings', 'conclusion' => 'Independent conclusion', 'recommendations' => ''])->assertOk();
        $this->postJson($base.'/finalize', $this->payload($f, 4) + ['attest' => true, 'password' => 'password', 'key' => 'new-author'])->assertOk();
        $this->assertSame(2, DB::table('doctor_report_versions')->count());
        $this->assertSame($replacement->id, DB::table('doctor_finalizations')->value('author_id'));
        $this->actingAs($f['doctor']);
        $this->get($w['url'])->assertForbidden();
        $this->get($base)->assertForbidden();
        $this->assertSame($f['doctor']->id, DB::table('doctor_report_versions')->orderBy('revision')->value('author_id'));
    }

    #[DataProvider('invalidDrafts')]
    public function test_unsafe_or_empty_content_cannot_finalize(string $findings, string $conclusion, bool $save): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->postJson($base.'/quality', $this->payload($f, 0) + ['decision' => 'usable'])->assertOk();
        $response = $this->postJson($base.'/draft', $this->payload($f, 1) + ['findings' => $findings, 'conclusion' => $conclusion, 'recommendations' => '']);
        if ($save) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
        $this->postJson($base.'/finalize', $this->payload($f, $save ? 2 : 1) + ['attest' => true, 'password' => 'password', 'key' => 'invalid-final'])->assertForbidden();
        $this->assertSame(0, DB::table('doctor_finalizations')->count());
    }

    public static function invalidDrafts(): array
    {
        return [['', 'Conclusion', true], ['Findings', '  ', true], ['<p></p>', 'Conclusion', false], ['&nbsp;', 'Conclusion', true], ['&lt;p&gt;&lt;/p&gt;', 'Conclusion', true], ['<script>alert(1)</script>', 'Conclusion', false]];
    }

    public function test_stale_unknown_and_forged_authorship_fields_reject_without_history(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $input = $this->payload($f, 0) + ['findings' => 'First', 'conclusion' => 'Conclusion', 'recommendations' => ''];
        $this->postJson($base.'/draft', $input + ['author_id' => $f['worker']->id])->assertForbidden();
        $this->postJson($base.'/draft', $input)->assertOk();
        $this->postJson($base.'/draft', $input)->assertForbidden();
        $this->assertSame(1, DB::table('doctor_report_versions')->count());
        $this->postJson($base.'/quality', array_replace($this->payload($f, 1), ['binding' => str_repeat('c', 64), 'decision' => 'usable']))->assertForbidden();
    }

    public function test_finalization_requires_per_action_credentials_attestation_and_throttles_failures(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->postJson($base.'/quality', $this->payload($f, 0) + ['decision' => 'usable'])->assertOk();
        $this->postJson($base.'/draft', $this->payload($f, 1) + ['findings' => 'Findings', 'conclusion' => 'Conclusion', 'recommendations' => ''])->assertOk();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'key' => 'test'])->assertForbidden();
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => false, 'password' => 'password', 'key' => 'test'])->assertForbidden();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'password' => 'incorrect', 'key' => 'test'])->assertForbidden();
        }
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'password' => 'password', 'key' => 'test'])->assertForbidden();
        $this->assertSame(0, DB::table('doctor_finalizations')->count());
        $this->assertSame(2, DB::table('doctor_workspaces')->value('revision'));
    }

    public function test_capability_separation_scope_and_forged_verifier_are_denied(): void
    {
        $f = RadiologistFixtures::commands();
        $this->app->instance(RadiologistStudySource::class, new SyntheticRadiologistSource($f));
        $this->actingAs($f['admin']);
        $input = ['action' => 'credential', 'user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC', 'valid_until' => now()->addDays(1)->toAtomString(), 'password' => 'password'];
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], $input + ['verifier_id' => $f['worker']->id])->assertForbidden();
        DB::table('authorization_permission_assignments')->where('permission', 'doctor.radiologist-workspace.credential')->update(['active' => false]);
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], $input)->assertForbidden();
        $this->assertSame(0, DB::table('doctor_eligibility')->count());
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'issue', 'user' => $f['doctor']->id, 'password' => 'password'])->assertForbidden();
        DB::table('doctor_workspace_managers')->update(['active' => false]);
        $this->get('/admin/radiologist-workspaces/'.$f['handoff'])->assertForbidden();
    }

    public function test_audit_failure_rolls_back_clinical_mutation_and_withholds_prepared_reply(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $response = $this->get($base)->assertOk();
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('synthetic append failure');
            }
        });
        ob_start();
        $response->baseResponse->sendContent();
        $body = ob_get_clean();
        $this->assertSame(503, $response->baseResponse->getStatusCode());
        $this->assertStringNotContainsString('SYNTHETIC-MRN', $body);
        $this->postJson($base.'/draft', $this->payload($f, 0) + ['findings' => 'Findings', 'conclusion' => 'Conclusion', 'recommendations' => ''])->assertStatus(503);
        $this->assertSame(0, DB::table('doctor_report_versions')->count());
        $this->assertSame(0, DB::table('doctor_workspaces')->value('revision'));
    }

    public function test_additive_migration_round_trip_preserves_original_and_upstream(): void
    {
        [$f,$w] = $this->issued();
        $original = (array) DB::table('image_gateway_studies')->first();
        $upstream = (array) DB::table('operator_radiographer_handoffs')->first();
        $migration = require database_path('migrations/2026_10_16_000001_create_radiologist_workspace_tables.php');
        $migration->down();
        $this->assertSame($original, (array) DB::table('image_gateway_studies')->first());
        $this->assertSame($upstream, (array) DB::table('operator_radiographer_handoffs')->first());
        $migration->up();
        $this->assertSame(0, DB::table('doctor_workspaces')->count());
    }

    public function test_head_and_range_cache_requests_still_use_final_authorization(): void
    {
        [$f,$w] = $this->issued();
        $url = '/radiologist/workspaces/'.$w['id'].'/dicom';
        $head = $this->head($url)->assertOk()->baseResponse;
        ob_start();
        $head->sendContent();
        $body = ob_get_clean();
        $this->assertSame('', $body);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'radiologist-workspace.dicom-delivery-authorized')->count());
        $pending = $this->get($url, ['Range' => 'bytes=0-132', 'If-None-Match' => '*'])->assertOk()->baseResponse;
        DB::table('doctor_workspaces')->update(['revoked' => true]);
        ob_start();
        $pending->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $pending->getStatusCode());
        $this->assertStringNotContainsString('DICM', $body);
    }

    public function test_commit_failure_rolls_back_final_report_status_and_history(): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->postJson($base.'/quality', $this->payload($f, 0) + ['decision' => 'usable'])->assertOk();
        $this->postJson($base.'/draft', $this->payload($f, 1) + ['findings' => 'Findings', 'conclusion' => 'Conclusion', 'recommendations' => ''])->assertOk();
        $before = DB::table('doctor_workspace_history')->count();
        $this->app['events']->listen(TransactionCommitting::class, function () {
            if (DB::table('doctor_finalizations')->count() > 0) {
                throw new \RuntimeException('Synthetic commit failure');
            }
        });
        $this->postJson($base.'/finalize', $this->payload($f, 2) + ['attest' => true, 'password' => 'password', 'key' => 'commit-fail'])->assertForbidden();
        $this->assertSame(0, DB::table('doctor_finalizations')->count());
        $this->assertSame($before, DB::table('doctor_workspace_history')->count());
        $this->assertSame('not_finalized', DB::table('doctor_workspaces')->value('clinical_status'));
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    public function test_unknown_inconsistent_and_superseded_reporting_evidence_stays_unknown(): void
    {
        [$f,$w] = $this->issued();
        $guard = app(ClinicalFinalizationGuard::class);
        $this->assertSame('not_finalized', $guard->refresh($f['i']['booking'], $f['binding'], $f['handoff']));
        DB::table('doctor_workspaces')->update(['clinical_status' => 'unknown']);
        $this->assertSame('unknown', $guard->refresh($f['i']['booking'], $f['binding'], $f['handoff']));
        DB::table('doctor_workspaces')->update(['clinical_status' => 'finalized']);
        $this->assertSame('unknown', $guard->refresh($f['i']['booking'], $f['binding'], $f['handoff']));
        DB::table('doctor_workspaces')->update(['clinical_status' => 'not_finalized']);
        DB::table('operator_radiographer_workspaces')->update(['active_handoff' => null]);
        $this->assertSame('unknown', $guard->refresh($f['i']['booking'], $f['binding'], $f['handoff']));
    }

    public function test_doctor_management_locator_and_page_need_no_radiographer_privileges(): void
    {
        [$f,$w] = $this->issued();
        $this->actingAs($f['admin']);
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'like', 'operator.radiographer-workspace.%')->update(['active' => false]);
        $this->app->instance(RadiologistStudySource::class, new RadiographerStudyRead);
        $this->get('/admin/radiologist-studies/'.$f['reference'])->assertRedirect('/admin/radiologist-workspaces/'.$f['handoff']);
        $this->get('/admin/radiologist-workspaces/'.$f['handoff'])->assertOk()->assertSee($f['doctor']->email);
        DB::table('doctor_workspace_managers')->update(['active' => false]);
        $this->get('/admin/radiologist-studies/'.$f['reference'])->assertForbidden();
    }

    public function test_credential_validity_offsets_are_stored_as_utc(): void
    {
        [$f,$w] = $this->issued();
        $this->actingAs($f['admin']);
        $this->postJson('/admin/radiologist-workspaces/'.$f['handoff'], ['action' => 'credential', 'user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC-OFFSET', 'valid_until' => '2099-05-06T12:00:00+07:00', 'password' => 'password'])->assertOk();
        $this->assertSame('2099-05-06 05:00:00', DB::table('doctor_eligibility')->orderByDesc('revision')->value('valid_until'));
        $this->actingAs($f['doctor']);
        $this->get('/radiologist/workspaces/'.$w['id'])->assertForbidden();
    }

    public function test_individual_login_rejects_forwarded_locator_and_accepts_only_current_assignee(): void
    {
        [$f,$w] = $this->issued();
        Auth::logout();
        $this->flushSession();
        $this->get($w['url'])->assertRedirect('/login');
        $this->post('/login', ['identifier' => $f['worker']->email, 'password' => 'password'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
        $this->post('/login', ['identifier' => $f['doctor']->email, 'password' => 'password'])->assertRedirect($w['url']);
        $this->assertAuthenticatedAs($f['doctor']);
        $this->get($w['url'])->assertRedirect('/radiologist/workspaces/'.$w['id']);
        $this->get('/operator/studies/'.$f['study'].'/download')->assertForbidden();
        $this->get('/operator/studies/'.$f['study'].'/ai-report')->assertForbidden();
    }

    #[DataProvider('approvedRepeatReasons')]
    public function test_task_approved_repeat_reason_vocabulary_is_preserved(string $reason): void
    {
        [$f,$w] = $this->issued();
        $base = '/radiologist/workspaces/'.$w['id'];
        $this->postJson($base.'/quality', $this->payload($f, 0) + ['decision' => 'repeat_required', 'reason' => $reason, 'explanation' => $reason === 'other' ? 'Synthetic explanation' : ''])->assertOk()->assertJsonPath('state', 'repeat_pending');
        $this->postJson($base.'/quality', $this->payload($f, 1) + ['decision' => 'repeat_required', 'reason' => 'positioning'])->assertForbidden();
        $this->assertSame(1, DB::table('doctor_workspace_history')->where('action', 'quality')->count());
    }

    public static function approvedRepeatReasons(): array
    {
        return [['operator_error'], ['equipment_failure'], ['incorrect_order'], ['medical_limitation'], ['other']];
    }

    public function test_assignment_owner_reads_are_audited_and_handoff_projection_minimizes_identity(): void
    {
        [$f,$w] = $this->issued();
        $this->actingAs($f['admin']);
        $h = DB::transaction(fn () => app(RadiologistHandoff::class)->selection($f['handoff']));
        $this->assertStringNotContainsString('SYNTHETIC-MRN', json_encode($h, JSON_THROW_ON_ERROR));
        $identity = DB::transaction(fn () => app(RadiologistCaseIdentity::class)->forAssignment($f['handoff']));
        $this->assertSame('SYNTHETIC-MRN', $identity['patientId']);
        $this->assertGreaterThan(0, DB::table('audit_events')->where('action', 'radiologist-workspace.handoff-read')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'radiologist-workspace.assignment-identity-read')->count());
    }
}
