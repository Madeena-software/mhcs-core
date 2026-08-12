<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class MvpApplicationIndonesianUiLocalizationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_application_uses_indonesian_json_copy_for_representative_surfaces(): void
    {
        $fixture = $this->operatorFixture(false);
        $copy = json_decode((string) file_get_contents(base_path('lang/id.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', config('app.fallback_locale'));
        $this->assertSame('Antrian rumah skrining', $copy['Clinic queue']);
        $this->assertSame('SESI FOTO RADIOGRAFI', $copy['Radiography session']);
        $this->assertSame('Kolom NIK wajib diisi.', trans('validation.required', ['attribute' => 'NIK']));
        foreach ([
            'A valid submission identity is required.',
            'This X-ray admission already has a capture set.',
            'The synthetic DICOM fixture is invalid.',
            'The submission identity was reused with different capture data.',
            'A trusted Operator context is required.',
            'Exactly one synthetic radiograph is required.',
            'Synthetic inputs must be ZIP NPZ files.',
            'The synthetic fixture identity is not accepted.',
            'The synthetic fixture bytes are not accepted.',
            'The repository-owned synthetic fixture is unavailable.',
            'The X-ray admission is unavailable to this Operator.',
        ] as $message) {
            $this->assertArrayHasKey($message, $copy);
            $this->assertNotSame($message, $copy[$message]);
        }
        $this->assertArrayHasKey('NIK:', $copy);

        $this->actingAs($fixture['memberUser'])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Dasbor Member');

        $this->get(route('lcd.show', $fixture['siteLocalId']))
            ->assertOk()
            ->assertSee('Antrian rumah skrining')
            ->assertSee('Panggilan saat ini');

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Dasbor operator')
            ->assertSee('PEMERIKSAAN DASAR');

        $this->get(route('operator.attendance', $fixture['scheduleId']))
            ->assertOk()
            ->assertSee($copy['NIK:']);

        $this->get(route('operator.dashboard'))
            ->assertSee('<html lang="id">', false);

        $this->view('operator.study', [
            'study_id' => 'synthetic-study',
            'window_center' => 0,
            'window_width' => 1,
        ])
            ->assertSee('Studi DICOM')
            ->assertSee('Unduh DICOM');
    }
}
