<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04pPublicQueueDisplayTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2040-01-10T03:30:00+00:00')));
    }

    public function test_public_lcd_page_uses_indonesian_labels(): void
    {
        $fixture = $this->operatorFixture(false);

        $this->get(route('lcd.show', $fixture['siteLocalId']))
            ->assertOk()
            ->assertSee('class="lcd-shell"', false)
            ->assertSee('id="lcd-clock"', false)
            ->assertSee('class="current-hero"', false)
            ->assertSee('class="recent-grid"', false)
            ->assertSee('Antrian rumah skrining')
            ->assertSee('Panggilan saat ini')
            ->assertSee('Panggilan terbaru')
            ->assertSee('Menunggu panggilan berikutnya');
    }

    public function test_public_queue_endpoint_returns_only_called_ticket_data_for_the_selected_site(): void
    {
        $fixture = $this->operatorFixture(false);
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
            'ticket_number' => 'LCD-001',
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
            'state' => 'waiting',
            'ready_at' => $now,
            'operator_profile_id' => null,
            'claimed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.basic-examination-worklist.claim', $admissionId), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('operator.basic-examination-worklist.call', $admissionId), ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->app['auth']->guard()->logout();

        $response = $this->getJson(route('lcd.queue', $fixture['siteLocalId']))
            ->assertOk()
            ->assertJsonPath('current.0.ticket_number', 'LCD-001')
            ->assertJsonPath('current.0.destination', 'PEMERIKSAAN DASAR')
            ->assertJsonPath('recent_calls.0.ticket_number', 'LCD-001')
            ->assertJsonPath('recent_calls.0.destination', 'PEMERIKSAAN DASAR');

        $json = $response->getContent();
        foreach ([$fixture['memberId'], $fixture['bookingId'], $fixture['scheduleId'], $fixture['profileId'], 'Synthetic Arrival Member', 'MRN-'] as $value) {
            $this->assertStringNotContainsString($value, $json);
        }

        DB::table('operator_queue_admissions')->where('id', $admissionId)->update(['stage' => 'xray']);

        $this->getJson(route('lcd.queue', $fixture['siteLocalId']))
            ->assertOk()
            ->assertJsonPath('current.0.destination', 'SESI FOTO RADIOGRAFI')
            ->assertJsonPath('recent_calls.0.destination', 'SESI FOTO RADIOGRAFI')
            ->assertJsonMissing(['destination' => 'X-ray'])
            ->assertJsonMissing(['destination' => 'Rontgen']);

        $payload = $this->getJson(route('lcd.queue', $fixture['siteLocalId']))->json();
        $this->assertSame(['ticket_number', 'destination'], array_keys($payload['current'][0]));
        $this->assertSame(['ticket_number', 'destination'], array_keys($payload['recent_calls'][0]));

    }

    public function test_lcd_excludes_future_and_ended_schedules_but_includes_an_active_schedule(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2040-01-10T03:30:00+00:00')));

        DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->update([
            'starts_at' => '2040-01-10 04:00:00',
            'ends_at' => '2040-01-10 05:00:00',
        ]);
        $this->getJson(route('lcd.queue', $fixture['siteLocalId']))->assertOk()->assertJsonPath('current', [])->assertJsonPath('recent_calls', []);

        DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->update([
            'starts_at' => '2040-01-10 03:00:00',
            'ends_at' => '2040-01-10 04:00:00',
        ]);
        $this->getJson(route('lcd.queue', $fixture['siteLocalId']))->assertOk()->assertJsonPath('current', [])->assertJsonPath('recent_calls', []);

        DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->update([
            'starts_at' => '2040-01-10 02:00:00',
            'ends_at' => '2040-01-10 03:00:00',
        ]);
        $this->getJson(route('lcd.queue', $fixture['siteLocalId']))->assertOk()->assertJsonPath('current', [])->assertJsonPath('recent_calls', []);
    }

    public function test_ended_schedule_hides_existing_current_and_recent_rows(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('shift_schedules')->where('id', $fixture['scheduleId'])->update(['starts_at' => '2040-01-10 02:00:00', 'ends_at' => '2040-01-10 03:00:00']);
        $this->getJson(route('lcd.queue', $fixture['siteLocalId']))
            ->assertOk()
            ->assertJsonPath('current', [])
            ->assertJsonPath('recent_calls', []);
    }
}
