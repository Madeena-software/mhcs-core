<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

uses(Mvp04Fixtures::class)->in(__FILE__);

beforeEach(function (): void {
    mvpOperatorBrowserPrepareDatabase($this);
    $this->fixture = $this->operatorFixture(false);
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
        ->assertSee('0 ready for X-ray readiness');
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
