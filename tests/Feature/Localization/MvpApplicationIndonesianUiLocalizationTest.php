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

        $this->view('operator.study', [
            'study_id' => 'synthetic-study',
            'window_center' => 0,
            'window_width' => 1,
        ])
            ->assertSee('Studi DICOM')
            ->assertSee('Unduh DICOM');
    }
}
