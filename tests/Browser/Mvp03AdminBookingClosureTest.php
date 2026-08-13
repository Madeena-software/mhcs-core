<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pest\Browser\Api\AwaitableWebpage;
use Tests\TestCase;

beforeEach(function (): void {
    browserPrepareDatabase($this);
    $this->fixture = browserFixture();
});

it('lets a managing administrator create and edit offerings and schedules from navigation', function (): void {
    $fixture = $this->fixture;

    $page = adminLogin($fixture['manage_admin']);
    $page->assertPathIs('/admin/service-offerings')
        ->assertSeeLink('New Layanan');
    $page->click('a[href$="/admin/service-offerings/create"]')->wait(1);
    $page->fill('[id="form.code"]', 'BROWSER-CREATED')
        ->fill('[id="form.name"]', 'Layanan Browser')
        ->fill('[id="form.point_price"]', '3.2500');
    $page->press('button[wire\\:target="create"]')->wait(1);
    $page
        ->assertPathIs('/admin/service-offerings/*')
        ->click('nav[aria-label="Sidebar navigation"] a[href$="/admin/service-offerings"]')
        ->refresh()
        ->wait(1)
        ->assertPathIs('/admin/service-offerings');
    expect(DB::table('service_offerings')->where('code', 'BROWSER-CREATED')->exists())->toBeTrue();

    $page->click('a.fi-ac-link-action[href$="/admin/service-offerings/'.$fixture['service_id'].'/edit"]')->wait(1)
        ->assertPathIs('/admin/service-offerings/*/edit')
        ->fill('[id="form.code"]', $fixture['service_code'])
        ->fill('[id="form.name"]', 'Layanan Browser Diubah')
        ->fill('[id="form.point_price"]', '2.5000')
        ->assertValue('[id="form.name"]', 'Layanan Browser Diubah');
    $page
        ->press('button[wire\\:target="save"]')
        ->wait(2)
        ->assertPathIs('/admin/service-offerings/*')
        ->click('nav[aria-label="Sidebar navigation"] a[href$="/admin/service-offerings"]')
        ->refresh()
        ->wait(1)
        ->assertPathIs('/admin/service-offerings');

    $page->click('nav[aria-label="Sidebar navigation"] a[href$="/admin/shift-schedules"]')
        ->refresh()
        ->wait(1)
        ->assertPathIs('/admin/shift-schedules')
        ->assertSeeLink('New Jadwal')
        ->assertSee($fixture['schedule_display_reference'])
        ->click('a[href$="/admin/shift-schedules/create"]')
        ->wait(1)
        ->assertPathIs('/admin/shift-schedules/create')
        ->assertSee('Create Jadwal')
        ->click('Cancel')
        ->wait(1)
        ->assertPathIs('/admin/shift-schedules');

    $page->click('a.fi-ac-link-action[href$="/admin/shift-schedules/'.$fixture['schedule_id'].'/edit"]')->wait(1)
        ->fill('[id="form.quota"]', '7')
        ->press('button[wire\\:target="save"]')
        ->wait(1)
        ->assertPathIs('/admin/shift-schedules/*')
        ->assertNoSmoke();
});

it('keeps read-only administrators without create or edit actions', function (): void {
    $page = adminLogin($this->fixture['read_admin']);

    $page->assertPathIs('/admin/service-offerings')
        ->assertDontSee('New Layanan')
        ->assertCount('a[href$="/edit"]', 0)
        ->click('a[href$="/admin/shift-schedules"]')
        ->assertPathIs('/admin/shift-schedules')
        ->assertDontSee('New Jadwal')
        ->assertCount('a[href$="/edit"]', 0)
        ->assertNoSmoke();
});

it('lets a member book through the visible catalogue and hides the booking from another member', function (): void {
    $fixture = $this->fixture;

    $page = memberLogin($fixture['member']);
    $page->click('Sesi Foto Radiografi')
        ->wait(1)
        ->assertPathIs('/member/services')
        ->click('Lihat jadwal')
        ->wait(1)
        ->assertPathIs('/member/services/'.$fixture['service_id'])
        ->check('confirmation')
        ->press('Konfirmasi Jadwal')
        ->wait(1)
        ->assertPathBeginsWith('/member/bookings/')
        ->assertSee('Sesi Foto Radiografi Terjadwal')
        ->assertSee($fixture['service_code'])
        ->assertDontSee('nik_lookup_digest');

    $bookingId = (string) Str::after($page->url(), '/member/bookings/');
    expect($bookingId)->not->toBeEmpty();

    $page->press('Keluar')
        ->wait(1)
        ->assertPathIs('/login')
        ->fill('identifier', $fixture['other_member']['email'])
        ->fill('password', 'password')
        ->press('button[type="submit"]')
        ->wait(1)
        ->assertPathIs('/member/dashboard')
        ->navigate('/member/bookings/'.$bookingId)
        ->wait(1)
        ->assertDontSee('Detail Sesi Foto Radiografi')
        ->assertDontSee($fixture['service_code'])
        ->assertNoSmoke();
});

