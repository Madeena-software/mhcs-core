<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\RadiologistFixtures;

$app = require __DIR__.'/radiologist-browser-bootstrap.php';
$directory = storage_path('framework/testing/radiologist-browser');
$command = $argv[1] ?? '';
try {
    if ($command === 'seed') {
        if (is_file($directory.'/audit-failure')) {
            unlink($directory.'/audit-failure');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        $f = RadiologistFixtures::create(($argv[2] ?? 'direct') === 'converted');
        file_put_contents($directory.'/fixture.json', json_encode(['study' => $f['study'], 'digest' => hash('sha256', $f['bytes']), 'case' => $f['i']['booking']], JSON_THROW_ON_ERROR));
        echo json_encode(['admin' => $f['admin']->email, 'doctor' => $f['doctor']->email, 'doctorId' => $f['doctor']->id, 'handoff' => $f['handoff']], JSON_THROW_ON_ERROR);
    } elseif ($command === 'expire') {
        DB::table('doctor_workspaces')->update(['expires_at' => now()->subSecond()]);
        echo 'synthetic access expired';
    } elseif ($command === 'audit-fail') {
        file_put_contents($directory.'/audit-failure', 'synthetic');
        echo 'synthetic audit failure enabled';
    } elseif ($command === 'audit-restore') {
        unlink($directory.'/audit-failure');
        echo 'synthetic audit restored';
    } elseif ($command === 'verify') {
        $f = json_decode(file_get_contents($directory.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
        if (DB::table('image_gateway_studies')->where('id', $f['study'])->value('checksum') !== $f['digest']) {
            throw new RuntimeException('Original changed');
        }
        echo json_encode(['reports' => DB::table('doctor_report_versions')->count(), 'finalizations' => DB::table('doctor_finalizations')->count(), 'ai_jobs' => DB::table('image_gateway_ai_jobs')->count(), 'history' => DB::table('doctor_workspace_history')->count()], JSON_THROW_ON_ERROR);
    } else {
        throw new RuntimeException('Unknown synthetic command');
    }

} catch (Throwable $e) {
    fwrite(STDERR, 'Synthetic Radiologist fixture unavailable: '.get_class($e).PHP_EOL);
    exit(1);
}
