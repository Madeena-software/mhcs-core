<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OneStopMcuWorkflowTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_operator_saves_independent_mcu_exam_and_downloads_pdf_from_persisted_values(): void
    {
        [$fixture, $admissionId] = $this->checkedInAdmission('MCU-1');
        $payload = $this->validPayload();

        $this->get(route('operator.one-stop-mcu.index'))
            ->assertOk()
            ->assertSee('MCU Satu Pintu')
            ->assertSee('Synthetic Arrival Member');
        $this->get(route('operator.one-stop-mcu.create', $admissionId))
            ->assertOk()
            ->assertSee('Alat ukur tinggi badan: Microtoise')
            ->assertDontSee('name="microtoise_value"', false);

        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)
            ->assertRedirect(route('operator.one-stop-mcu.index'))
            ->assertSessionHas('status');

        $exam = DB::table('operator_mcu_examinations')->where('operator_queue_admission_id', $admissionId)->first();
        $this->assertNotNull($exam);
        $this->assertSame($fixture['memberId'], $exam->member_id);
        $this->assertSame($fixture['bookingId'], $exam->booking_id);
        $this->assertSame($fixture['profileId'], $exam->operator_profile_id);
        $this->assertSame($fixture['siteLocalId'], $exam->operator_site_id);
        $this->assertSame('23.15', number_format((float) $exam->bmi, 2, '.', ''));
        $this->assertSame('350', number_format((float) $exam->pef_highest_value, 0, '.', ''));
        $this->assertSame('8', number_format((float) $exam->fasting_duration_hours, 0, '.', ''));
        $this->assertSame('95', number_format((float) $exam->glucose_mg_dl, 0, '.', ''));
        $this->assertSame('180', number_format((float) $exam->total_cholesterol_mg_dl, 0, '.', ''));
        $this->assertSame('5.2', number_format((float) $exam->uric_acid_mg_dl, 1, '.', ''));
        $this->assertSame('350', number_format((float) $exam->pef_attempt_i, 0, '.', ''));
        $this->assertSame('330', number_format((float) $exam->pef_attempt_ii, 0, '.', ''));
        $this->assertSame('340', number_format((float) $exam->pef_attempt_iii, 0, '.', ''));
        $this->assertTrue(Schema::hasColumn('operator_mcu_examinations', 'height_cm'));
        $this->assertFalse(Schema::hasColumn('operator_mcu_examinations', 'microtoise_value'));
        $this->assertSame('waiting', DB::table('operator_queue_admissions')->where('id', $admissionId)->value('state'));
        $this->assertSame(0, DB::table('operator_vital_signs_executions')->count());

        $response = $this->get(route('operator.one-stop-mcu.pdf', $admissionId))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $text = (new Parser)->parseContent($response->getContent())->getText();
        foreach ([
            'Synthetic Arrival Member',
            (string) $exam->height_cm,
            'Microtoise',
            '350',
            '120 mmHg',
            '80 mmHg',
            '75 kg',
            '36.5 °C',
            '95 mg/dL',
            '180 mg/dL',
            '5.2 mg/dL',
            'Puasa',
            '8 jam',
            'Follow-up as scheduled.',
            'HASIL SKRINING MERUPAKAN PEMERIKSAAN AWAL DAN BUKAN PENETAPAN DIAGNOSIS MEDIS.',
            'Tanda tangan pemeriksa',
            'Tanda tangan peserta',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertSame(1, substr_count($text, '180 cm'));
        $this->assertStringContainsString('10-01-2040 03:30', $text);

        $this->get(route('operator.one-stop-mcu.pdf', ['admission' => $admissionId, 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="one-stop-mcu-'.$admissionId.'.pdf"');
    }

    public function test_invalid_height_and_pef_values_are_rejected_and_highest_pef_uses_maximum(): void
    {
        [, $admissionId] = $this->checkedInAdmission('MCU-2');
        $payload = $this->validPayload();
        $payload['height_cm'] = '0';
        $payload['pef_attempt_ii'] = '-1';

        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)
            ->assertSessionHasErrors(['height_cm', 'pef_attempt_ii']);
        $this->assertDatabaseCount('operator_mcu_examinations', 0);

        $payload = $this->validPayload();
        $payload['height_cm'] = '1e309';
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)->assertSessionHasErrors('height_cm');
        $this->assertDatabaseCount('operator_mcu_examinations', 0);

        $payload = $this->validPayload();
        $payload['pef_attempt_i'] = '250';
        $payload['pef_attempt_ii'] = '';
        $payload['pef_attempt_iii'] = '300';
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)->assertRedirect();

        $this->assertSame('300', number_format((float) DB::table('operator_mcu_examinations')->value('pef_highest_value'), 0, '.', ''));
        $this->assertDatabaseCount('operator_mcu_examinations', 1);
    }

    public function test_all_missing_pef_attempts_are_saved_without_a_derived_highest_value(): void
    {
        [, $admissionId] = $this->checkedInAdmission('MCU-PEF-MISSING');
        $payload = $this->validPayload();
        $payload['fasting_status'] = 'non_fasting';
        $payload['fasting_duration_hours'] = '';
        $payload['last_meal_at'] = '12:15';
        $payload['pef_attempt_i'] = '';
        $payload['pef_attempt_ii'] = '';
        $payload['pef_attempt_iii'] = '';

        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)->assertRedirect();

        $exam = DB::table('operator_mcu_examinations')->first();
        $this->assertNotNull($exam);
        $this->assertNull($exam->pef_attempt_i);
        $this->assertNull($exam->pef_attempt_ii);
        $this->assertNull($exam->pef_attempt_iii);
        $this->assertNull($exam->pef_highest_value);
        $this->assertSame('non_fasting', $exam->fasting_status);
        $this->assertSame('12:15:00', $exam->last_meal_at);
    }

    public function test_unassigned_operator_cannot_read_or_mutate_an_mcu_examination(): void
    {
        [$fixture, $admissionId] = $this->checkedInAdmission('MCU-3');
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $this->validPayload())->assertRedirect();

        $other = $this->secondOperatorFixture($fixture);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $other['profileId'])->delete();
        $this->actingAs($other['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->get(route('operator.one-stop-mcu.create', $admissionId))->assertForbidden();
        $this->get(route('operator.one-stop-mcu.pdf', $admissionId))->assertForbidden();
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('operator_mcu_examinations', 1);
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function checkedInAdmission(string $ticketNumber): array
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'checked_in']);

        $now = now();
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => $ticketNumber,
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
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$fixture, $admissionId];
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'operation_id' => (string) Str::uuid(),
            'examined_at' => '2040-01-10T03:30',
            'systolic_bp' => '120',
            'diastolic_bp' => '80',
            'weight_kg' => '75',
            'height_cm' => '180',
            'temperature_c' => '36.5',
            'glucose_mg_dl' => '95',
            'total_cholesterol_mg_dl' => '180',
            'uric_acid_mg_dl' => '5.2',
            'fasting_status' => 'fasting',
            'fasting_duration_hours' => '8',
            'last_meal_at' => '',
            'pef_attempt_i' => '350',
            'pef_attempt_ii' => '330',
            'pef_attempt_iii' => '340',
            'notes' => 'Follow-up as scheduled.',
        ];
    }
}