/** @return array<string, mixed> */
function browserFixture(): array
{
    $now = now();
    $suffix = Str::lower(Str::random(8));
    $memberUser = User::factory()->create(['email' => 'browser-member-'.$suffix.'@example.test']);
    $otherUser = User::factory()->create(['email' => 'browser-other-'.$suffix.'@example.test']);
    $manageAdmin = User::factory()->create(['email' => 'browser-manage-'.$suffix.'@example.test']);
    $readAdmin = User::factory()->create(['email' => 'browser-read-'.$suffix.'@example.test']);

    $memberId = (string) Str::uuid();
    $otherMemberId = (string) Str::uuid();
    $organizationId = (string) Str::uuid();
    $siteId = (string) Str::uuid();
    $serviceId = (string) Str::uuid();
    $scheduleId = (string) Str::uuid();
    $rateId = (string) Str::uuid();
    $serviceCode = 'BROWSER-'.$suffix;

    foreach ([[$memberId, $memberUser, 'Browser Member'], [$otherMemberId, $otherUser, 'Browser Other']] as [$id, $user, $name]) {
        DB::table('members')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.Str::upper(Str::random(10)),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'protected',
            'nik_lookup_digest' => hash('sha256', $id),
            'name' => $name,
            'birth_date' => '1988-01-01',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Browser address',
            'emergency_contact_name' => 'Browser contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'org-'.$organizationId, 'name' => 'Organisasi Browser', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'site-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-BROWSER', 'display_name' => 'Lokasi Browser', 'timezone' => 'Asia/Jakarta', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => $serviceCode, 'name' => 'Layanan Browser', 'includes_ai' => true, 'includes_doctor' => false, 'point_price' => '2.5000', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('point_exchange_rates')->insert(['id' => $rateId, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => $now, 'configured_by_admin_id' => null, 'created_at' => $now, 'updated_at' => $now]);
    $scheduleDisplayReference = 'JAD-'.Str::upper(Str::random(8));
    DB::table('shift_schedules')->insert(['id' => $scheduleId, 'display_reference' => $scheduleDisplayReference, 'examination_site_id' => $siteId, 'service_offering_id' => $serviceId, 'starts_at' => '2040-05-01 03:00:00', 'ends_at' => '2040-05-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => null, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $memberId, 'booking_id' => null, 'funding_source' => 'personal', 'entry_type' => 'credit', 'point_delta' => '10.0000', 'source_reference' => 'test:browser-credit:'.$suffix, 'reverses_id' => null, 'created_at' => $now]);

    browserGrant($manageAdmin, ['member.admin.access', 'member.catalogue.read', 'member.catalogue.manage', 'member.schedule.read', 'member.schedule.manage']);
    browserGrant($readAdmin, ['member.admin.access', 'member.catalogue.read', 'member.schedule.read']);

    return [
        'member' => ['email' => $memberUser->email],
        'other_member' => ['email' => $otherUser->email],
        'manage_admin' => ['email' => $manageAdmin->email],
        'read_admin' => ['email' => $readAdmin->email],
        'service_id' => $serviceId,
        'schedule_id' => $scheduleId,
        'schedule_display_reference' => $scheduleDisplayReference,
        'service_code' => $serviceCode,
    ];
}

function browserPrepareDatabase(TestCase $test): void
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

/** @param list<string> $permissions */
function browserGrant(User $user, array $permissions): void
{
    DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'administrator', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($permissions as $permission) {
        DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}

function adminLogin(array $credentials): AwaitableWebpage
{
    return visit('/admin/login')
        ->fill('[id="form.email"]', $credentials['email'])
        ->fill('[id="form.password"]', 'password')
        ->press('button[type="submit"]')
        ->wait(0.1);
}

function memberLogin(array $credentials): AwaitableWebpage
{
    return visit('/login')
        ->fill('identifier', $credentials['email'])
        ->fill('password', 'password')
        ->press('button[type="submit"]')
        ->wait(0.1);
}
