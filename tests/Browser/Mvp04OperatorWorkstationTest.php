<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

uses(Mvp04Fixtures::class)->in(__FILE__);

beforeEach(function (): void {
    mvpOperatorBrowserPrepareDatabase($this);
    $this->fixture = $this->operatorFixture(false);
    DB::table('shift_schedules')->where('id', $this->fixture['scheduleId'])->update([
        'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
    ]);
});

it('lets an operator enter the workstation, select a site, and see the ordered clinic flow', function (): void {
    $fixture = $this->fixture;

    visit(route('operator.login'))
        ->wait(1)
        ->assertPathIs('/operator/login')
        ->assertSee('Operator workstation')
        ->fill('identifier', $fixture['operator']->email)
        ->fill('password', 'password')
        ->press('Sign in')
        ->wait(1)
        ->assertPathIs('/operator')
        ->assertSee('Select an assigned site')
        ->click('Select an assigned site')
        ->wait(1)
        ->assertPathIs('/operator/site')
        ->assertSee('Authorized site')
        ->press('Set active site')
        ->wait(1)
        ->assertPathIs('/operator')
        ->assertSee('Synthetic Operator Site')
        ->assertSee('1. Attendance')
        ->assertSee('2. Arrival and verification')
        ->assertSee('3. Consent and ticket')
        ->assertSee('4. Basic examination')
        ->assertSee('5. X-ray readiness')
        ->assertSee('0 awaiting verification')
        ->assertSee('0 ready for basic examination')
        ->assertSee('0 ready for X-ray readiness')
        ->click('Open assigned-shift attendance')
        ->wait(1)
        ->assertPathIs('/operator/eligible-shifts')
        ->click('Open attendance')
        ->wait(1)
        ->assertPathIs('/operator/attendance/'.$fixture['scheduleId'])
        ->assertSee('Attendance list')
        ->assertSee('Synthetic Arrival Member')
        ->assertSee('1 eligible members')
        ->assertSee('Record physical arrival');
});

function mvpOperatorBrowserPrepareDatabase(TestCase $test): void
{
    $database = storage_path('framework/testing/mhcs-operator-browser.sqlite');
    @unlink($database);
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
    ]);
    putenv('DB_DATABASE='.$database);
    DB::purge('sqlite');
    $test->artisan('migrate:fresh', ['--quiet' => true]);
}
