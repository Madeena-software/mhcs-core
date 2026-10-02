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
        foreach (['operator.study.results', 'operator.verification-worklist', 'operator.basic-examination-worklist', 'operator.xray-readiness-worklist'] as $route) {
            $this->get(route($route).'?q=TEST&date_from=2000-01-01')->assertOk()->assertDontSee('data-row-status=', false);
        }

        // Unauthenticated
        $this->actingAsGuest();
        $this->flushSession();
        $this->get(route('operator.study.dicom', $studyId))->assertRedirect('/login');
        foreach (['operator.study.results', 'operator.verification-worklist', 'operator.basic-examination-worklist', 'operator.xray-readiness-worklist'] as $route) {
            $this->get(route($route))->assertRedirect('/login');
        }
    }

    public function test_verification_selector_matches_all_eligible_service_states_and_preserves_eligibility(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'arrived']);
        $states = ['unclaimed', 'open', 'matched', 'nonclinical_validation', 'mismatch_reported', 'insufficient_evidence', 'cancelled'];
        foreach ($states as $state) {
            $arrival = (string) Str::uuid();
            DB::table('operator_arrivals')->insert([
                'id' => $arrival, 'booking_id' => $fixture['bookingId'], 'member_schedule_id' => $fixture['scheduleId'],
                'operator_site_id' => $fixture['siteLocalId'], 'operator_profile_id' => $fixture['profileId'],
                'occurrence_at' => '2040-01-10 10:15:00', 'recorded_at' => now(), 'operation_id' => (string) Str::uuid(),
                'source' => 'test', 'status' => 'recorded', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($state !== 'unclaimed') {
                DB::table('operator_identity_verifications')->insert([
                    'id' => (string) Str::uuid(), 'arrival_id' => $arrival, 'booking_id' => $fixture['bookingId'],
                    'member_schedule_id' => $fixture['scheduleId'], 'operator_site_id' => $fixture['siteLocalId'],
                    'operator_profile_id' => $fixture['profileId'], 'state' => $state, 'started_at' => now(),
                    'operation_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $response = $this->get(route('operator.verification-worklist'))->assertOk();
        foreach ($states as $state) {
            $response->assertSee('value="'.$state.'"', false)->assertSee('data-row-status="'.$state.'"', false);
        }
        foreach (['verified', 'pending_verification', 'refused'] as $state) {
            $response->assertDontSee('value="'.$state.'"', false);
        }
        // Query parameters cannot broaden the service's existing booking eligibility.
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'checked_in']);
        $this->get(route('operator.verification-worklist').'?status=matched&q=Synthetic')
            ->assertOk()->assertDontSee('data-row-status=', false)->assertSee('data-empty-initial-row', false);
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'arrived']);
        $foreign = $this->operatorFixture(false, '900000000002');
        $this->actingAs($foreign['operator'])->withSession(['operator.active_site_id' => $foreign['siteLocalId']]);
        $this->get(route('operator.verification-worklist').'?status=matched')->assertOk()->assertDontSee('data-row-status=', false);
    }

    public function test_browser_messages_are_registry_substituted_and_png_json_is_attribute_safe(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->createAcceptedStudy($fixture, $this->insertCalledXrayAdmission($fixture));
        $copy = [
            'Memproses...' => 'PROCESS "quoted" <safe>', 'Menyimpan...' => 'SAVE test', 'Selesai' => 'DONE test',
            'Gagal mengunduh gambar PNG. Pastikan berkas studi tersedia.' => 'ERROR test',
            'Menampilkan :visible dari :total' => 'COUNT :visible/:total', 'Total: :total' => 'TOTAL :total',
            'Tanggal mulai harus sebelum atau sama dengan tanggal sampai.' => 'RANGE test',
        ];
        app('translator')->setLoaded(['*' => ['*' => ['id' => array_replace(json_decode(file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR), $copy)]]]);
        $response = $this->get(route('operator.study.results'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $button = $xpath->query('//*[@data-png-download]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame([
            'processing' => $copy['Memproses...'], 'saving' => $copy['Menyimpan...'], 'done' => $copy['Selesai'],
            'error' => $copy['Gagal mengunduh gambar PNG. Pastikan berkas studi tersedia.'],
        ], json_decode($button->getAttribute('data-png-messages'), true, flags: JSON_THROW_ON_ERROR));
        $messages = $xpath->query('//*[@data-operator-list-messages]')->item(0);
        $this->assertNotNull($messages);
        $this->assertSame([
            'filteredCount' => $copy['Menampilkan :visible dari :total'], 'totalCount' => $copy['Total: :total'],
            'invalidRange' => $copy['Tanggal mulai harus sebelum atau sama dengan tanggal sampai.'],
        ], json_decode($messages->textContent, true, flags: JSON_THROW_ON_ERROR));
        foreach (['operator.study.results', 'operator.verification-worklist', 'operator.basic-examination-worklist', 'operator.xray-readiness-worklist'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('data-filter-error role="alert" hidden', false);
        }
    }

    public function test_four_lists_use_configured_timezone_for_display_and_inclusive_filter_date(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $admission = $this->insertCalledXrayAdmission($fixture);
        DB::table('operator_queue_admissions')->where('id', $admission)->update(['ready_at' => '2040-01-09 23:30:00', 'stage' => 'basic_examination']);
        $this->get(route('operator.basic-examination-worklist'))->assertOk()
            ->assertSee('data-row-date="2040-01-10"', false)->assertSee('2040-01-10 06:30:00');
        DB::table('operator_queue_admissions')->where('id', $admission)->update(['stage' => 'xray']);
        $this->get(route('operator.xray-readiness-worklist'))->assertOk()
            ->assertSee('data-row-date="2040-01-10"', false)->assertSee('2040-01-10 06:30:00');
        $study = $this->createAcceptedStudy($fixture, $admission);
        $capture = DB::table('image_gateway_studies')->where('id', $study)->value('capture_set_id');
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['accepted_at' => '2040-01-09 23:30:00']);
        $this->get(route('operator.study.results'))->assertOk()
            ->assertSee('data-row-date="2040-01-10"', false)->assertSee('2040-01-10 06:30:00');
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'arrived']);
        DB::table('operator_arrivals')->insert([
            'id' => (string) Str::uuid(), 'booking_id' => $fixture['bookingId'], 'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'], 'operator_profile_id' => $fixture['profileId'],
            'occurrence_at' => '2040-01-09 23:30:00', 'recorded_at' => now(), 'operation_id' => (string) Str::uuid(),
            'source' => 'test', 'status' => 'recorded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('operator.verification-worklist'))->assertOk()
            ->assertSee('data-row-date="2040-01-10"', false)->assertSee('2040-01-10 06:30:00');
    }

    public function test_operational_status_metadata_matches_eligible_rows_and_filters_cannot_expand_shift_scope(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $basic = $this->insertCalledXrayAdmission($fixture, 'BASIC-0007');
        DB::table('operator_queue_admissions')->where('id', $basic)->update(['stage' => 'basic_examination']);
        foreach (['waiting', 'called', 'in_service'] as $state) {
            DB::table('operator_queue_admissions')->where('id', $basic)->update(['state' => $state]);
            $this->get(route('operator.basic-examination-worklist'))->assertOk()->assertSee('data-row-status="'.$state.'"', false);
        }
        DB::table('operator_queue_admissions')->where('id', $basic)->update(['state' => 'completed']);
        $this->get(route('operator.basic-examination-worklist').'?status=completed')->assertOk()->assertDontSee('data-row-status=', false);
        $xray = $basic;
        DB::table('operator_queue_admissions')->where('id', $xray)->update(['stage' => 'xray']);
        foreach (['waiting', 'called'] as $state) {
            DB::table('operator_queue_admissions')->where('id', $xray)->update(['state' => $state]);
            $this->get(route('operator.xray-readiness-worklist'))->assertOk()->assertSee('data-row-status="'.$state.'"', false);
        }
        $study = $this->createAcceptedStudy($fixture, $xray);
        $capture = DB::table('image_gateway_studies')->where('id', $study)->value('capture_set_id');
        $studyRecord = (array) DB::table('image_gateway_studies')->where('id', $study)->first();
        DB::table('image_gateway_studies')->where('id', $study)->delete();
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['processing_status' => 'failed']);
        DB::table('operator_queue_admissions')->where('id', $xray)->update(['state' => 'awaiting_ai']);
        $this->get(route('operator.xray-readiness-worklist'))->assertOk()->assertSee('data-row-status="dicom_processing_failed"', false);
        DB::table('image_gateway_studies')->insert($studyRecord);
        DB::table('image_gateway_capture_sets')->where('id', $capture)->update(['processing_status' => 'completed']);
        DB::table('operator_queue_admissions')->where('id', $xray)->update(['state' => 'called']);
        DB::table('operator_shift_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['status' => 'cancelled']);
        foreach (['operator.basic-examination-worklist', 'operator.xray-readiness-worklist', 'operator.study.results'] as $route) {
            $this->get(route($route).'?q=0042&status=called&date_from=2000-01-01')->assertOk()->assertDontSee('data-row-status=', false);
        }
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
