<?php

declare(strict_types=1);

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

uses(Mvp04Fixtures::class)->in(__FILE__);

beforeEach(function (): void {
    mvpCoreBrowserPrepareDatabase($this);
    $this->fixture = $this->operatorFixture(false);
});

it('shows the private printer ticket and safe LCD failure recovery', function (): void {
    $fixture = $this->fixture;
    $now = now();
    $ticketId = (string) Str::uuid();
    $admissionId = (string) Str::uuid();

    DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'checked_in']);
    DB::table('operator_paper_tickets')->insert([
        'id' => $ticketId,
        'booking_id' => $fixture['bookingId'],
        'member_schedule_id' => $fixture['scheduleId'],
        'operator_site_id' => $fixture['siteLocalId'],
        'operator_profile_id' => $fixture['profileId'],
        'ticket_number' => 'BROWSER-001',
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('operator_queue_admissions')->insert([
        'id' => $admissionId,
        'operator_paper_ticket_id' => $ticketId,
        'operator_site_id' => $fixture['siteLocalId'],
        'member_schedule_id' => $fixture['scheduleId'],
        'queue_class' => 'advance',
        'stage' => 'basic_examination',
        'state' => 'called',
        'ready_at' => $now,
        'operator_profile_id' => $fixture['profileId'],
        'claimed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('operator_queue_admission_history')->insert([
        'id' => (string) Str::uuid(),
        'operator_queue_admission_id' => $admissionId,
        'operator_profile_id' => $fixture['profileId'],
        'event_type' => 'called',
        'from_state' => 'waiting',
        'to_state' => 'called',
        'operation_id' => (string) Str::uuid(),
        'occurred_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('authorization_permission_assignments')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $fixture['operator']->id,
        'permission' => 'operator.identity.verify',
        'assigned_by_user_id' => null,
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Auth::guard('web')->login($fixture['operator']);
    Auth::shouldUse('web');
    $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
    $this->app['session']->save();
    $sessionCookie = app('encrypter')->encrypt(
        CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$this->app['session']->getId(),
        false,
    );

    $page = visit(route('operator.paper-ticket.print', $ticketId), [
        'storageState' => [
            'cookies' => [[
                'name' => config('session.cookie'),
                'value' => $sessionCookie,
                'domain' => '127.0.0.1',
                'path' => '/',
            ]],
            'origins' => [],
        ],
    ])
        ->wait(1)
        ->assertPathIs('/operator/paper-tickets/'.$ticketId.'/print')
        ->assertSee('Synthetic Operator Site')
        ->assertSee('BROWSER-001')
        ->assertDontSee('Synthetic Arrival Member')
        ->assertDontSee('MRN-');

    $lcd = $page->navigate(route('lcd.show', $fixture['siteLocalId']))
        ->wait(1)
        ->assertSee('BROWSER-001')
        ->assertSee('Basic examination')
        ->assertMissing('#queue-status')
        ->assertDontSee('Synthetic Arrival Member')
        ->assertDontSee($fixture['memberId']);

    $lcd->script('window.__mvpOriginalFetch = window.fetch; window.fetch = async () => new Response("", { status: 503, statusText: "synthetic disconnect" });');
    $lcd->wait(6)
        ->assertVisible('#queue-status')
        ->assertSee('Queue disconnected — shown calls may be stale.');

    $lcd->script('window.fetch = window.__mvpOriginalFetch;');
    $lcd->wait(6)
        ->assertMissing('#queue-status')
        ->assertSee('BROWSER-001')
        ->assertDontSee('Synthetic Arrival Member')
        ->assertDontSee($fixture['memberId']);
});

function mvpCoreBrowserPrepareDatabase(TestCase $test): void
{
    $database = storage_path('framework/testing/mhcs-browser.sqlite');
    @unlink($database);
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
    ]);
    putenv('DB_DATABASE='.$database);
    DB::purge('sqlite');
    $test->artisan('migrate:fresh', ['--quiet' => true]);
}
