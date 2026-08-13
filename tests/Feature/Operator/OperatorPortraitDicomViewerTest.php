<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorPortraitDicomViewerTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.private_object_disk' => 'local',
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.manifest_key' => str_repeat('m', 32),
            'mhcs.security.manifest_key_id' => 'test-key',
            'mhcs.mpips.base_url' => 'http://127.0.0.1:8014',
            'mhcs.mpips.api_key' => 'test-api-key',
        ]);
        Storage::fake('local');
    }

    public function test_redesigned_study_view_renders_polished_indonesian_portrait_surface_with_monitor_popup_control(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);

        $studyReference = (string) DB::table('image_gateway_studies')->where('id', $studyId)->value('display_reference');
        $this->assertMatchesRegularExpression('/\ADCM-[A-Z0-9]{8}\z/', $studyReference);

        $response = $this->get(route('operator.study.show', $studyId));

        $response->assertOk()
            ->assertSee($studyReference)
            ->assertSee('VOI otomatis')
            ->assertSee('Hanya zoom dan geser')
            ->assertSee('Buka di monitor')
            ->assertSee(route('operator.study.show', $studyId), false)
            ->assertSee('Unduh DICOM')
            ->assertSee(route('operator.study.download', $studyId), false)
            ->assertSee('Kembali ke hasil DICOM')
            ->assertSee(route('operator.study.results'), false)
            ->assertSee('data-popup-blocked-message="Browser memblokir jendela pop-up. Lanjutkan pada tab ini atau izinkan pop-up."', false)
            ->assertSee('data-testid="dicom-viewport"', false)
            ->assertDontSee('Window/Level')
            ->assertDontSee('Contrast')
            ->assertDontSee('Brightness')
            ->assertDontSee('Rotate')
            ->assertDontSee('Annotation')
            ->assertDontSee('Measurement')
            ->assertDontSee('Crop')
            ->assertDontSee('Invert');
    }

    public function test_unauthorized_operator_or_foreign_site_is_denied_access_to_study(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);

        $this->get(route('operator.study.show', $studyId))->assertOk();

        // Foreign site operator
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'foreign-'.$fixture['memberId']),
        ]);
        $foreign = $this->operatorFixture(false);
        $this->actingAs($foreign['operator'])->withSession(['operator.active_site_id' => $foreign['siteLocalId']]);
        $this->get(route('operator.study.show', $studyId))->assertForbidden();

        // Unauthenticated
        $this->actingAsGuest();
        $this->flushSession();
        $this->get(route('operator.study.show', $studyId))->assertRedirect('/login');
    }

    public function test_display_reference_and_download_disposition_are_preserved(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);
        $studyReference = (string) DB::table('image_gateway_studies')->where('id', $studyId)->value('display_reference');

        $download = $this->get(route('operator.study.download', $studyId));
        $download->assertOk();
        $this->assertSame('attachment; filename="'.$studyReference.'.dcm"', (string) $download->headers->get('Content-Disposition'));

        $this->get(route('operator.study.dicom', $studyId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/dicom')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    private function insertCalledXrayAdmission(array $fixture): string
    {
        $now = now();
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TEST-XRAY-01',
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
            'stage' => 'xray',
            'state' => 'called',
            'ready_at' => $now,
            'operator_profile_id' => $fixture['profileId'],
            'claimed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $admissionId;
    }

    private function createAcceptedStudy(array $fixture, string $admission): string
    {
        Http::fake([
            '*' => Http::response(
                str_repeat("\0", 128).'DICM'.'valid dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            ),
        ]);

        $this->post(route('operator.xray-capture.store', $admission), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => [
                'examination' => ['study_description' => 'CHEST RADIOGRAPH'],
                'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA'],
            ],
            'radiograph_npz' => new UploadedFile(
                base_path('resources/fixtures/image-gateway/synthetic-radiograph-01.npz'),
                'synthetic-radiograph-01.npz',
                'application/octet-stream',
                null,
                true,
            ),
            'gain_npz' => new UploadedFile(
                base_path('resources/fixtures/image-gateway/synthetic-gain-01.npz'),
                'synthetic-gain-01.npz',
                'application/octet-stream',
                null,
                true,
            ),
        ])->assertRedirect(route('operator.study.results'));

        $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        return (string) DB::table('image_gateway_studies')->value('id');
    }
}
