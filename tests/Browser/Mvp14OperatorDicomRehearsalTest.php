<?php

declare(strict_types=1);

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

uses(Mvp04Fixtures::class)->in(__FILE__);

beforeEach(function (): void {
    mvp14BrowserPrepareDatabase($this);
    putenv('APP_ENV=testing');
    config([
        'app.env' => 'testing',
        'mhcs.private_object_disk' => 'local',
        'mhcs.security.object_key' => str_repeat('o', 32),
        'mhcs.security.grant_key' => str_repeat('g', 32),
        'mhcs.security.manifest_key' => str_repeat('m', 32),
        'mhcs.security.manifest_key_id' => 'test-key',
        'mhcs.mpips.base_url' => 'http://127.0.0.1:8014',
        'mhcs.mpips.api_key' => 'test-api-key',
        'mhcs.image_policy' => [
            'file_count' => 2,
            'per_file_bytes' => 1048576,
            'total_bytes' => 2097152,
            'decompressed_bytes' => 4194304,
            'max_width' => 4096,
            'max_height' => 4096,
            'field_count' => 32,
            'cpu_seconds' => 5,
            'memory_bytes' => 134217728,
            'execution_seconds' => 30,
            'process_count' => 1,
            'temporary_storage_bytes' => 8388608,
            'accepted_forms' => ['zip-npz'],
            'recovery_window_seconds' => 300,
            'max_attempts' => 1,
        ],
    ]);
    $this->app->instance('env', 'testing');
    Storage::disk('local')->deleteDirectory('objects');
    $this->fixture = $this->operatorFixture(false);
    DB::table('shift_schedules')->where('id', $this->fixture['scheduleId'])->update([
        'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
    ]);
    $this->admission = mvp14InsertCalledXray($this->fixture);
});

it('takes an operator from X-ray capture to an actual Cornerstone viewport and download', function (): void {
    $fixture = $this->fixture;

    $page = visit('/operator/login')
        ->wait(1)
        ->fill('identifier', $fixture['operator']->email)
        ->fill('password', 'password')
        ->press('Masuk')
        ->wait(1)
        ->click('Pilih lokasi yang ditugaskan')
        ->wait(1)
        ->press('Tetapkan lokasi aktif')
        ->wait(1)
        ->navigate('/operator/xray-readiness-worklist/'.$this->admission.'/capture')
        ->wait(1)
        ->assertSee('NPZ radiografi');

    Http::fake(Http::response(
        str_repeat("\0", 128).'DICM'.'browser dicom',
        200,
        [
            'Content-Type' => 'application/dicom',
            'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
            'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
        ],
    ));
    $response = $this->actingAs($fixture['operator'])
        ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
        ->post(route('operator.xray-capture.store', $this->admission), [
            'submission_id' => (string) Str::uuid(),
            'radiograph_npz' => mvp14BrowserFixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => mvp14BrowserFixtureUpload('synthetic-gain-01.npz'),
        ])
        ->assertRedirect();
    $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
    app()->call([new ProcessCaptureSet($captureId), 'handle']);
    $studyId = (string) DB::table('image_gateway_studies')->value('id');
    if ($studyId === '') {
        throw new RuntimeException($response->getStatusCode().' '.json_encode(session()->all()));
    }

    $page
        ->navigate('/operator/studies/'.$studyId)
        ->wait(2)
        ->assertPathIs('/operator/studies/'.$studyId)
        ->assertSee('VOI otomatis')
        ->assertSee('Hanya zoom dan geser')
        ->assertDontSee('Window/Level')
        ->assertDontSee('Contrast')
        ->assertDontSee('Brightness')
        ->assertDontSee('Rotate')
        ->assertDontSee('Annotation')
        ->assertDontSee('Measurement')
        ->assertDontSee('Invert')
        ->assertVisible('[data-testid="dicom-viewport"]');

    $page->page()->waitForFunction('window.__mhcsDicomViewerReady === true');
    $page->assertVisible('[data-testid="dicom-viewport"]');
    $page->click('Unduh DICOM')->wait(1);
    expect($page->attribute('a[download]', 'download'))->toBe('');
});

it('lets a second current-shift operator discover and download the accepted study', function (): void {
    $fixture = $this->fixture;
    $second = $this->secondOperatorFixture($fixture);

    Http::fake(Http::response(
        str_repeat("\0", 128).'DICM'.'browser dicom',
        200,
        [
            'Content-Type' => 'application/dicom',
            'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
            'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
        ],
    ));
    $this->actingAs($fixture['operator'])
        ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
        ->post(route('operator.xray-capture.store', $this->admission), [
            'submission_id' => (string) Str::uuid(),
            'radiograph_npz' => mvp14BrowserFixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => mvp14BrowserFixtureUpload('synthetic-gain-01.npz'),
        ])
        ->assertRedirect();
    $captureId = (string) DB::table('image_gateway_capture_sets')->value('id');
    app()->call([new ProcessCaptureSet($captureId), 'handle']);
    $studyId = (string) DB::table('image_gateway_studies')->value('id');
    $this->actingAsGuest();
    $this->flushSession();

    $page = visit('/operator/login')
        ->wait(1)
        ->fill('identifier', $second['operator']->email)
        ->fill('password', 'password')
        ->press('Masuk')
        ->wait(1)
        ->click('Pilih lokasi yang ditugaskan')
        ->wait(1)
        ->press('Tetapkan lokasi aktif')
        ->wait(1)
        ->click('Hasil DICOM')
        ->wait(1)
        ->assertPathIs('/operator/studies')
        ->assertSee('Daftar kerja hasil DICOM')
        ->assertSee($studyId)
        ->click('Buka studi DICOM')
        ->wait(2)
        ->assertPathIs('/operator/studies/'.$studyId)
        ->assertSee('VOI otomatis')
        ->assertSee('Hanya zoom dan geser')
        ->assertVisible('[data-testid="dicom-viewport"]');

    $page->page()->waitForFunction('window.__mhcsDicomViewerReady === true');
    $page->click('Unduh DICOM')->wait(1);
    expect($page->attribute('a[download]', 'download'))->toBe('');
});

function mvp14BrowserPrepareDatabase(TestCase $test): void
{
    $database = storage_path('framework/testing/mhcs-mvp14-browser.sqlite');
    @unlink($database);
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
    ]);
    putenv('DB_DATABASE='.$database);
    DB::purge('sqlite');
    $test->artisan('migrate:fresh', ['--quiet' => true]);
}

/** @param array<string, mixed> $fixture */
function mvp14InsertCalledXray(array $fixture): string
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
        'ticket_number' => 'BROWSER-XRAY-01',
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
    DB::table('operator_queue_admission_history')->insert([
        'id' => (string) Str::uuid(),
        'operator_queue_admission_id' => $admissionId,
        'operator_profile_id' => $fixture['profileId'],
        'event_type' => 'called',
        'from_state' => 'waiting',
        'to_state' => 'called',
        'operation_id' => (string) Str::uuid(),
        'occurred_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $admissionId;
}

function mvp14BrowserFixtureUpload(string $name): UploadedFile
{
    return new UploadedFile(
        base_path('resources/fixtures/image-gateway/'.$name),
        $name,
        'application/octet-stream',
        null,
        true,
    );
}
