<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RadiologistFixtures
{
    /** Explicit isolated command fixture; upstream handoff/Gateway verification is not exercised. */
    public static function commands(): array
    {
        $f = RadiographerFixtures::create(['00080060' => ['CS', 'DX']]);
        Auth::login($f['admin']);
        $w = app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
        $handoff = (string) Str::uuid();
        $binding = str_repeat('b', 64);
        $protocol = (array) DB::table('operator_workspace_protocols')->where('study_id', $f['study'])->first();
        $snapshot = ['protocol' => $protocol, 'actual' => ['modality' => 'DX', 'patientId' => 'SYNTHETIC-MRN', 'patientName' => 'SYNTHETIC^ONLY']];
        DB::table('operator_radiographer_handoffs')->insert(['id' => $handoff, 'workspace_id' => $w['id'], 'cycle' => 1, 'actor_id' => $f['worker']->id, 'idempotency_key' => 'synthetic-command-fixture', 'payload_digest' => str_repeat('a', 64), 'binding' => $binding, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
        DB::table('operator_radiographer_workspaces')->where('id', $w['id'])->update(['active_handoff' => $handoff, 'binding' => $binding]);
        foreach (['issue', 'reassign', 'revoke', 'credential'] as $action) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $f['admin']->id, 'permission' => 'doctor.radiologist-workspace.'.$action, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('doctor_workspace_managers')->insert(['user_id' => $f['admin']->id, 'site_id' => $f['operatorSiteId'], 'active' => true]);
        $doctor = User::factory()->create(['email' => 'synthetic-radiologist-'.Str::lower(Str::random(6)).'@example.test']);

        return $f + ['doctor' => $doctor, 'handoff' => $handoff, 'binding' => $binding, 'radiographer_workspace' => $w['id']];
    }

    public static function create(bool $converted = false): array
    {
        $f = RadiographerFixtures::create(['00080060' => ['CS', 'DX']]);
        if ($converted) {
            $f = RadiographerFixtures::converted($f, false, 'female', ['00080060' => ['CS', 'DX']]);
        }
        Auth::login($f['admin']);
        $s = app(RadiographerWorkspace::class);
        $w = $s->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
        Auth::login($f['worker']);
        $r = $s->read($w['id'], 1);
        $s->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $h = $s->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'synthetic-handoff'], 1);
        foreach (['issue', 'reassign', 'revoke', 'credential'] as $a) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $f['admin']->id, 'permission' => 'doctor.radiologist-workspace.'.$a, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('doctor_workspace_managers')->insert(['user_id' => $f['admin']->id, 'site_id' => $f['operatorSiteId'], 'active' => true]);
        $doctor = User::factory()->create(['email' => 'synthetic-radiologist-'.Str::lower(Str::random(6)).'@example.test']);

        return $f + ['doctor' => $doctor, 'handoff' => $h['handoff'], 'binding' => $r['binding'], 'radiographer_workspace' => $w['id']];
    }
}
