<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AdminOperationsFixtures;

beforeEach(function (): void {
    $database = storage_path('framework/testing/admin-board-browser-'.getmypid().'.sqlite');
    if (! is_dir(dirname($database))) {
        mkdir(dirname($database), 0755, true);
    }
    touch($database);
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
        'session.driver' => 'file',
        'session.files' => storage_path('framework/testing/admin-board-sessions-'.getmypid()),
    ]);
    if (! is_dir(config('session.files'))) {
        mkdir(config('session.files'), 0700, true);
    }
    DB::purge('sqlite');
    $this->artisan('migrate:fresh', ['--force' => true, '--quiet' => true]);
});

function boardBrowserLogin(array $fixture): mixed
{
    return visit('/admin/login')
        ->fill('[id="form.email"]', $fixture['email'])
        ->fill('[id="form.password"]', 'password')
        ->press('button[type="submit"]')
        ->wait(1)
        ->assertPathIs('/admin');
}

function boardBrowserSelect(mixed $page, array $fixture): mixed
{
    return $page->select('#board-facility', $fixture['siteId'])->wait(1)
        ->fill('#board-date', $fixture['date'])->wait(1)
        ->press('#board-refresh')
        ->wait(1)
        ->assertSee('JAD-BOARD001')
        ->assertSee('Radiografi Sintetis');
}

it('shows persisted operational data and preserves management save-return and registration routes', function (): void {
    $fixture = AdminOperationsFixtures::create();
    $page = boardBrowserSelect(boardBrowserLogin($fixture), $fixture);
    $page->assertSee('Kapasitas Tercadangkan')->assertSee('Asia/Jakarta')->assertNoSmoke();
    $page->keys('#board-facility', 'Tab')->assertScript("document.activeElement.id === 'board-date'");
    $page->keys('#board-refresh', 'Shift+Tab')->assertScript("document.activeElement.id === 'board-date'");
    $page->keys('#board-refresh', 'Enter')->wait(1)->assertSee('JAD-BOARD001');
    expect($page->content())->not->toContain('nik_lookup_digest', 'point_cost_snapshot', 'booking_id');
    expect(DB::table('audit_events')->where('action', 'member.admin-session-board.read')->where('outcome', 'success')->exists())->toBeTrue();
    $beforeBookings = DB::table('bookings')->count();
    $beforeArrivals = DB::table('operator_arrivals')->count();
    $beforeOutbox = DB::table('outbox_messages')->count();
    $page->press('#board-refresh')->wait(1)->assertSee('JAD-BOARD001');
    expect(DB::table('bookings')->count())->toBe($beforeBookings);
    expect(DB::table('operator_arrivals')->count())->toBe($beforeArrivals);
    expect(DB::table('outbox_messages')->count())->toBe($beforeOutbox);

    $page->click('a[href*="/admin/shift-schedules/'.$fixture['scheduleId'].'/edit"]')->wait(1)
        ->fill('[id="form.quota"]', '7')->press('button[wire\\:target="save"]')->wait(1)->assertNoSmoke();
    expect((int) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->value('quota'))->toBe(7);
    $page->navigate('/admin')->wait(1);
    boardBrowserSelect($page, $fixture)->assertSee('7');

    $page->click('nav[aria-label="Pengelolaan"] a[href$="/admin/operator-sites"]')->wait(1)
        ->click('a.fi-ac-link-action[href*="/admin/operator-sites/'.$fixture['operatorSiteId'].'/edit"]')->wait(1)
        ->fill('[id="form.display_name"]', 'Lokasi Sintetis Diubah')->press('button[wire\\:target="save"]')->wait(1)->assertNoSmoke();
    expect(DB::table('examination_site_refs')->where('id', $fixture['siteId'])->value('display_name'))->toBe('Lokasi Sintetis Diubah');
    $page->navigate('/admin')->wait(1);
    boardBrowserSelect($page, $fixture)->assertSee('Lokasi Sintetis Diubah');
    $page->navigate('/admin/members/create')->wait(1)->assertPathIs('/admin/members/create')->assertNoSmoke();
    $page->navigate('/admin/shift-schedules/'.$fixture['scheduleId'])->wait(1)
        ->assertPathIs('/admin/shift-schedules/'.$fixture['scheduleId'])->assertSee('Radiografi Sintetis')->assertNoSmoke();
    $page->click('.fi-user-menu-trigger')->wait(1)->press('form[action$="/admin/logout"] button')->wait(1)
        ->assertPathIs('/admin/login');
    $page->navigate('/admin')->wait(1)->assertPathIs('/admin/login');
});

