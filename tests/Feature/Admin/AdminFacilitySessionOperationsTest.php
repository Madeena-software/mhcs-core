<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Member\Application\Services\AdminBookingSummaryRead;
use App\Modules\Member\Application\Services\AdminSessionBoardRead;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Domain\Enums\BookingStatus;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Member\Filament\Resources\ShiftSchedules\Pages\ListShiftSchedules;
use App\Modules\Operator\Application\Services\AdminSessionArrivalRead;
use App\Providers\Filament\Pages\AdminFacilitySessionOperations;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Authorization\AdminOperationalRead;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\AdminOperationsFixtures;
use Tests\TestCase;

final class AdminFacilitySessionOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_admin_landing_is_operational_board(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $this->get('/admin')->assertOk()->assertSee('Operasional Fasilitas');
    }

    public function test_persisted_sessions_zero_counts_and_refresh_are_audited(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        Livewire::test(AdminFacilitySessionOperations::class)->set('facility', $f['siteId'])->set('date', $f['date'])->call('refreshBoard')->assertSee('JAD-BOARD001')->assertSee('Kapasitas Tercadangkan')->assertSee('Radiografi Sintetis');
        $this->assertDatabaseHas('audit_events', ['action' => 'member.admin-session-board.read', 'outcome' => 'success']);
    }

    public function test_revoked_schedule_access_clears_projection(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        $component = Livewire::test(AdminFacilitySessionOperations::class)->set('facility', $f['siteId'])->set('date', $f['date'])->assertSee('JAD-BOARD001');
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.schedule.read')->update(['active' => false]);
        $component->call('refreshBoard')->assertDontSee('JAD-BOARD001')->assertDontSee('Radiografi Sintetis');
    }

    public function test_schedule_only_does_not_query_bookings_or_arrivals(): void
    {
        $f = AdminOperationsFixtures::create(['member.schedule.read']);
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        DB::enableQueryLog();
        Livewire::test(AdminFacilitySessionOperations::class)->set('facility', $f['siteId'])->set('date', $f['date'])->assertSee('JAD-BOARD001')->assertSee('Tidak diizinkan');
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`]?bookings["`]?/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`]?operator_arrivals["`]?/i', $query['query']);
        }
    }

    public function test_x1_schedule_only_suppresses_protected_count_queries(): void
    {
        $f = AdminOperationsFixtures::create(['member.schedule.read']);
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        DB::enableQueryLog();
        Livewire::test(ListShiftSchedules::class)->assertSee('Kapasitas Tercadangkan')->assertSee('Tidak diizinkan');
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`]?bookings["`]?/i', $query['query']);
        }
    }

    public function test_every_booking_status_and_over_quota_are_exact_without_identity(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        foreach (BookingStatus::cases() as $status) {
            AdminOperationsFixtures::booking($f, $status->value);
        }
        for ($i = 0; $i < 5; $i++) {
            AdminOperationsFixtures::booking($f, 'confirmed');
        }
        $result = app(AdminBookingSummaryRead::class)->read([$f['scheduleId'] => 5], $f['siteId']);
        $this->assertSame('available', $result['state']);
        $v = $result['values'][$f['scheduleId']];
        $this->assertSame(14, $v['total']);
        $this->assertSame(6, $v['by_status']['confirmed']);
        $reserved = count(Mvp03BookingService::capacityStatuses()) + 5;
        $this->assertSame($reserved, $v['reserved']);
        $this->assertSame(max(0, 5 - $reserved), $v['remaining']);
        $this->assertStringNotContainsString('Private Synthetic', json_encode($result));
    }

    public function test_arrival_only_access_counts_progressed_distinct_bookings_without_booking_totals(): void
    {
        $f = AdminOperationsFixtures::create(['member.schedule.read', 'operator.audit.read']);
        $this->actingAs($f['admin']);
        $booking = AdminOperationsFixtures::booking($f, 'completed');
        AdminOperationsFixtures::arrival($f, $booking);
        AdminOperationsFixtures::arrival($f, $booking);
        $r = app(AdminSessionArrivalRead::class)->read($f['siteId'], $f['operatorSiteId'], [$f['scheduleId']], ['request_kind' => 'refresh', 'operational_date' => $f['date'], 'timezone' => 'Asia/Jakarta']);
        $this->assertSame('available', $r['state']);
        $this->assertSame(1, $r['values'][$f['scheduleId']]);
        $this->assertStringNotContainsString($booking, json_encode($r));
    }

    public function test_orphan_arrivals_and_unknown_booking_states_are_unavailable(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        AdminOperationsFixtures::arrival($f, (string) Str::uuid());
        $r = app(AdminSessionArrivalRead::class)->read($f['siteId'], $f['operatorSiteId'], [$f['scheduleId']], ['request_kind' => 'refresh']);
        $this->assertSame('unavailable', $r['state']);
        $this->assertNull($r['values']);
        AdminOperationsFixtures::booking($f, 'unknown');
        $r = app(AdminBookingSummaryRead::class)->read([$f['scheduleId'] => 5], $f['siteId']);
        $this->assertSame('unavailable', $r['state']);
        $this->assertNull($r['values']);
    }

    public function test_invalid_mapping_and_malformed_date_withhold_sessions(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $read = app(AdminSessionBoardRead::class);
        foreach (['2040-02-30', 'not-a-date'] as $date) {
            $r = $read->read($f['siteId'], $date, 1, 'filter');
            $this->assertSame('unavailable', $r['state']);
            $this->assertNull($r['values']);
        }
        foreach (['timezone' => 'Bad/Zone', 'source_version' => '2', 'organization_id' => 'wrong'] as $field => $value) {
            $old = DB::table('operator_sites')->where('id', $f['operatorSiteId'])->value($field);
            DB::table('operator_sites')->where('id', $f['operatorSiteId'])->update([$field => $value]);
            $this->assertSame('unavailable', $read->read($f['siteId'], $f['date'], 1, 'filter')['state']);
            DB::table('operator_sites')->where('id', $f['operatorSiteId'])->update([$field => $old]);
        }
    }

    public function test_local_start_membership_overnight_and_dst_midnights(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $read = app(AdminSessionBoardRead::class);
        foreach (['operator_sites', 'examination_site_refs'] as $table) {
            DB::table($table)->where('id', $table === 'operator_sites' ? $f['operatorSiteId'] : $f['siteId'])->update(['timezone' => 'America/New_York']);
        }
        foreach (['2040-03-11' => 23, '2040-11-04' => 25] as $date => $hours) {
            $r = $read->read($f['siteId'], $date, 1, 'filter');
            $this->assertSame('available', $r['state']);
            $range = $r['values']['range'];
            $this->assertSame($hours, (strtotime($range[1].' UTC') - strtotime($range[0].' UTC')) / 3600);
        }
        DB::table('shift_schedules')->where('id', $f['scheduleId'])->update(['starts_at' => '2040-02-02 04:30:00', 'ends_at' => '2040-02-02 06:00:00']);
        $this->assertCount(1, $read->read($f['siteId'], '2040-02-01', 1, 'filter')['values']['sessions']);
        $this->assertCount(0, $read->read($f['siteId'], '2040-02-02', 1, 'filter')['values']['sessions']);
    }

    public function test_required_audit_failure_and_outer_transaction_withhold_values(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $read = app(AdminBookingSummaryRead::class);
        DB::beginTransaction();
        try {
            $r = $read->read([$f['scheduleId'] => 5], $f['siteId']);
            $this->assertSame('unavailable', $r['state']);
            $this->assertNull($r['values']);
        } finally {
            DB::rollBack();
        }
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \JsonException('synthetic failure');
            }
        });
        $r = $read->read([$f['scheduleId'] => 5], $f['siteId']);
        $this->assertSame('unavailable', $r['state']);
        $this->assertNull($r['values']);
        $this->assertSame(0, DB::table('audit_events')->where('outcome', 'success')->count());
    }

    public function test_board_audit_has_validated_scope_and_minimized_metadata(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        app(AdminSessionBoardRead::class)->read($f['siteId'], $f['date'], 1, 'filter');
        $event = DB::table('audit_events')->where('action', 'member.admin-session-board.read')->first();
        $this->assertSame($f['siteId'], $event->target_id);
        $metadata = json_decode($event->metadata, true);
        $this->assertEqualsCanonicalizing(['request_kind', 'operational_date', 'timezone', 'section_states'], array_keys($metadata));
        $this->assertSame($f['date'], $metadata['operational_date']);
        $this->assertSame('Asia/Jakarta', $metadata['timezone']);
    }

    public function test_over_quota_board_warns_and_never_reports_negative_remaining(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        for ($i = 0; $i < 6; $i++) {
            AdminOperationsFixtures::booking($f);
        }
        Livewire::test(AdminFacilitySessionOperations::class)->set('facility', $f['siteId'])->set('date', $f['date'])->assertSee('Melebihi kuota')->assertSee('Sisa Kapasitas: 0');
    }

    public function test_actual_livewire_update_denies_revoked_grant_and_new_count_disclosure(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $response = $this->get('/admin?facility='.$f['siteId'].'&date='.$f['date'])->assertOk();
        preg_match_all('/wire:snapshot="([^"\n]+)"/', $response->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $candidate = html_entity_decode($encoded, ENT_QUOTES);
            $decoded = json_decode($candidate, true);
            if (($decoded['memo']['name'] ?? '') === AdminFacilitySessionOperations::class) {
                $snapshot = $candidate;
            }
        }
        $this->assertNotNull($snapshot);
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.booking.read')->update(['active' => false]);
        DB::enableQueryLog();
        $update = $this->postJson(Livewire::getUpdateUri(), ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'refreshBoard', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
        $html = $update->json('components.0.effects.html');
        $this->assertIsString($html);
        $this->assertStringNotContainsString('Total Booking:', $html);
        $this->assertStringContainsString('Tidak diizinkan', $html);
        foreach (DB::getQueryLog() as $q) {
            if (str_contains($q['query'], 'bookings')) {
                $this->assertStringNotContainsString('COUNT(*)', $q['query']);
            }
        }
    }

    public function test_real_commit_failure_rolls_back_success_audit_and_withholds_values(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $dispatcher = DB::connection()->getEventDispatcher();
        $dispatcher->listen(TransactionCommitting::class, fn () => throw new \PDOException('synthetic commit failure'));
        try {
            $r = app(AdminBookingSummaryRead::class)->read([$f['scheduleId'] => 5], $f['siteId']);
        } finally {
            $dispatcher->forget(TransactionCommitting::class);
        }
        $this->assertSame('unavailable', $r['state']);
        $this->assertNull($r['values']);
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertSame(0, DB::table('audit_events')->where('outcome', 'success')->count());
    }

    public function test_x1_revocation_clears_counts_without_querying_booking_table(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        AdminOperationsFixtures::booking($f);
        $component = Livewire::test(ListShiftSchedules::class);
        $this->assertDatabaseHas('audit_events', ['action' => 'member.admin-schedule-capacity.read', 'outcome' => 'success']);
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.booking.read')->update(['active' => false]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('$refresh')->assertSee('Tidak diizinkan');
        foreach (DB::getQueryLog() as $q) {
            $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`]?bookings["`]?/i', $q['query']);
        }
        $this->assertStringNotContainsString('reserved', json_encode($component->snapshot));
    }

    public function test_account_ineligibility_and_claim_failure_deny_without_protected_queries(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $read = app(AdminSessionBoardRead::class);
        foreach (['account_status' => 'inactive', 'login_enabled' => false, 'must_change_password' => true] as $field => $value) {
            $old = $f['admin']->$field;
            DB::table('users')->where('id', $f['admin']->id)->update([$field => $value]);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->assertSame('denied', $read->read($f['siteId'], $f['date'], 1, 'refresh')['state']);
            foreach (DB::getQueryLog() as $q) {
                $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`]?shift_schedules["`]?/i', $q['query']);
            }
            DB::table('users')->where('id', $f['admin']->id)->update([$field => $old]);
        }
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.schedule.read')->update(['permission' => 'member.schedule.*']);
        $this->assertSame('denied', $read->read($f['siteId'], $f['date'], 1, 'refresh')['state']);
    }

    public function test_batch_queries_do_not_scale_per_session_and_reads_do_not_mutate_operations(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $quotas = [$f['scheduleId'] => 5];
        $original = DB::table('shift_schedules')->where('id', $f['scheduleId'])->first();
        for ($i = 0; $i < 24; $i++) {
            $row = (array) $original;
            $row['id'] = (string) Str::uuid();
            $row['display_reference'] = 'JAD-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
            DB::table('shift_schedules')->insert($row);
            $quotas[$row['id']] = 5;
        }
        $before = DB::table('shift_schedules')->get()->toJson();
        $outbox = DB::table('outbox_messages')->count();
        DB::enableQueryLog();
        $r = app(AdminBookingSummaryRead::class)->read($quotas, $f['siteId']);
        $this->assertSame('available', $r['state']);
        $this->assertCount(25, $r['values']);
        $queries = array_filter(DB::getQueryLog(), fn ($q) => preg_match('/\bfrom\s+["`]?bookings["`]?/i', $q['query']) === 1);
        $this->assertCount(1, $queries);
        $this->assertSame($before, DB::table('shift_schedules')->get()->toJson());
        $this->assertSame($outbox, DB::table('outbox_messages')->count());
        $this->assertSame(0, DB::table('bookings')->count());
    }

    public function test_database_audit_lookup_and_real_json_encoding_failure_withhold_values(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $read = app(AdminBookingSummaryRead::class);
        $dispatcher = DB::connection()->getEventDispatcher();
        $dispatcher->listen(QueryExecuted::class, function ($q) {
            if (str_contains($q->sql, 'select exists') && str_contains($q->sql, 'audit_events')) {
                throw new \RuntimeException('synthetic lookup failure');
            }
        });
        try {
            $this->assertSame('unavailable', $read->read([$f['scheduleId'] => 5], $f['siteId'])['state']);
        } finally {
            $dispatcher->forget(QueryExecuted::class);
        }
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                $attributes = get_object_vars($event);
                $attributes['roles'] = [chr(177)];
                (new DatabaseAuditStore)->append(new AuditEvent(...$attributes));
            }
        });
        $r = $read->read([$f['scheduleId'] => 5], $f['siteId']);
        $this->assertSame('unavailable', $r['state']);
        $this->assertNull($r['values']);
        $this->assertSame(0, DB::table('audit_events')->where('outcome', 'success')->count());
    }

    public function test_unverifiable_claims_are_denied_without_source_diagnosis(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $dispatcher = DB::connection()->getEventDispatcher();
        $dispatcher->listen(QueryExecuted::class, function ($q) {
            if (str_contains($q->sql, 'authorization_permission_assignments') && str_starts_with($q->sql, 'select')) {
                throw new \RuntimeException('synthetic claim failure');
            }
        });
        try {
            $r = app(AdminSessionBoardRead::class)->read($f['siteId'], $f['date'], 1, 'refresh');
        } finally {
            $dispatcher->forget(QueryExecuted::class);
        }
        $this->assertSame('denied', $r['state']);
        $this->assertNull($r['values']);
    }

    public function test_cross_site_arrivals_and_unknown_arrival_state_withhold_aggregate(): void
    {
        $f = AdminOperationsFixtures::create();
        $other = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $booking = AdminOperationsFixtures::booking($f);
        AdminOperationsFixtures::arrival($f, $booking, $other['operatorSiteId']);
        $read = app(AdminSessionArrivalRead::class);
        $this->assertSame('unavailable', $read->read($f['siteId'], $f['operatorSiteId'], [$f['scheduleId']], ['request_kind' => 'refresh'])['state']);
        DB::table('operator_arrivals')->where('booking_id', $booking)->update(['operator_site_id' => $f['operatorSiteId'], 'status' => 'unknown']);
        $this->assertSame('unavailable', $read->read($f['siteId'], $f['operatorSiteId'], [$f['scheduleId']], ['request_kind' => 'refresh'])['state']);
    }

    public function test_x1_authorized_zero_differs_from_audit_unavailable(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        Filament::setCurrentPanel('admin');
        $schedule = ShiftSchedule::query()->findOrFail($f['scheduleId']);
        Livewire::test(ListShiftSchedules::class)->assertTableColumnStateSet('confirmed_count', 0, $schedule)->assertDontSee('Data tidak tersedia');
        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('synthetic append failure');
            }
        });
        Livewire::test(ListShiftSchedules::class)->assertSee('Data tidak tersedia')->assertDontSee('Tidak diizinkan');
    }

    public function test_pre_release_revocation_omits_selection_from_rejected_audit(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        $dispatcher = DB::connection()->getEventDispatcher();
        $dispatcher->listen(TransactionCommitted::class, function () use ($f): void {
            DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.schedule.read')->update(['active' => false]);
        });
        try {
            $r = app(AdminOperationalRead::class)->read(fn () => app(MemberAuthorization::class)->scheduleRead(), fn () => [
                'facility' => ['reference' => $f['siteId']],
                'metadata' => ['request_kind' => 'refresh', 'operational_date' => $f['date'], 'timezone' => 'Asia/Jakarta'],
            ], 'member.admin-session-board.read', 'member', 'schedules');
        } finally {
            $dispatcher->forget(TransactionCommitted::class);
        }
        $this->assertSame('denied', $r['state']);
        $this->assertNull($r['values']);
        $event = DB::table('audit_events')->where('action', 'member.admin-session-board.read')->where('outcome', 'rejected')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->target_id);
        $this->assertSame(['request_kind' => 'refresh', 'section_states' => ['schedules' => 'denied']], json_decode($event->metadata, true));
    }

    public function test_initial_selected_facility_defaults_to_local_today_and_preserves_explicit_date(): void
    {
        $f = AdminOperationsFixtures::create();
        $this->actingAs($f['admin']);
        app()->instance(Clock::class, new FrozenClock(new \DateTimeImmutable('2040-01-31T23:30:00+00:00')));
        $this->get('/admin?facility='.$f['siteId'])->assertOk()->assertSee('JAD-BOARD001');
        $this->get('/admin?facility='.$f['siteId'].'&date=2040-01-31')->assertOk()->assertDontSee('JAD-BOARD001');
    }
}
