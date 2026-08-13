<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'The submission identity was reused with different capture data.',
            'A trusted Operator context is required.',
            'Exactly one non-empty NPZ pair is required.',
            'NPZ uploads must be non-empty ZIP files within the size limit.',
            'The NPZ pair exceeds the request size limit.',
            'Authoritative capture metadata is unavailable.',
            'DICOM study unavailable.',
            'The DICOM viewer is unavailable.',
            'DICOM study ready. Automatic VOI is applied.',
            'The DICOM study could not be displayed.',
            'Study mode badges',
            'Pointer drag pans the image. Use the mouse wheel to zoom.',
            'JavaScript is unavailable. Continue in this tab; enable JavaScript for monitor viewing.',
            'The X-ray admission is unavailable to this Operator.',
        ] as $message) {
            $this->assertArrayHasKey($message, $copy);
            $this->assertNotSame($message, $copy[$message]);
        }
        $this->assertArrayHasKey('NIK:', $copy);
        foreach ([
            'An authorized active site is required.',
            'That site is not authorized for this Operator.',
            'Select an authorized active site before continuing.',
            'Site switching is blocked while arrival work is unresolved.',
            'Site switching is blocked while identity verification is unresolved.',
            'The requested attendance list is unavailable.',
            'An arrival operation identity is required.',
            'The arrival confirmation is no longer valid.',
            'The arrival confirmation has expired.',
            'The Operator is not assigned to this schedule.',
            'The requested arrival is unavailable.',
            'The arrival confirmation is missing or invalid.',
            'Arrival time requires an explicit offset.',
            'Arrival time is invalid.',
            'Operator access is unavailable.',
            'Select an active site before continuing.',
            'Select an authorized active site before continuing.',
            'Identity verification authorization is unavailable.',
            'Operator administration authorization is required.',
            'Operator administration authorization is unavailable.',
            'The Operator profile is unavailable.',
            'The arrived Member is unavailable for verification.',
            'The verification operation conflicts with existing work.',
            'This arrived Member is already claimed for verification.',
            'This verification case is terminal and cannot be reopened.',
            'Reclaiming a cancelled verification case requires explicit confirmation.',
            'This Operator already has an open verification case.',
            'The identity verification view is unavailable.',
            'The requested verification asset is unavailable.',
            'The verification decision is invalid.',
            'A reason is required for this verification decision.',
            'A terminal verification decision cannot be changed.',
            'Current approved identity evidence is unavailable.',
            'A terminal verification case cannot be cancelled.',
            'The verification case is no longer open.',
            'The active site is unavailable.',
            'A valid verification operation is required.',
            'A bounded reason is required.',
            'The paper-consent booking is unavailable.',
            'A valid paper-consent operation is required.',
            'Only a matched identity case can confirm paper consent.',
            'The check-in schedule is unavailable.',
            'The Operator is not assigned to this shift.',
            'The Operator is no longer assigned to this shift.',
            'The paper ticket could not be loaded after issue.',
            'The check-in case is unavailable.',
            'Only a matched identity case can issue a paper ticket.',
            'A valid ticket operation is required.',
            'Ticket number must contain only letters, numbers, and hyphens up to 32 characters.',
        ] as $message) {
            $this->assertArrayHasKey($message, $copy);
            $this->assertNotSame($message, $copy[$message]);
        }

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

        $this->followingRedirects()
            ->get(route('operator.attendance', (string) Str::uuid()))
            ->assertOk()
            ->assertSee('Daftar kehadiran yang diminta tidak tersedia.')
            ->assertDontSee('The requested attendance list is unavailable.');

        $this->get(route('operator.dashboard'))
            ->assertSee('<html lang="id">', false);

        $this->view('operator.study', [
            'study_id' => 'capture-study',
            'window_center' => 0,
            'window_width' => 1,
        ])
            ->assertSee('Studi DICOM')
            ->assertSee('Indikator mode studi')
            ->assertSee('Seret untuk menggeser. Gunakan roda mouse untuk memperbesar atau memperkecil.')
            ->assertSee('JavaScript tidak tersedia. Lanjutkan di tab ini; aktifkan JavaScript untuk melihat di monitor.')
            ->assertDontSee('Study mode badges')
            ->assertSee('Unduh DICOM');
    }
}
