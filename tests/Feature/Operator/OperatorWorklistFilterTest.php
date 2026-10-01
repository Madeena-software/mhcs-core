<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorWorklistFilterTest extends TestCase
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

    public function test_study_results_view_renders_filter_bar_and_png_download_column(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);

        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        $response = $this->get(route('operator.study.results'));

        $response->assertOk()
            ->assertSee('data-worklist-filter-bar', false)
            ->assertSee('data-filter-query', false)
            ->assertSee('data-filter-date-from', false)
            ->assertSee('data-filter-date-to', false)
            ->assertSee('data-filter-status', false)
            ->assertSee('data-filter-reset', false)
            ->assertSee('data-filter-count', false)
            ->assertSee('Unduh PNG')
            ->assertSee('data-png-download', false)
            ->assertSee('data-dicom-url="'.route('operator.study.dicom', $studyId).'"', false)
            ->assertSee('data-reference="'.$study->display_reference.'"', false)
            ->assertSee('data-worklist-row', false)
            ->assertSee('data-search-text', false)
            ->assertSee('data-row-date', false)
            ->assertSee('data-row-status', false)
            ->assertSee('data-empty-filtered-row', false)
            ->assertSee('Tidak ada data yang sesuai dengan filter.')
            ->assertSee('Atur Ulang');
    }

    public function test_ai_retry_form_is_not_nested_inside_study_selection_form(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);
        $captureSetId = (string) DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id');
        // Mark AI status as retryable failure
        DB::table('image_gateway_ai_jobs')->insert([
            'id' => (string) Str::uuid(),
            'study_id' => $studyId,
            'capture_set_id' => $captureSetId,
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'admission_id' => $admission,
            'status' => 'retryable_failure',
            'attempts' => 1,
            'max_attempts' => 3,
            'last_error_code' => 'NETWORK_TIMEOUT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('operator.study.results'));

        $response->assertOk();
        $content = $response->getContent();

        // Check that AI retry form uses HTML5 form attribute so it is not nested inside batch selection form
        $this->assertStringContainsString('id="ai-retry-form-'.$studyId.'"', $content);
        $this->assertStringContainsString('form="ai-retry-form-'.$studyId.'"', $content);
    }

    public function test_verification_worklist_renders_filter_bar_and_row_data(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $response = $this->get(route('operator.verification-worklist'));

        $response->assertOk()
            ->assertSee('data-worklist-filter-bar', false)
            ->assertSee('data-filter-query', false)
            ->assertSee('data-filter-date-from', false)
            ->assertSee('data-filter-date-to', false)
            ->assertSee('data-filter-status', false)
            ->assertSee('data-filter-reset', false)
            ->assertSee('data-empty-filtered-row', false)
            ->assertSee('Tidak ada data yang sesuai dengan filter.');
    }

    public function test_basic_examination_worklist_renders_filter_bar_and_row_data(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $response = $this->get(route('operator.basic-examination-worklist'));

        $response->assertOk()
            ->assertSee('data-worklist-filter-bar', false)
            ->assertSee('data-filter-query', false)
            ->assertSee('data-filter-date-from', false)
            ->assertSee('data-filter-date-to', false)
            ->assertSee('data-filter-status', false)
            ->assertSee('data-filter-reset', false)
            ->assertSee('data-empty-filtered-row', false)
            ->assertSee('Tidak ada data yang sesuai dengan filter.');
    }

    public function test_xray_readiness_worklist_renders_filter_bar_and_row_data(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $response = $this->get(route('operator.xray-readiness-worklist'));

        $response->assertOk()
            ->assertSee('data-worklist-filter-bar', false)
            ->assertSee('data-filter-query', false)
            ->assertSee('data-filter-date-from', false)
            ->assertSee('data-filter-date-to', false)
            ->assertSee('data-filter-status', false)
            ->assertSee('data-filter-reset', false)
            ->assertSee('data-empty-filtered-row', false)
            ->assertSee('Tidak ada data yang sesuai dengan filter.');
    }

    public function test_unauthorized_operator_or_foreign_site_cannot_access_study_results_or_dicom_for_png(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        $studyId = $this->createAcceptedStudy($fixture, $admission);

        $this->get(route('operator.study.dicom', $studyId))->assertOk();

        // Foreign site operator
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'nik_lookup_digest' => hash('sha256', 'foreign-'.$fixture['memberId']),
        ]);
        $foreign = $this->operatorFixture(false);
        $this->actingAs($foreign['operator'])->withSession(['operator.active_site_id' => $foreign['siteLocalId']]);
        $this->get(route('operator.study.dicom', $studyId))->assertForbidden();

        // Unauthenticated
        $this->actingAsGuest();
        $this->flushSession();
        $this->get(route('operator.study.dicom', $studyId))->assertRedirect('/login');
    }

    private function insertCalledXrayAdmission(array $fixture, string $ticketNumber = 'TEST-XRAY-01'): string
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
        $now = now();
        $captureId = (string) Str::uuid();
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString($fixture['profileId']),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayCaptureService::STUDY_PURPOSE,
        );
        $object = app(PrivateObjectStore::class)->put(str_repeat("\0", 128).'DICM'.'study', $context, ImageGatewayCaptureService::STUDY_PURPOSE);
        DB::table('image_gateway_capture_sets')->insert([
            'id' => $captureId,
            'submission_id' => (string) Str::uuid(),
            'admission_id' => $admission,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'radiograph_count' => 1,
            'status' => 'accepted',
            'accepted_at' => $now,
            'processing_status' => 'completed',
            'attempts' => 0,
            'radiograph_status' => 'success',
            'gain_status' => 'success',
            'mpips_status' => 'success',
            'dicom_status' => 'success',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $studyId = (string) Str::uuid();
        $label = Str::upper(Str::random(8));
        DB::table('image_gateway_studies')->insert([
            'id' => $studyId,
            'capture_set_id' => $captureId,
            'display_reference' => 'DCM-'.$label,
            'object_key' => (string) $object->key,
            'checksum' => $object->checksum,
            'bytes' => $object->bytes,
            'format' => 'application/dicom',
            'filename' => 'DCM-'.$label.'.dcm',
            'study_instance_uid' => '2.25.'.Uuid::uuid4()->getInteger()->toString(),
            'series_instance_uid' => '2.25.'.Uuid::uuid4()->getInteger()->toString(),
            'sop_instance_uid' => '2.25.'.Uuid::uuid4()->getInteger()->toString(),
            'transfer_syntax' => null,
            'window_center' => null,
            'window_width' => null,
            'rows' => 512,
            'columns' => 512,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $studyId;
    }
}
