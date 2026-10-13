<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\ImageGateway\Application\Contracts\AdminStudyWorklist;
use App\Modules\Member\Application\Contracts\AdminStudyReferences;
use App\Modules\Operator\Application\Contracts\AdminStudyIntakes;
use App\Providers\Filament\Pages\AdminCentralizedStudyWorklist;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Authorization\AuthorizationClaimResolver;
use App\Shared\Authorization\DatabaseAuthorizationClaimResolver;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AdminOperationsFixtures;
use Tests\Support\StudyWorklistFixtures;
use Tests\TestCase;

final class AdminCentralizedStudyWorklistTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Filament::setCurrentPanel('admin');
    }

    private function read(array $f, int $page = 1): array
    {
        return app(AdminStudyWorklist::class)->read($f['siteId'], $f['date'], $f['scheduleId'], $page);
    }

    private function worklistComponent(array $f): mixed
    {
        return Livewire::withQueryParams(['facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']])->test(AdminCentralizedStudyWorklist::class);
    }

    public function test_http_link_livewire_pagination_refresh_and_minimized_snapshot(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $ids = [];
        for ($i = 1; $i <= 27; $i++) {
            $ids[] = StudyWorklistFixtures::intake($f, sprintf('T-%03d', $i), $i === 1 ? 'awaiting_ai' : 'waiting');
            DB::table('operator_queue_admissions')->where('id', $ids[$i - 1]['admission'])->update(['created_at' => sprintf('2030-01-01 00:00:%02d', $i)]);
        }
        $url = '/admin/study-worklist?'.http_build_query(['facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']]);
        $this->get('/admin?'.http_build_query(['facility' => $f['siteId'], 'date' => $f['date']]))->assertOk()->assertSee('Daftar Studi Terpusat');
        $response = $this->get($url)->assertOk()->assertSee('T-001')->assertSee('T-025')->assertDontSee('T-026')->assertSee('bukan persetujuan radiolog');
        foreach ($ids as $id) {
            foreach ($id as $private) {
                $response->assertDontSee($private);
            }
        }
        $component = $this->worklistComponent($f)->assertSee('T-025')->assertDontSee('T-026');
        $component->call('nextPage')->assertSee('T-026')->assertDontSee('T-025')->call('previousPage')->assertSee('T-001');
        StudyWorklistFixtures::capture($f, $ids[0], 'retrying');
        $component->call('refreshWorklist')->assertSee('Menunggu percobaan ulang');
        $this->assertStringNotContainsString($ids[0]['admission'], json_encode($component->snapshot));
        $this->assertSame(25, count($this->read($f)['values']['rows']));
        $this->assertFalse($this->read($f, 2)['values']['more']);
        $this->assertSame($this->read($f)['values']['rows'], $this->read($f)['values']['rows']);
        $component->set('date', '2040-02-02')->assertSet('session', '')->assertSet('page', 1)->assertDontSee('T-001');
    }

    public static function processingStates(): array
    {
        return array_map(fn ($state) => [$state], ['pending', 'processing', 'retrying', 'completed', 'failed']);
    }

    #[DataProvider('processingStates')]
    public function test_recorded_legacy_processing_states(string $state): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f);
        StudyWorklistFixtures::capture($f, $intake, $state);
        $row = $this->read($f)['values']['rows'][0];
        $this->assertSame('accepted', $row['receipt']);
        $this->assertSame($state, $row['processing']);
        $this->assertSame('unavailable', $row['dicom']);
        $this->assertSame('not_applicable', $row['ai']);
    }

    public static function aiStates(): array
    {
        return array_map(fn ($state) => [$state], ['queued', 'processing', 'retryable_failure', 'terminal_failure', 'report_ready']);
    }

    #[DataProvider('aiStates')]
    public function test_direct_dicom_and_all_ai_states_without_report_read(string $state): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f, '<script>sentinel()</script>', 'awaiting_ai');
        $capture = StudyWorklistFixtures::capture($f, $intake, 'completed');
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['radiograph_status' => 'pending', 'gain_status' => 'pending', 'dicom_status' => 'success']);
        $study = StudyWorklistFixtures::study($capture);
        StudyWorklistFixtures::job($intake, $capture, $study, $state);
        $logs = [];
        $this->app['events']->listen(MessageLogged::class, function ($event) use (&$logs): void {
            $logs[] = [$event->message, $event->context];
        });
        DB::enableQueryLog();
        $result = $this->read($f);
        $this->assertSame('available', $result['state']);
        $row = $result['values']['rows'][0];
        $this->assertSame('completed', $row['processing']);
        $this->assertSame('available', $row['dicom']);
        $this->assertSame($state, $row['ai']);
        $html = $this->worklistComponent($f)->html();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>sentinel()', $html);
        $payload = json_encode($result).$html.json_encode(DB::table('audit_events')->get()).json_encode($logs);
        foreach ([$capture, $study, $intake['booking'], $intake['admission'], 'Private Synthetic Identity', 'PRIVATE_KEY_SENTINEL', 'PRIVATE_FINDINGS_SENTINEL', 'PRIVATE_FILENAME_SENTINEL', 'PRIVATE_ERROR_SENTINEL', 'https://private.example.test'] as $private) {
            $this->assertStringNotContainsString($private, $payload);
        }
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(from|join)\s+["`]?(image_gateway_ai_reports|image_gateway_capture_objects|members)["`]?/i', $query['query']);
        }
    }

    public function test_no_capture_incomplete_capture_and_absent_ai_are_distinct(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f);
        $this->assertSame('not_received', $this->read($f)['values']['rows'][0]['receipt']);
        $capture = StudyWorklistFixtures::capture($f, $intake, accepted: false);
        $this->assertSame('incomplete', $this->read($f)['values']['rows'][0]['receipt']);
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['status' => 'accepted', 'accepted_at' => now(), 'processing_status' => 'completed']);
        StudyWorklistFixtures::study($capture);
        $this->assertSame('not_queued', $this->read($f)['values']['rows'][0]['ai']);
    }

    public static function missingCapabilities(): array
    {
        return array_map(fn ($permission) => [$permission], ['member.admin.access', 'member.schedule.read', 'member.booking.read', 'operator.audit.read']);
    }

    #[DataProvider('missingCapabilities')]
    public function test_missing_capability_denies_all_owner_bypasses_without_protected_queries(string $missing): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', $missing)->update(['active' => false]);
        DB::enableQueryLog();
        $this->assertSame('denied', $this->read($f)['state']);
        $this->assertSame('denied', app(AdminStudyIntakes::class)->read($f['siteId'], $f['date'], $f['scheduleId'], 1)['state']);
        $this->assertSame('denied', app(AdminStudyReferences::class)->read($f['siteId'], $f['date'], $f['scheduleId'])['state']);
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(from|join)\s+["`]?(bookings|operator_queue_admissions|operator_paper_tickets|image_gateway_[a-z_]+)["`]?/i', $query['query']);
        }
    }

    public function test_http_guest_and_role_denial_and_livewire_revocation(): void
    {
        $this->get('/admin/study-worklist')->assertRedirect('/admin/login');
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $component = $this->worklistComponent($f)->assertSee('T-001');
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.booking.read')->update(['active' => false]);
        $component->call('refreshWorklist')->assertSee('Tidak diizinkan')->assertDontSee('T-001');
        $this->get('/admin/study-worklist?'.http_build_query(['facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']]))->assertOk()->assertDontSee('T-001');
        DB::table('authorization_role_assignments')->where('user_id', $f['admin']->id)->update(['active' => false]);
        $this->get('/admin/study-worklist')->assertForbidden();
    }

    public static function corruptAssociations(): array
    {
        return array_map(fn ($case) => [$case], ['booking', 'ticket', 'admission', 'admission_state', 'capture', 'capture_status', 'processing', 'accepted_at', 'study', 'ai_status', 'ai_member', 'ai_capture', 'mapping']);
    }

    #[DataProvider('corruptAssociations')]
    public function test_corrupt_owned_associations_and_unknown_states_withhold(string $case): void
    {
        $f = AdminOperationsFixtures::create();
        $other = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f);
        $capture = StudyWorklistFixtures::capture($f, $intake, 'completed');
        $study = StudyWorklistFixtures::study($capture);
        $job = StudyWorklistFixtures::job($intake, $capture, $study);
        $otherIntake = StudyWorklistFixtures::intake($other);
        $otherCapture = StudyWorklistFixtures::capture($other, $otherIntake);
        match ($case) {
            'booking' => DB::table('bookings')->where('id', $intake['booking'])->update(['examination_site_id_snapshot' => $other['siteId']]),
            'ticket' => DB::table('operator_paper_tickets')->where('id', $intake['ticketId'])->update(['member_schedule_id' => $other['scheduleId']]),
            'admission' => DB::table('operator_queue_admissions')->where('id', $intake['admission'])->update(['member_schedule_id' => $other['scheduleId']]),
            'admission_state' => DB::table('operator_queue_admissions')->where('id', $intake['admission'])->update(['state' => 'unknown']),
            'capture' => DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['operator_site_id' => $other['operatorSiteId']]),
            'capture_status' => DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['status' => 'unknown']),
            'processing' => DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['processing_status' => 'unknown']),
            'accepted_at' => DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['accepted_at' => null]),
            'study' => DB::table('image_gateway_studies')->where('id', $study)->update(['format' => 'unknown']),
            'ai_status' => DB::table('image_gateway_ai_jobs')->where('id', $job)->update(['status' => 'unknown']),
            'ai_member' => DB::table('image_gateway_ai_jobs')->where('id', $job)->update(['member_id' => DB::table('bookings')->where('id', $otherIntake['booking'])->value('member_id')]),
            'ai_capture' => DB::table('image_gateway_ai_jobs')->where('id', $job)->update(['capture_set_id' => $otherCapture]),
            'mapping' => DB::table('operator_sites')->where('id', $f['operatorSiteId'])->update(['source_version' => 'stale']),
        };
        $result = $this->read($f);
        $this->assertSame('unavailable', $result['state']);
        $this->assertNull($result['values']);
        $this->worklistComponent($f)->assertSee('Data tidak tersedia')->assertDontSee('T-001');
    }

    public function test_forged_scope_invalid_page_and_inactive_history(): void
    {
        $f = AdminOperationsFixtures::create();
        $other = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $this->assertSame('unavailable', $this->read($f, 0)['state']);
        $this->assertSame('unavailable', $this->read($f, PHP_INT_MAX)['state']);
        foreach ([['date' => '2040-02-30'], ['siteId' => $other['siteId']], ['scheduleId' => $other['scheduleId']]] as $changes) {
            $this->assertSame('unavailable', $this->read([...$f, ...$changes])['state']);
        }
        $this->worklistComponent($f)->set('session', $other['scheduleId'])->assertDontSee('T-001')->assertSee('Data tidak tersedia');
        DB::table('operator_sites')->where('id', $f['operatorSiteId'])->update(['active' => false]);
        DB::table('examination_site_refs')->where('id', $f['siteId'])->update(['active' => false]);
        $this->assertSame('available', $this->read($f)['state']);
    }

    public function test_audit_append_failure_and_nested_transaction_withhold(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        DB::beginTransaction();
        $this->assertSame('unavailable', $this->read($f)['state']);
        DB::rollBack();
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('PRIVATE_FINDINGS_SENTINEL');
            }
        });
        $this->assertSame('unavailable', $this->read($f)['state']);
        $this->worklistComponent($f)->assertDontSee('T-001')->assertSee('Data tidak tersedia');
    }

    public function test_final_audit_commit_failure_withholds_and_rolls_back_success(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $final = false;
        $this->app->instance(AuditStore::class, new class($final) implements AuditStore
        {
            public function __construct(private bool &$final) {}

            public function append(AuditEvent $event): void
            {
                app(DatabaseAuditStore::class)->append($event);
                if ($event->action === 'image-gateway.admin-study-worklist.read' && $event->outcome === 'success') {
                    $this->final = true;
                }
            }
        });
        $this->app['events']->listen(TransactionCommitting::class, function () use (&$final): void {
            if ($final) {
                $final = false;
                throw new \RuntimeException('synthetic_commit_failure');
            }
        });
        $this->assertSame('unavailable', $this->read($f)['state']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'image-gateway.admin-study-worklist.read', 'outcome' => 'success']);
    }

    public function test_success_audit_precedes_return_and_commit_revocation_withholds(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $this->assertSame('available', $this->read($f)['state']);
        $this->assertDatabaseHas('audit_events', ['action' => 'image-gateway.admin-study-worklist.read', 'outcome' => 'success', 'actor_id' => $f['admin']->id]);
        $final = false;
        $this->app->instance(AuditStore::class, new class($final) implements AuditStore
        {
            public function __construct(private bool &$final) {}

            public function append(AuditEvent $event): void
            {
                app(DatabaseAuditStore::class)->append($event);
                if ($event->action === 'image-gateway.admin-study-worklist.read' && $event->outcome === 'success') {
                    $this->final = true;
                }
            }
        });
        $this->app['events']->listen(TransactionCommitted::class, function () use ($f, &$final): void {
            if ($final) {
                DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'operator.audit.read')->update(['active' => false]);
            }
        });
        $this->assertSame('denied', $this->read($f)['state']);
    }

    public function test_read_has_bounded_queries_and_audit_only_database_effects(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        Http::preventStrayRequests();
        Bus::fake();
        Queue::fake();
        $queries = [];
        $this->app['events']->listen(QueryExecuted::class, function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->read($f);
        $one = count($queries);
        for ($i = 2; $i <= 25; $i++) {
            StudyWorklistFixtures::intake($f, 'T-'.$i);
        }
        $queries = [];
        $this->read($f);
        $this->assertLessThanOrEqual($one + 2, count($queries));
        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
        foreach ($queries as $sql) {
            if (preg_match('/^\s*(insert|update|delete)/i', $sql)) {
                $this->assertStringContainsString('audit_events', $sql);
            }
        }
    }

    public function test_real_http_livewire_json_refresh_forgery_and_revocation(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f);
        $response = $this->get('/admin/study-worklist?'.http_build_query(['facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']]))->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $candidate) {
            $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5);
            if ((json_decode($candidate, true)['memo']['name'] ?? '') === AdminCentralizedStudyWorklist::class) {
                $snapshot = $candidate;
            }
        }
        $this->assertNotNull($snapshot);
        $this->assertStringNotContainsString($intake['admission'], $snapshot);
        $this->assertStringNotContainsString($intake['booking'], $snapshot);
        $request = fn ($updates, $value) => $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $value, 'updates' => $updates, 'calls' => [['path' => '', 'method' => 'refreshWorklist', 'params' => []]]]]], ['X-Livewire' => 'true']);
        $refresh = $request([], $snapshot)->assertOk();
        $this->assertStringContainsString('T-001', $refresh->json('components.0.effects.html'));
        $request(['session' => 'forged-session'], $refresh->json('components.0.snapshot'))->assertOk()->assertDontSee('T-001');
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'operator.audit.read')->update(['active' => false]);
        DB::enableQueryLog();
        $denied = $request([], $snapshot)->assertOk();
        $this->assertStringNotContainsString('T-001', $denied->json('components.0.effects.html'));
        $this->assertStringContainsString('Tidak diizinkan', $denied->json('components.0.effects.html'));
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(from|join)\s+["`]?(operator_queue_admissions|image_gateway_[a-z_]+)["`]?/i', $query['query']);
        }
    }

    public function test_source_outage_withholds_without_disclosing_exception(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $this->app['events']->listen(QueryExecuted::class, function ($query): void {
            if (str_contains($query->sql, 'image_gateway_capture_sets')) {
                throw new \RuntimeException('PRIVATE_FINDINGS_SENTINEL');
            }
        });
        $result = $this->read($f);
        $this->assertSame('unavailable', $result['state']);
        $this->assertNull($result['values']);
        $this->assertStringNotContainsString('PRIVATE_FINDINGS_SENTINEL', json_encode(DB::table('audit_events')->get()));
    }

    public function test_revocation_during_gateway_read_withholds(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $revoked = false;
        $this->app->instance(AuthorizationClaimResolver::class, new class($revoked) implements AuthorizationClaimResolver
        {
            public function __construct(private bool &$revoked) {}

            public function roles(User|string $user): array
            {
                return (new DatabaseAuthorizationClaimResolver)->roles($user);
            }

            public function permissions(User|string $user): array
            {
                $permissions = (new DatabaseAuthorizationClaimResolver)->permissions($user);

                return $this->revoked ? array_values(array_diff($permissions, ['member.booking.read'])) : $permissions;
            }
        });
        $this->app['events']->listen(QueryExecuted::class, function ($query) use (&$revoked): void {
            if (str_contains($query->sql, 'image_gateway_capture_sets')) {
                $revoked = true;
            }
        });
        $this->assertSame('denied', $this->read($f)['state']);
    }

    public function test_session_directory_reaches_later_sessions_and_resets_intake_scope(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        for ($i = 1; $i <= 26; $i++) {
            $row = (array) DB::table('shift_schedules')->where('id', $f['scheduleId'])->first();
            DB::table('shift_schedules')->insert([...$row, 'id' => (string) Str::uuid(), 'display_reference' => sprintf('JAD-LATER%03d', $i), 'starts_at' => sprintf('2040-02-01 04:%02d:00', $i), 'ends_at' => '2040-02-01 06:00:00']);
        }
        $late = DB::table('shift_schedules')->where('display_reference', 'JAD-LATER026')->value('id');
        $component = $this->worklistComponent($f)->call('nextSessions')->assertSet('session', '')->assertSet('page', 1)->assertSee('JAD-LATER026');
        $component->set('session', $late)->assertSee('Belum ada intake X-ray pada sesi ini.');
        $component->call('previousSessions')->assertSet('session', '')->assertSet('page', 1);
    }

    public static function localDateCases(): array
    {
        return [
            ['Pacific/Kiritimati', '2040-02-02', '2040-02-01 10:30:00', '2040-02-02 03:00:00'],
            ['America/New_York', '2030-03-10', '2030-03-10 05:00:00', '2030-03-11 04:00:00'],
            ['America/New_York', '2030-11-03', '2030-11-03 04:00:00', '2030-11-04 05:00:00'],
        ];
    }

    #[DataProvider('localDateCases')]
    public function test_local_start_date_midnight_overnight_and_dst(string $zone, string $date, string $start, string $end): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        DB::table('operator_sites')->where('id', $f['operatorSiteId'])->update(['timezone' => $zone]);
        DB::table('examination_site_refs')->where('id', $f['siteId'])->update(['timezone' => $zone]);
        DB::table('shift_schedules')->where('id', $f['scheduleId'])->update(['starts_at' => $start, 'ends_at' => $end]);
        $f['date'] = $date;
        $result = $this->read($f);
        $this->assertSame('available', $result['state']);
        $this->assertSame($zone, $result['values']['facility']['timezone']);
        $this->assertSame('T-001', $result['values']['rows'][0]['ticket_reference']);
        $f['date'] = (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
        $this->assertSame('unavailable', $this->read($f)['state']);
        DB::table('examination_site_refs')->where('id', $f['siteId'])->update(['timezone' => 'Invalid/Timezone']);
        $this->assertSame('unavailable', $this->read($f)['state']);
    }

    public static function ineligibleActors(): array
    {
        return [['member'], ['doctor'], ['operator'], ['panel_only'], ['manage_only'], ['inactive'], ['login_disabled'], ['password_change']];
    }

    #[DataProvider('ineligibleActors')]
    public function test_ineligible_actor_withholds_rows(string $case): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        if (in_array($case, ['member', 'doctor', 'operator'], true)) {
            DB::table('authorization_role_assignments')->where('user_id', $f['admin']->id)->update(['role' => $case]);
        } elseif (in_array($case, ['panel_only', 'manage_only'], true)) {
            DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', '!=', 'member.admin.access')->update(['active' => false]);
        } else {
            DB::table('users')->where('id', $f['admin']->id)->update(match ($case) {
                'inactive' => ['account_status' => 'inactive'],
                'login_disabled' => ['login_enabled' => false],
                'password_change' => ['must_change_password' => true],
            });
        }
        $this->assertSame('denied', $this->read($f)['state']);
        if ($case === 'panel_only' || $case === 'manage_only') {
            $this->get('/admin/study-worklist')->assertForbidden();
        }
    }

    public function test_orphaned_ticket_and_duplicate_capture_associations_withhold(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $intake = StudyWorklistFixtures::intake($f);
        Schema::disableForeignKeyConstraints();
        try {
            DB::table('operator_queue_admissions')->where('id', $intake['admission'])->update(['operator_paper_ticket_id' => 'synthetic-orphan']);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $this->assertSame('unavailable', $this->read($f)['state']);
        DB::table('operator_queue_admissions')->where('id', $intake['admission'])->update(['operator_paper_ticket_id' => $intake['ticketId']]);
        $other = StudyWorklistFixtures::intake($f, 'T-002');
        StudyWorklistFixtures::capture($f, $intake);
        $duplicate = StudyWorklistFixtures::capture($f, $other);
        DB::table('image_gateway_capture_sets')->where('id', $duplicate)->update(['booking_id' => $intake['booking']]);
        $this->assertSame('unavailable', $this->read($f)['state']);
    }

    public function test_final_audit_append_failure_withholds_after_owner_validation(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        StudyWorklistFixtures::intake($f);
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                if ($event->action === 'image-gateway.admin-study-worklist.read' && $event->outcome === 'success') {
                    throw new \RuntimeException('PRIVATE_FINDINGS_SENTINEL');
                }
                app(DatabaseAuditStore::class)->append($event);
            }
        });
        $this->assertSame('unavailable', $this->read($f)['state']);
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.admin-study-intakes.read', 'outcome' => 'success']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'image-gateway.admin-study-worklist.read', 'outcome' => 'success']);
    }
}
