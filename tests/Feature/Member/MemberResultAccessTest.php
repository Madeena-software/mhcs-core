<?php

declare(strict_types=1);

namespace Tests\Feature\Member;

use App\Http\Controllers\Member\ResultController;
use App\Models\User;
use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use App\Modules\Doctor\Infrastructure\UnknownClinicalSourceInventory;
use App\Modules\Member\Application\Contracts\ResultPublication;
use App\Modules\Member\Application\Services\MemberResultAccess;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MemberResultFixtures;
use Tests\TestCase;

final class MemberResultAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        app()->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        config(['mhcs.private_object_disk' => 'member_result_feature', 'filesystems.disks.member_result_feature' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/member_result_feature')]]);
        Storage::fake('member_result_feature');
        $this->get('/login');
        request()->session()->start();
    }

    private function fixture(): array
    {
        return MemberResultFixtures::finalize(MemberResultFixtures::prepare());
    }

    private function member(array $f): void
    {
        $this->actingAs($f['memberUser']);
        $this->withSession([Auth::guard('web')->getName() => $f['memberUser']->id, 'member.result.credential' => hash('sha256', $f['memberUser']->getAuthPassword())]);
    }

    public function test_manual_exact_version_publication_and_existing_member_login(): void
    {
        $f = $this->fixture();
        $this->assertSame(0, DB::table('member_result_publications')->count(), 'Finalization must never publish');
        $p = MemberResultFixtures::publish($f);
        $this->assertSame(86400, strtotime(DB::table('member_result_publications')->value('expires_at')) - strtotime(DB::table('member_result_publications')->value('published_at')));
        $this->post('/logout');
        $this->get($p['url'])->assertRedirect('/login');
        $this->post('/login', ['identifier' => $f['memberUser']->email, 'password' => 'password'])->assertRedirect($p['url']);
        $r = $this->get($p['url'])->assertOk()->assertSee('Synthetic final findings.')->assertSee('Synthetic final conclusion.')->assertSee('Synthetic recommendation.')->assertSee($f['doctor']->email);
        foreach (['SYNTHETIC-MRN', 'SYNTHETIC^ONLY', 'object_key', 'attestation', 'AI report', 'point_cost', 'password', 'draft_id'] as $sentinel) {
            $r->assertDontSee($sentinel);
        }
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $r->headers->get('Referrer-Policy'));
        $this->assertSame(1, DB::table('member_result_history')->count());
        $audit = json_encode(DB::table('audit_events')->where('action', 'like', 'member.result.%')->get(), JSON_THROW_ON_ERROR);
        foreach (['Synthetic final', 'SYNTHETIC-MRN', 'signature=', $f['memberUser']->email, $f['doctor']->email] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $audit);
        }
    }

    public static function invalidation(): array
    {
        return array_map(fn ($s) => [$s], ['name-drift', 'mrn-drift', 'sex-drift', 'other-member', 'unverified', 'age17', 'future-birthday', 'missing-photo', 'rejected-document', 'pending-document', 'replaced-photo', 'disabled', 'suspended', 'password-required', 'changed-password', 'wrong-generation', 'tampered', 'expired', 'stale-generation', 'lineage', 'unknown-inventory', 'hold', 'suspend', 'revoke']);
    }

    #[DataProvider('invalidation')]
    public function test_independent_current_authorization_denies(string $change): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $url = $p['url'];
        if (in_array($change, ['hold', 'suspend', 'revoke', 'stale-generation'], true)) {
            app(MemberResultAccess::class)->manage($f['i']['booking'], $change === 'stale-generation' ? 'revoke' : $change, ['revision' => 1, 'key' => 'withhold']);
            if ($change === 'stale-generation') {
                MemberResultFixtures::publish($f, 2, 'new-generation');
            }
        }
        $this->member($f);
        match ($change) {
            'name-drift' => DB::table('members')->where('id', $f['member'])->update(['name' => 'Changed subject']),
            'mrn-drift' => DB::table('members')->where('id', $f['member'])->update(['medical_record_number' => 'CHANGED-MRN']),
            'sex-drift' => DB::table('members')->where('id', $f['member'])->update(['administrative_gender' => 'male']),
            'other-member' => $this->actingAs(User::factory()->create()),
            'unverified' => DB::table('members')->where('id', $f['member'])->update(['identity_status' => 'pending_verification']),
            'age17' => DB::table('members')->where('id', $f['member'])->update(['birth_date' => now()->subYears(17)->toDateString()]),
            'future-birthday' => DB::table('members')->where('id', $f['member'])->update(['birth_date' => now()->addDay()->toDateString()]),
            'missing-photo' => DB::table('member_verification_assets')->where('type', 'profile_photo')->delete(),
            'rejected-document' => DB::table('member_verification_assets')->where('type', 'ktp')->update(['review_status' => 'rejected']),
            'pending-document' => DB::table('member_verification_assets')->where('type', 'ktp')->update(['review_status' => 'pending']),
            'replaced-photo' => DB::table('member_verification_assets')->where('type', 'profile_photo')->update(['updated_at' => now()->addSecond()]),
            'disabled' => DB::table('users')->where('id', $f['memberUser']->id)->update(['login_enabled' => false]),
            'suspended' => DB::table('users')->where('id', $f['memberUser']->id)->update(['account_status' => 'suspended']),
            'password-required' => DB::table('users')->where('id', $f['memberUser']->id)->update(['must_change_password' => true]),
            'changed-password' => DB::table('users')->where('id', $f['memberUser']->id)->update(['password' => bcrypt('new-password')]),
            'wrong-generation' => $url = str_replace('generation=1', 'generation=2', $url),
            'tampered' => $url .= 'x',
            'expired' => app()->instance(Clock::class, new FrozenClock(now()->addDay()->toDateTimeImmutable())),
            'lineage' => DB::table('doctor_report_versions')->where('id', $f['report'])->update(['findings' => 'UNTRUSTED']),
            'unknown-inventory' => app()->instance(ClinicalSourceInventory::class, new UnknownClinicalSourceInventory),
            default => null,
        };
        $r = $this->get($url);
        $this->assertNotSame(200, $r->status());
        $r->assertDontSee('Synthetic final findings.')->assertDontSee($f['doctor']->email)->assertDontSee('SYNTHETIC-MRN');
    }

    public function test_exact_eighteenth_birthday_uses_trusted_date(): void
    {
        $this->travelTo(Carbon::parse('2008-01-01 00:00:00', 'UTC'));
        app()->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $this->member($f);
        $this->get($p['url'])->assertOk();
    }

    public function test_withdrawal_only_capability_and_invalid_lineage_still_allow_revocation(): void
    {
        $f = $this->fixture();
        MemberResultFixtures::publish($f);
        DB::table('authorization_permission_assignments')->where('permission', 'member.result-publication.publish')->delete();
        DB::table('doctor_clinical_guards')->update(['status' => 'unknown']);
        $this->get('/admin/member-results/'.$f['i']['booking'])->assertOk()->assertDontSee('Synthetic final findings.')->assertSee('Cabut akses');
        $this->post('/admin/member-results/'.$f['i']['booking'], ['action' => 'revoke', 'revision' => 1, 'key' => 'withdraw-invalid'])->assertOk();
        $this->assertSame('revoked', DB::table('member_result_publications')->value('state'));
    }

    public function test_prepared_response_withholds_after_persisted_session_invalidation(): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $this->member($f);
        $this->get($p['url'])->assertOk();
        $request = Request::create($p['url']);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $f['memberUser']);
        $prepared = app(ResultController::class)->entry($request, $p['id'], app(ResultPublication::class));
        session()->getHandler()->destroy(session()->getId());
        ob_start();
        $prepared->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $prepared->getStatusCode());
        $this->assertStringNotContainsString('Synthetic final findings.', $body);
    }

    public function test_unsigned_link_and_external_login_return_disclose_nothing(): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $this->member($f);
        $this->get(strtok($p['url'], '?'))->assertForbidden()->assertDontSee('Synthetic final findings.');
        $this->post('/logout');
        $this->withSession(['url.intended' => 'https://example.invalid/member/results/entry/anything']);
        $r = $this->post('/login', ['identifier' => $f['memberUser']->email, 'password' => 'password']);
        $this->assertStringNotContainsString('example.invalid', $r->headers->get('Location'));
    }

    public function test_report_template_escapes_authored_markup_and_exposes_only_approved_fields(): void
    {
        $html = view('member.results.report', ['findings' => '<script>alert(1)</script>', 'conclusion' => '<img src=x onerror=alert(2)>',
            'recommendations' => '', 'clinician' => 'Synthetic clinician', 'finalized_at' => '2040-01-01 00:00:00',
            'metadata' => ['examination' => 'Synthetic examination', 'facility' => 'Synthetic facility', 'date' => '2040-01-01']])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_original_subject_identity_drift_before_publication_denies(): void
    {
        $f = $this->fixture();
        DB::table('members')->where('id', $f['member'])->update(['medical_record_number' => 'CHANGED-SUBJECT']);
        try {
            MemberResultFixtures::publish($f);
            $this->fail('Published mismatched original subject');
        } catch (\DomainException) {
            $this->assertSame(0, DB::table('member_result_publications')->count());
        }
    }

    public function test_mandatory_password_replacement_returns_to_result_with_fresh_credential(): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        DB::table('users')->where('id', $f['memberUser']->id)->update(['must_change_password' => true]);
        $this->post('/logout');
        $this->get($p['url'])->assertRedirect('/login');
        $this->post('/login', ['identifier' => $f['memberUser']->email, 'password' => 'password'])->assertRedirect('/password/change-required');
        $this->post('/password/change-required', ['current_password' => 'password', 'password' => 'SyntheticReplacement123!',
            'password_confirmation' => 'SyntheticReplacement123!'])->assertRedirect($p['url']);
        $this->get($p['url'])->assertOk()->assertSee('Synthetic final findings.');
    }

    public function test_idempotency_preserves_expiry_and_history_and_revoke_is_terminal(): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $this->assertSame($p, MemberResultFixtures::publish($f));
        $this->assertSame(1, DB::table('member_result_publications')->count());
        $this->assertSame(1, DB::table('member_result_history')->count());
        app(MemberResultAccess::class)->manage($f['i']['booking'], 'revoke', ['revision' => 1, 'key' => 'revoke']);
        try {
            MemberResultFixtures::publish($f);
            $this->fail('Stale retry resurrected grant');
        } catch (\DomainException) {
        }
        $next = MemberResultFixtures::publish($f, 2, 'new-publication');
        $this->assertNotSame($p['id'], $next['id']);
        $this->assertSame('revoked', DB::table('member_result_publications')->where('id', $p['id'])->value('state'));
        $this->assertSame(3, DB::table('member_result_history')->count());
        $this->member($f);
        $this->get($p['url'])->assertForbidden();
        $this->get($next['url'])->assertOk();
    }

    public function test_missing_publication_capability_denies_before_doctor_projection(): void
    {
        $f = $this->fixture();
        DB::table('authorization_permission_assignments')->where('permission', 'member.result-publication.publish')->delete();
        $clinicalQueries = [];
        DB::listen(function ($query) use (&$clinicalQueries) {
            if (preg_match('/doctor_(workspaces|report_versions|finalizations)/', $query->sql)) {
                $clinicalQueries[] = $query->sql;
            }
        });
        $this->get('/admin/member-results/'.$f['i']['booking'])->assertForbidden()->assertDontSee('Synthetic final');
        $this->assertSame([], $clinicalQueries, 'Unauthorized previews must not query clinical projections.');
        $this->assertSame(0, DB::table('member_result_history')->count());
    }

    public function test_audit_failure_rolls_back_mutation_and_withholds_prepared_body(): void
    {
        $f = $this->fixture();
        $audit = app(AuditStore::class);
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('SYNTHETIC-AUDIT-FAILURE');
            }
        });
        try {
            MemberResultFixtures::publish($f);
            $this->fail('Publication succeeded without audit');
        } catch (\Throwable) {
            $this->assertSame(0, DB::table('member_result_publications')->count());
            $this->assertSame(0, DB::table('member_result_history')->count());
        }
        app()->instance(AuditStore::class, $audit);
        $p = MemberResultFixtures::publish($f);
        $this->member($f);
        $this->get($p['url'])->assertOk();
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('SYNTHETIC-AUDIT-FAILURE');
            }
        });
        $this->get($p['url'])->assertStatus(503)->assertDontSee('Synthetic final findings.');
    }

    public function test_prepared_response_rechecks_committed_revocation_including_head(): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        $this->member($f);
        $this->get($p['url'])->assertOk();
        $request = Request::create($p['url']);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $f['memberUser']);
        $prepared = app(ResultController::class)->entry($request, $p['id'], app(ResultPublication::class));
        $this->actingAs($f['admin']);
        app(MemberResultAccess::class)->manage($f['i']['booking'], 'revoke', ['revision' => 1, 'key' => 'revoke']);
        $this->actingAs($f['memberUser']);
        ob_start();
        $prepared->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $prepared->getStatusCode());
        $this->assertStringNotContainsString('Synthetic final findings.', $body);
        $this->head($p['url'])->assertForbidden()->assertContent('');
    }
}
