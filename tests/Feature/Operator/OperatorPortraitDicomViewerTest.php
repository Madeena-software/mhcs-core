<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Shared\Security\ProtectedIdentifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;
use ZipArchive;

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

    public function test_study_view_renders_polished_indonesian_current_tab_surface(): void
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
            ->assertSee('Kontrol gambar')
            ->assertSee('Putar ke kiri')
            ->assertSee('Layar penuh')
            ->assertSee('Indikator mode studi')
            ->assertSee('Studi DICOM hanya-baca. VOI otomatis diterapkan; seret untuk menggeser dan gunakan roda mouse untuk memperbesar atau memperkecil.')
            ->assertSee('Seret untuk menggeser. Gunakan roda mouse untuk memperbesar atau memperkecil.')
            ->assertSee('JavaScript tidak tersedia. Aktifkan JavaScript untuk melihat studi ini di tab saat ini.')
            ->assertSee('data-image-url="'.route('operator.study.dicom', $studyId).'"', false)
            ->assertSee('Unduh DICOM')
            ->assertSee(route('operator.study.download', $studyId), false)
            ->assertDontSee('<a download href="'.route('operator.study.download', $studyId).'"', false)
            ->assertSee('download', false)
            ->assertSee('Kembali ke hasil DICOM')
            ->assertSee(route('operator.study.results'), false)
            ->assertSee('dicom-workstation', false)
            ->assertSee('booth-left', false)
            ->assertSee('center-viewer', false)
            ->assertSee('right-sidebar', false)
            ->assertSee('viewer-stage', false)
            ->assertSee('bottom-bar', false)
            ->assertSee('data-testid="dicom-viewport"', false)
            ->assertDontSee('Study mode badges')
            ->assertDontSee('Window/Level')
            ->assertDontSee('Contrast')
            ->assertDontSee('Brightness')
            ->assertDontSee('Annotation')
            ->assertDontSee('Measurement')
            ->assertDontSee('Crop')
            ->assertDontSee('Invert');
    }

    public function test_viewer_bootstrap_and_failure_copy_are_safe(): void
    {
        $viewerSource = (string) file_get_contents(base_path('resources/js/operator-dicom-viewer.js'));
        $appSource = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringNotContainsString('error.message', $viewerSource);
        $this->assertStringContainsString('root.dataset.displayErrorMessage', $viewerSource);
        $this->assertStringContainsString('viewport.setStack([imageId], 0)', $viewerSource);
        $this->assertStringNotContainsString('dicomImageLoader.wadouri.loadImage', $viewerSource);
        $this->assertStringContainsString("import('./operator-dicom-viewer.js')", $appSource);
        $this->assertStringContainsString('withViewerTimeout', $appSource);
    }

    public function test_floating_controls_are_reserved_for_fullscreen_and_stage_uses_available_space(): void
    {
        $viewSource = (string) file_get_contents(resource_path('views/operator/study.blade.php'));

        $this->assertMatchesRegularExpression('/\.viewer-floating-toolbar\s*\{[^}]*display:\s*none;/', $viewSource);
        $this->assertMatchesRegularExpression('/\.viewer-stage:fullscreen \.viewer-floating-toolbar\s*\{[^}]*display:\s*flex;/', $viewSource);
        $this->assertStringContainsString('@media (orientation: portrait) and (min-width: 821px)', $viewSource);
        $this->assertStringContainsString('width: min(96%, 1200px)', $viewSource);
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

    public function test_batch_download_fails_atomically_for_mixed_authority(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $authorized = $this->createAcceptedStudy($fixture, $this->insertCalledXrayAdmission($fixture));
        $foreign = (string) Str::uuid();

        $this->post(route('operator.study.batch-download'), ['studies' => [$authorized, $foreign]])
            ->assertForbidden()
            ->assertDontSee('PK');
    }

    public function test_batch_download_rejects_empty_malformed_and_duplicate_input_safely(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $study = $this->createAcceptedStudy($fixture, $this->insertCalledXrayAdmission($fixture));

        $this->post(route('operator.study.batch-download'), [])->assertSessionHasErrors('studies');
        $this->post(route('operator.study.batch-download'), ['studies' => ['not-a-uuid']])->assertSessionHasErrors('studies.0');
        $response = $this->post(route('operator.study.batch-download'), ['studies' => [$study, $study]]);
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertSame(1, $zip->numFiles);
        $zip->close();
    }

    public function test_batch_download_returns_two_authorized_studies_for_two_members_on_same_shift(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $first = $this->createAcceptedStudy($fixture, $this->insertCalledXrayAdmission($fixture));
        $secondBooking = $this->createSecondBookingForSameShift($fixture);
        $secondAdmission = $this->insertCalledXrayAdmission($fixture, 'TEST-XRAY-02', $secondBooking);
        $this->createAcceptedStudy($fixture, $secondAdmission);
        $second = $this->insertSyntheticStudyForCapture($secondAdmission, $first);
        $firstResponse = $this->get(route('operator.study.dicom', $first))->assertOk();
        $secondResponse = $this->get(route('operator.study.dicom', $second))->assertOk();

        $response = $this->post(route('operator.study.batch-download'), ['studies' => [$first, $second]]);

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $zip = new ZipArchive;
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertTrue($zip->open($path) === true);
        $this->assertSame(2, $zip->numFiles);
        $names = [$zip->getNameIndex(0), $zip->getNameIndex(1)];
        $this->assertSame($firstResponse->getContent(), $zip->getFromName($names[0]));
        $this->assertSame($secondResponse->getContent(), $zip->getFromName($names[1]));
        $this->assertSame([$names[0], $names[1]], array_map('basename', $names));
        $zip->close();
    }

    public function test_results_worklist_has_current_study_selection_and_no_dimensions_column(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $first = $this->createAcceptedStudy($fixture, $this->insertCalledXrayAdmission($fixture));

        $this->get(route('operator.study.results'))
            ->assertOk()
            ->assertDontSee('<th>Dimensi</th>', false)
            ->assertSee('Unduh terpilih')
            ->assertSee('select-all-studies', false)
            ->assertSee('name="studies[]"', false)
            ->assertSee($first);
    }

    public function test_missing_private_dicom_is_denied_without_bubbling_a_storage_500(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);

        DB::table('image_gateway_studies')->where('id', $studyId)->update([
            'object_key' => 'objects/'.Str::uuid(),
        ]);

        $this->get(route('operator.study.dicom', $studyId))->assertForbidden();
    }

    private function createSecondBookingForSameShift(array $fixture): string
    {
        $now = now();
        $memberUser = User::factory()->create(['email' => 'member-'.Str::lower(Str::random(8)).'@example.test']);
        $memberId = (string) Str::uuid();
        $protected = app(ProtectedIdentifierService::class)->protect('900000000002');
        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $memberUser->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.substr($memberId, 0, 8),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
            'name' => 'Synthetic Second Member',
            'birth_date' => '1989-01-10',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Synthetic address',
            'emergency_contact_name' => 'Synthetic contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $booking = (array) DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $booking['id'] = (string) Str::uuid();
        $booking['member_id'] = $memberId;
        $booking['created_at'] = $now;
        $booking['confirmed_at'] = $now;
        $booking['updated_at'] = $now;
        DB::table('bookings')->insert($booking);

        return $booking['id'];
    }

    private function insertSyntheticStudyForCapture(string $admissionId, string $sourceStudyId): string
    {
        $source = (array) DB::table('image_gateway_studies')->where('id', $sourceStudyId)->first();
        $captureId = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->value('id');
        $sourceObjectKey = (string) $source['object_key'];
        $source['id'] = (string) Str::uuid();
        $source['capture_set_id'] = $captureId;
        $source['object_key'] = 'objects/'.Str::uuid();
        $source['study_instance_uid'] .= '.2';
        $source['series_instance_uid'] .= '.2';
        $source['sop_instance_uid'] .= '.2';
        $source['display_reference'] = 'DCM-SYNTH02';
        $source['filename'] = 'DCM-SYNTH02.dcm';
        DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['status' => 'accepted']);
        DB::table('image_gateway_studies')->insert($source);
        Storage::disk('local')->put($source['object_key'], Storage::disk('local')->get($sourceObjectKey));

        return $source['id'];
    }

    private function insertCalledXrayAdmission(array $fixture, string $ticketNumber = 'TEST-XRAY-01', ?string $bookingId = null): string
    {
        $now = now();
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $bookingId ?? $fixture['bookingId'],
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
        $jobId = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        Http::fake([
            '*' => Http::response(
                str_repeat("\0", 128).'DICM'.'valid dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => $jobId,
                    'X-Correlation-ID' => $correlationId,
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

        $captureId = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admission)->value('id');
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        return (string) DB::table('image_gateway_studies as studies')
            ->join('image_gateway_capture_sets as captures', 'captures.id', '=', 'studies.capture_set_id')
            ->where('captures.admission_id', $admission)
            ->value('studies.id');
    }
}
