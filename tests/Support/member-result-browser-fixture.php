<?php

declare(strict_types=1);

use App\Models\User;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ClosedSyntheticClinicalInventory;
use Tests\Support\MemberResultFixtures;
use Tests\Support\RadiographerFixtures;

$app = require __DIR__.'/member-result-browser-bootstrap.php';
$directory = storage_path('framework/testing/radiologist-browser');
$command = $argv[1] ?? '';
try {
    if ($command === 'seed') {
        foreach (['member-inventory.json', 'audit-failure'] as $file) {
            if (is_file($directory.'/'.$file)) {
                unlink($directory.'/'.$file);
            }
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        $f = RadiographerFixtures::create(['00080060' => ['CS', 'DX']]);
        if (($argv[2] ?? 'direct') === 'converted') {
            $f = RadiographerFixtures::converted($f, false, 'female', ['00080060' => ['CS', 'DX']]);
        }
        foreach (['issue', 'reassign', 'revoke', 'credential'] as $action) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $f['admin']->id, 'permission' => 'doctor.radiologist-workspace.'.$action, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('doctor_workspace_managers')->insert(['user_id' => $f['admin']->id, 'site_id' => $f['operatorSiteId'], 'active' => true]);
        $doctor = User::factory()->create(['email' => 'synthetic-result-doctor@example.test']);
        $f = MemberResultFixtures::prepare(base: $f + ['doctor' => $doctor]);
        file_put_contents($directory.'/member-fixture.json', json_encode(['study' => $f['study'], 'capture' => $f['capture'], 'member' => $f['member'], 'i' => $f['i']], JSON_THROW_ON_ERROR));
        echo json_encode(['admin' => $f['admin']->email, 'worker' => $f['worker']->email, 'workerId' => $f['worker']->id, 'doctor' => $doctor->email, 'doctorId' => $doctor->id, 'member' => $f['memberUser']->email, 'reference' => $f['reference'], 'case' => $f['i']['booking'], 'facility' => $f['siteId'], 'date' => $f['date'], 'session' => $f['scheduleId']], JSON_THROW_ON_ERROR);
    } elseif ($command === 'handoff') {
        $h = DB::table('operator_radiographer_handoffs')->first();
        if (! $h) {
            throw new RuntimeException;
        }
        echo json_encode(['handoff' => $h->id], JSON_THROW_ON_ERROR);
    } elseif ($command === 'seal') {
        $f = json_decode(file_get_contents($directory.'/member-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
        $h = DB::table('operator_radiographer_handoffs')->first();
        $s = DB::table('image_gateway_studies')->where('id', $f['study'])->first();
        $f += ['handoff' => $h->id, 'binding' => $h->binding, 'bytes' => Storage::disk(config('mhcs.private_object_disk'))->get($s->object_key)];
        $inventory = ClosedSyntheticClinicalInventory::seal($f);
        file_put_contents($directory.'/member-inventory.json', json_encode($inventory->export(), JSON_THROW_ON_ERROR));
        echo json_encode(['finalizations' => DB::table('doctor_finalizations')->count(), 'publications' => DB::table('member_result_publications')->count()], JSON_THROW_ON_ERROR);
    } elseif ($command === 'expire-profession') {
        $table = match ($argv[2] ?? '') {
            'radiographer' => 'operator_radiographer_workspaces',
            'radiologist' => 'doctor_workspaces',
            default => throw new RuntimeException,
        };
        DB::table($table)->update(['expires_at' => app(Clock::class)->now()->modify('-1 second')]);
        echo 'Synthetic professional entitlement expired';
    } elseif ($command === 'expire') {
        DB::table('member_result_publications')->update(['expires_at' => app(Clock::class)->now()->modify('-1 second')]);
        echo 'Synthetic publication expired';
    } elseif ($command === 'identity-deny' || $command === 'identity-restore') {
        DB::table('members')->update(['identity_status' => $command === 'identity-deny' ? 'pending_verification' : 'verified']);
        echo 'Synthetic identity changed';
    } elseif (in_array($command, ['lineage-deny', 'lineage-restore'], true)) {
        DB::table('doctor_clinical_guards')->update(['status' => $command === 'lineage-deny' ? 'unknown' : 'finalized']);
        echo 'Synthetic lineage withheld';
    } elseif ($command === 'audit-fail') {
        file_put_contents($directory.'/audit-failure', 'synthetic');
        echo 'Synthetic audit failure enabled';
    } elseif ($command === 'audit-restore') {
        unlink($directory.'/audit-failure');
        echo 'Synthetic audit restored';
    } elseif ($command === 'verify') {
        $audit = json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR);
        foreach (['Synthetic browser findings', 'signature=', 'SYNTHETIC-MRN'] as $sentinel) {
            if (str_contains($audit, $sentinel)) {
                throw new RuntimeException;
            }
        }
        echo json_encode(['finalizations' => DB::table('doctor_finalizations')->count(), 'publications' => DB::table('member_result_publications')->count(), 'history' => DB::table('member_result_history')->count(), 'ai' => DB::table('image_gateway_ai_jobs')->count()], JSON_THROW_ON_ERROR);
    } else {
        throw new RuntimeException;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Synthetic result fixture unavailable: '.get_class($e).PHP_EOL);
    exit(1);
}
