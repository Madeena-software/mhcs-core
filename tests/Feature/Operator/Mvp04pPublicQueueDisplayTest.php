<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04pPublicQueueDisplayTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

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
            ->assertJsonPath('current.0.destination', 'Basic examination')
            ->assertJsonPath('recent_calls.0.ticket_number', 'LCD-001')
            ->assertJsonPath('recent_calls.0.destination', 'Basic examination');

        $json = $response->getContent();
        foreach ([$fixture['memberId'], $fixture['bookingId'], $fixture['scheduleId'], $fixture['profileId'], 'Synthetic Arrival Member', 'MRN-'] as $value) {
            $this->assertStringNotContainsString($value, $json);
        }
    }
}
