<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RadiographerFixtures;

$app = require __DIR__.'/radiographer-browser-bootstrap.php';
$command = $argv[1] ?? '';
if ($command === 'seed') {
    Artisan::call('migrate:fresh', ['--force' => true]);
    Schema::create('synthetic_clinical_status', function (Blueprint $t): void {
        $t->string('case_id', 36)->primary();
        $t->string('binding', 64);
        $t->string('handoff_id', 36);
        $t->string('status', 24);
        $t->unsignedInteger('revision');
    });
    $f = RadiographerFixtures::create();
    if (($argv[2] ?? '') !== 'direct') {
        $f = RadiographerFixtures::converted($f, ($argv[2] ?? '') === 'supplied');
    }
    file_put_contents(storage_path('framework/testing/radiographer-browser/fixture.json'), json_encode(['admin' => $f['admin']->id, 'worker' => $f['worker']->id, 'reference' => $f['reference'], 'study' => $f['study'], 'case' => $f['i']['booking'], 'digest' => hash('sha256', $f['bytes'])], JSON_THROW_ON_ERROR));
    echo json_encode(['admin' => $f['admin']->email, 'worker' => $f['worker']->email, 'workerId' => $f['worker']->id, 'reference' => $f['reference'], 'facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']], JSON_THROW_ON_ERROR);
} elseif ($command === 'status') {
    $f = json_decode(file_get_contents(storage_path('framework/testing/radiographer-browser/fixture.json')), true, flags: JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($f, $argv): void {
        DB::table('doctor_clinical_guards')->where('case_id', $f['case'])->lockForUpdate()->first();
        $w = DB::table('operator_radiographer_workspaces')->where('study_id', $f['study'])->first();
        DB::table('synthetic_clinical_status')->updateOrInsert(['case_id' => $f['case']], ['binding' => $w->binding, 'handoff_id' => $w->active_handoff, 'status' => $argv[2], 'revision' => 1]);
    });
    echo 'synthetic status updated';
} elseif ($command === 'expire') {
    DB::table('operator_radiographer_workspaces')->update(['expires_at' => now()->subSecond()]);
    echo 'synthetic expiry updated';
} elseif ($command === 'verify') {
    $f = json_decode(file_get_contents(storage_path('framework/testing/radiographer-browser/fixture.json')), true, flags: JSON_THROW_ON_ERROR);
    if (DB::table('image_gateway_studies')->where('id', $f['study'])->value('checksum') !== $f['digest']) {
        throw new RuntimeException('Source changed');
    }
    echo json_encode(['handoffs' => DB::table('operator_radiographer_handoffs')->count(), 'cycles' => DB::table('operator_radiographer_workspaces')->value('cycle'), 'audit' => DB::table('audit_events')->where('action', 'like', 'radiographer-workspace.%')->count()], JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Unknown synthetic fixture command');
}
