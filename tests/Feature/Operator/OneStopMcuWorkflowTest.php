<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OneStopMcuService;
use App\Modules\Operator\Domain\OperatorException;
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
        $this->assertFalse(Schema::hasColumn('operator_mcu_examinations', 'nik'));
        $this->assertSame('waiting', DB::table('operator_queue_admissions')->where('id', $admissionId)->value('state'));
        $this->assertSame(0, DB::table('operator_vital_signs_executions')->count());

        $response = $this->get(route('operator.one-stop-mcu.pdf', $admissionId))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $document = (new Parser)->parseContent($response->getContent());
        $text = $document->getText();
        $this->assertCount(1, $document->getPages());
        foreach ([
            'Synthetic Arrival Member',
            '10-01-1988',
            '900000000001',
            'Rumah Skrining CV Prestige',
            'PT Madeena',
            'Jl. Lowanu No.68-72, Sorosutan',
            'Kec. Umbulharjo, Kota Yogyakarta',
            'Daerah Istimewa Yogyakarta 55162',
            '+62 897-7067-528',
            'Konsultasi hasil skrining via WhatsApp:',
            'dr. Noor Istichawari, M.M. (dr. Nunung)',
            '+62 822-3107-9219',
            'Untuk konsultasi dan tindak lanjut setelah pemeriksaan.',
            (string) $exam->height_cm,
            'Microtoise',
            '350',
            '330',
            '340',
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
        $this->assertStringNotContainsString((string) DB::table('members')->where('id', $fixture['memberId'])->value('medical_record_number'), $text);
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
        $payload['pef_attempt_ii'] = '100';
        $payload['pef_attempt_iii'] = '300';
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)->assertRedirect();

        $this->assertSame('300', number_format((float) DB::table('operator_mcu_examinations')->value('pef_highest_value'), 0, '.', ''));
        $highest = number_format((float) DB::table('operator_mcu_examinations')->value('pef_highest_value'), 2, '.', '');
        $this->assertNotSame(number_format((250 + 100 + 300) / 3, 2, '.', ''), $highest);
        $this->assertDatabaseCount('operator_mcu_examinations', 1);
    }

    public function test_each_missing_or_invalid_pef_attempt_is_rejected(): void
    {
        foreach ([
            ['pef_attempt_i' => ''],
            ['pef_attempt_ii' => ''],
            ['pef_attempt_iii' => ''],
            ['pef_attempt_i' => '', 'pef_attempt_ii' => '', 'pef_attempt_iii' => ''],
            ['pef_attempt_i' => '0'],
            ['pef_attempt_ii' => '-1'],
            ['pef_attempt_iii' => 'not-a-number'],
            ['pef_attempt_i' => '1e309'],
        ] as $index => $invalid) {
            [, $admissionId] = $this->checkedInAdmission('MCU-PEF-INVALID-'.$index, '90000000000'.$index);
            $payload = array_replace($this->validPayload(), $invalid);

            $this->post(route('operator.one-stop-mcu.store', $admissionId), $payload)
                ->assertSessionHasErrors(array_keys($invalid));
            $this->assertDatabaseCount('operator_mcu_examinations', 0);
        }
    }

    public function test_service_layer_rejects_missing_pef_even_without_http_validation(): void
    {
        [, $admissionId] = $this->checkedInAdmission('MCU-PEF-SERVICE');
        foreach ([
            ['pef_attempt_i' => null],
            ['pef_attempt_ii' => null],
            ['pef_attempt_iii' => null],
            ['pef_attempt_i' => null, 'pef_attempt_ii' => null, 'pef_attempt_iii' => null],
            ['pef_attempt_i' => '0'],
            ['pef_attempt_ii' => '-1'],
            ['pef_attempt_iii' => 'invalid'],
            ['pef_attempt_i' => '1e309'],
        ] as $invalid) {
            $payload = $this->validPayload();
            foreach ($invalid as $key => $value) {
                if ($value === null) {
                    unset($payload[$key]);
                } else {
                    $payload[$key] = $value;
                }
            }
            try {
                app(OneStopMcuService::class)->record($admissionId, $payload);
                $this->fail('The service must reject missing or invalid PEF attempts.');
            } catch (OperatorException $exception) {
                $this->assertSame('mcu_invalid', $exception->category);
            }
        }

        $this->assertDatabaseCount('operator_mcu_examinations', 0);
    }

    public function test_pdf_is_unavailable_when_canonical_nik_is_missing(): void
    {
        [$fixture, $admissionId] = $this->checkedInAdmission('MCU-NIK-MISSING');
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $this->validPayload())->assertRedirect();
        DB::table('members')->where('id', $fixture['memberId'])->update(['encrypted_nik' => null, 'nik_lookup_digest' => null]);

        $this->get(route('operator.one-stop-mcu.pdf', $admissionId))
            ->assertNotFound()
            ->assertDontSee(DB::table('members')->where('id', $fixture['memberId'])->value('medical_record_number'));
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
        $this->get(route('operator.one-stop-mcu.pdf', $admissionId))->assertForbidden()->assertDontSee('900000000001');
        $this->post(route('operator.one-stop-mcu.store', $admissionId), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('operator_mcu_examinations', 1);
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function checkedInAdmission(string $ticketNumber, string $nik = '900000000001'): array
    {
        $fixture = $this->operatorFixture(false, $nik);
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
