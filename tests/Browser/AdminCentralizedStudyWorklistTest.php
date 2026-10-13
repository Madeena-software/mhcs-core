<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\AdminOperationsFixtures;
use Tests\Support\StudyWorklistFixtures;

beforeEach(function (): void {
    $database = storage_path('framework/testing/study-worklist-browser-'.getmypid().'.sqlite');
    if (! is_dir(dirname($database))) {
        mkdir(dirname($database), 0755, true);
    }
    touch($database);
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'session.driver' => 'file', 'session.files' => storage_path('framework/testing/study-worklist-sessions-'.getmypid())]);
    if (! is_dir(config('session.files'))) {
        mkdir(config('session.files'), 0700, true);
    }
    DB::purge('sqlite');
    $this->artisan('migrate:fresh', ['--force' => true, '--quiet' => true]);
});

function studyBrowserLogin(array $f): mixed
{
    return visit('/admin/login')->fill('[id="form.email"]', $f['email'])->fill('[id="form.password"]', 'password')->press('button[type="submit"]')->wait(1)->assertPathIs('/admin');
}

it('navigates from board through intakes pagination refresh revocation and logout with minimized state', function (): void {
    $f = AdminOperationsFixtures::create();
    $first = null;
    for ($i = 1; $i <= 27; $i++) {
        $intake = StudyWorklistFixtures::intake($f, sprintf('T-%03d', $i), 'awaiting_ai');
        DB::table('operator_queue_admissions')->where('id', $intake['admission'])->update(['created_at' => sprintf('2030-01-01 00:00:%02d', $i)]);
        $first ??= $intake;
    }
    $page = studyBrowserLogin($f)->select('#board-facility', $f['siteId'])->wait(1)->fill('#board-date', $f['date'])->wait(1)->press('#board-refresh')->wait(1)->click('a[href*="/admin/study-worklist?"]')->wait(1)
        ->assertPathIs('/admin/study-worklist')->assertSee('T-001')->assertSee('T-025')->assertDontSee('T-026')->assertSee('Capture belum diterima')->assertSee('bukan persetujuan radiolog')->assertNoSmoke();
    $page->keys('#study-facility', 'Tab')->assertScript("document.activeElement.id === 'study-date'");
    $page->keys('#study-session', 'Tab')->assertScript("document.activeElement.id === 'study-refresh'");
    $page->press('#study-next')->wait(1)->assertSee('T-026')->assertDontSee('T-025')->press('#study-previous')->wait(1)->assertSee('T-001');
    $capture = StudyWorklistFixtures::capture($f, $first, 'completed');
    $study = StudyWorklistFixtures::study($capture);
    StudyWorklistFixtures::job($first, $capture, $study, 'report_ready');
    $page->keys('#study-refresh', 'Enter')->wait(1)->assertSee('Keluaran AI ditandai siap, belum diverifikasi')->assertNoSmoke();
    expect($page->content())->not->toContain($first['booking'], $first['admission'], $capture, $study, 'Private Synthetic Identity', 'PRIVATE_KEY_SENTINEL', 'PRIVATE_FINDINGS_SENTINEL');
    DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.booking.read')->update(['active' => false]);
    $page->press('#study-refresh')->wait(1)->assertSee('Tidak diizinkan')->assertDontSee('T-001')->assertDontSee('Keluaran AI ditandai siap, belum diverifikasi');
    $page->click('.fi-user-menu-trigger')->wait(1)->press('form[action$="/admin/logout"] button')->wait(1)->assertPathIs('/admin/login');
    $page->navigate('/admin/study-worklist')->wait(1)->assertPathIs('/admin/login');
});

it('withholds corrupt source data and forged scope on real updates', function (): void {
    $f = AdminOperationsFixtures::create();
    $intake = StudyWorklistFixtures::intake($f);
    $capture = StudyWorklistFixtures::capture($f, $intake);
    $page = studyBrowserLogin($f)->navigate('/admin/study-worklist?'.http_build_query(['facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']]))->wait(1)->assertSee('T-001');
    DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['processing_status' => 'unknown']);
    $page->press('#study-refresh')->wait(1)->assertSee('Data tidak tersedia')->assertDontSee('T-001');
    $page->script("let element = document.querySelector('#study-facility'); while (!element.hasAttribute('wire:id')) element = element.parentElement; window.Livewire.find(element.getAttribute('wire:id')).set('session', 'forged-session');");
    $page->wait(1)->press('#study-refresh')->wait(1)->assertSee('Data tidak tersedia')->assertDontSee('T-001');
    expect($page->content())->not->toContain($intake['admission'], $intake['booking'], $capture);
});