it('keeps independently denied summaries and stops new disclosure after revocation through real update requests', function (): void {
    $fixture = AdminOperationsFixtures::create(['member.schedule.read']);
    $page = boardBrowserSelect(boardBrowserLogin($fixture), $fixture);
    $page->assertSee('Tidak diizinkan')->assertCount('a[href*="/edit"]', 0)->assertNoSmoke();
    expect(DB::table('audit_events')->where('action', 'member.admin-session-board.read')->exists())->toBeTrue();
    DB::table('authorization_permission_assignments')->where('user_id', $fixture['admin']->id)->where('permission', 'member.schedule.read')->update(['active' => false]);
    $page->press('#board-refresh')->wait(1)->assertDontSee('JAD-BOARD001')->assertDontSee('Radiografi Sintetis');
    expect($page->content())->not->toContain('booking_id', 'nik_lookup_digest');
});

it('uses verified facility-local date boundaries and retains inactive historical visibility', function (): void {
    $fixture = AdminOperationsFixtures::create();
    $siteId = (string) Str::uuid();
    $operatorId = (string) Str::uuid();
    $stableId = 'synthetic-second-'.$siteId;
    $site = (array) DB::table('operator_sites')->where('id', $fixture['operatorSiteId'])->first();
    DB::table('operator_sites')->insert([...$site, 'id' => $operatorId, 'operator_site_id' => $stableId, 'code' => 'SECOND', 'display_name' => 'Lokasi Kedua', 'timezone' => 'Pacific/Kiritimati', 'active' => false]);
    $reference = (array) DB::table('examination_site_refs')->where('id', $fixture['siteId'])->first();
    DB::table('examination_site_refs')->insert([...$reference, 'id' => $siteId, 'operator_site_id' => $stableId, 'code' => 'SECOND', 'display_name' => 'Lokasi Kedua', 'timezone' => 'Pacific/Kiritimati', 'active' => false]);
    $schedule = (array) DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->first();
    DB::table('shift_schedules')->insert([...$schedule, 'id' => (string) Str::uuid(), 'display_reference' => 'JAD-CROSS001', 'examination_site_id' => $siteId, 'starts_at' => '2040-02-01 10:30:00', 'ends_at' => '2040-02-01 11:30:00', 'status' => 'closed']);
    $page = boardBrowserSelect(boardBrowserLogin($fixture), $fixture);
    $page->select('#board-facility', $siteId)->wait(1)->fill('#board-date', '2040-02-01')->wait(1)->press('#board-refresh')->wait(1)
        ->assertDontSee('JAD-CROSS001')->assertDontSee('JAD-BOARD001');
    $page->fill('#board-date', '2040-02-02')->wait(1)->press('#board-refresh')->wait(1)
        ->assertSee('JAD-CROSS001')->assertSee('Pacific/Kiritimati')->assertDontSee('JAD-BOARD001')->assertNoSmoke();
});

it('rejects forged facility values and account deactivation on subsequent HTTP requests', function (): void {
    $fixture = AdminOperationsFixtures::create();
    $page = boardBrowserSelect(boardBrowserLogin($fixture), $fixture);
    $page->script("let element = document.querySelector('#board-facility'); while (!element.hasAttribute('wire:id')) element = element.parentElement; window.Livewire.find(element.getAttribute('wire:id')).set('facility', 'forged-facility');");
    $page->wait(1)->press('#board-refresh')->wait(1)->assertDontSee('JAD-BOARD001')->assertDontSee('Radiografi Sintetis');
    DB::table('users')->where('id', $fixture['admin']->id)->update(['login_enabled' => false]);
    $page->press('#board-refresh')->wait(1)->assertDontSee('JAD-BOARD001')->assertDontSee('Radiografi Sintetis');
});

it('shows exact arrival counts independently while withholding booking counts and private identities', function (): void {
    $fixture = AdminOperationsFixtures::create(['member.schedule.read', 'operator.audit.read']);
    $booking = AdminOperationsFixtures::booking($fixture, 'completed');
    AdminOperationsFixtures::arrival($fixture, $booking);
    AdminOperationsFixtures::arrival($fixture, $booking);
    $page = boardBrowserSelect(boardBrowserLogin($fixture), $fixture);
    $page->assertSee('Total Kedatangan Tercatat: 1')->assertSee('Tidak diizinkan')->assertDontSee('Total Booking:')->assertNoSmoke();
    expect($page->content())->not->toContain($booking, 'Private Synthetic Identity', 'Private Synthetic Site Snapshot');
    $page->press('#board-refresh')->wait(1)->assertSee('Total Kedatangan Tercatat: 1')->assertDontSee('Total Booking:');
    DB::table('operator_arrivals')->where('booking_id', $booking)->update(['status' => 'unknown']);
    $page->press('#board-refresh')->wait(1)->assertSee('Data tidak tersedia')->assertDontSee('Total Kedatangan Tercatat: 1');
});
