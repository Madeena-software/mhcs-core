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
        ->assertSee('Dasbor operator')
        ->fill('identifier', $fixture['operator']->email)
        ->fill('password', 'password')
        ->press('Masuk')
        ->wait(1)
        ->assertPathIs('/operator')
        ->assertSee('Pilih lokasi yang ditugaskan')
        ->click('Pilih lokasi yang ditugaskan')
        ->wait(1)
        ->assertPathIs('/operator/site')
        ->assertSee('Lokasi yang diizinkan')
        ->press('Tetapkan lokasi aktif')
        ->wait(1)
        ->assertPathIs('/operator')
        ->assertSee('Synthetic Operator Site')
        ->assertSee('1. Kehadiran')
        ->assertSee('2. Kedatangan dan verifikasi')
        ->assertSee('3. Persetujuan dan tiket')
        ->assertSee('4. PEMERIKSAAN DASAR')
        ->assertSee('5. Kesiapan sesi foto radiografi')
        ->assertSee('0 menunggu verifikasi')
        ->assertSee('0 siap untuk pemeriksaan dasar')
        ->assertSee('0 siap untuk sesi foto radiografi')
        ->click('Buka kehadiran shift yang ditugaskan')
        ->wait(1)
        ->assertPathIs('/operator/eligible-shifts')
        ->assertSee($fixture['scheduleDisplayReference'])
        ->click('Buka kehadiran')
        ->wait(1)
        ->assertPathIs('/operator/attendance/'.$fixture['scheduleId'])
        ->assertSee('Daftar kehadiran')
        ->assertSee('Synthetic Arrival Member')
        ->assertSee('1 Member yang memenuhi syarat')
        ->assertSee($fixture['scheduleDisplayReference'])
        ->assertSee('Catat kedatangan fisik');
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
