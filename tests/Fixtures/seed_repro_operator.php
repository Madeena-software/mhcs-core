<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// When invoked under PHPUnit/testing environment, the process environment may have DB_CONNECTION=sqlite.
// In that case, or when connection is not mysql, load connection parameters from the local .env file.
$envFile = __DIR__ . '/../../.env';
$localEnv = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (str_contains($trimmed, '=')) {
            [$k, $v] = explode('=', $trimmed, 2);
            $localEnv[trim($k)] = trim($v);
        }
    }
}

$dbConnection = getenv('REPRO_DB_CONNECTION') ?: ($localEnv['DB_CONNECTION'] ?? 'mysql');
$dbHost = getenv('REPRO_DB_HOST') ?: ($localEnv['DB_HOST'] ?? '127.0.0.1');
$dbPort = (int) (getenv('REPRO_DB_PORT') ?: ($localEnv['DB_PORT'] ?? 3306));
$dbDatabase = getenv('REPRO_DB_DATABASE') ?: ($localEnv['DB_DATABASE'] ?? 'mhcs_core');
$dbUsername = getenv('REPRO_DB_USERNAME') ?: ($localEnv['DB_USERNAME'] ?? null);
$dbPassword = getenv('REPRO_DB_PASSWORD') ?: ($localEnv['DB_PASSWORD'] ?? null);

if (empty($dbUsername)) {
    fwrite(STDERR, "Error: Local database username not found in environment or .env file.\n");
    exit(1);
}

config([
    'database.default' => $dbConnection,
    "database.connections.{$dbConnection}.host" => $dbHost,
    "database.connections.{$dbConnection}.port" => $dbPort,
    "database.connections.{$dbConnection}.database" => $dbDatabase,
    "database.connections.{$dbConnection}.username" => $dbUsername,
    "database.connections.{$dbConnection}.password" => $dbPassword,
]);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$uniqueSuffix = Str::lower(Str::random(8));
$email = 'synthetic-operator-repro-' . $uniqueSuffix . '@example.test';
$password = 'SyntheticReproPass123!';

$operator = User::factory()->create([
    'email' => $email,
    'password' => Hash::make($password),
]);

$now = now();
$siteLocalId = (string) Str::uuid();
$siteStableId = 'site-repro-' . Str::lower(Str::random(6));
$organizationLocalId = (string) Str::uuid();
$organizationStableId = 'org-repro-' . Str::lower(Str::random(6));
$siteReferenceId = (string) Str::uuid();
$serviceId = (string) Str::uuid();
$scheduleId = (string) Str::uuid();
$profileId = (string) Str::uuid();
$eligibleId = (string) Str::uuid();
$siteStart = '2040-01-10 03:00:00';
$siteEnd = '2040-01-10 04:00:00';

DB::table('operator_organization_refs')->insert([
    'id' => $organizationLocalId,
    'operator_organization_id' => $organizationStableId,
    'name' => 'Synthetic Reproduction Organization',
    'source_version' => '1',
    'active' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('examination_site_refs')->insert([
    'id' => $siteReferenceId,
    'operator_site_id' => $siteStableId,
    'operator_organization_ref_id' => $organizationLocalId,
    'code' => 'SITE-REPRO-' . substr($siteLocalId, 0, 6),
    'display_name' => 'Synthetic Reproduction Site',
    'timezone' => 'Asia/Jakarta',
    'source_version' => '1',
    'active' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('operator_sites')->insert([
    'id' => $siteLocalId,
    'operator_site_id' => $siteStableId,
    'organization_id' => $organizationStableId,
    'organization_name' => 'Synthetic Reproduction Organization',
    'code' => 'SITE-REPRO-' . substr($siteLocalId, 0, 6),
    'display_name' => 'Synthetic Reproduction Site',
    'address_line' => null,
    'timezone' => 'Asia/Jakarta',
    'active' => true,
    'source_version' => '1',
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('service_offerings')->insert([
    'id' => $serviceId,
    'code' => 'RAD-REPRO-' . substr($serviceId, 0, 6),
    'name' => 'Synthetic Radiography',
    'includes_ai' => true,
    'includes_doctor' => false,
    'point_price' => '2.5000',
    'active' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('shift_schedules')->insert([
    'id' => $scheduleId,
    'display_reference' => 'JAD-' . Str::upper(Str::random(8)),
    'examination_site_id' => $siteReferenceId,
    'service_offering_id' => $serviceId,
    'starts_at' => $siteStart,
    'ends_at' => $siteEnd,
    'quota' => 5,
    'status' => 'open',
    'eligible_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('operator_profiles')->insert([
    'id' => $profileId,
    'user_id' => $operator->id,
    'display_name' => 'Synthetic Repro Operator',
    'employee_code' => 'OPR-REPRO-' . substr($profileId, 0, 6),
    'active' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('operator_site_assignments')->insert([
    'id' => (string) Str::uuid(),
    'operator_profile_id' => $profileId,
    'operator_site_id' => $siteLocalId,
    'active' => true,
    'assigned_by_user_id' => $operator->id,
    'assigned_at' => $now,
    'revoked_at' => null,
    'reason' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('operator_eligible_shifts')->insert([
    'id' => $eligibleId,
    'member_schedule_id' => $scheduleId,
    'operator_site_id' => $siteStableId,
    'schedule_starts_at' => $siteStart,
    'schedule_ends_at' => $siteEnd,
    'confirmed_count_at_eligibility' => 1,
    'quota' => 5,
    'event_version' => 1,
    'source_event_id' => 'test:repro:' . $scheduleId,
    'eligible_at' => $now,
    'sync_status' => 'eligible',
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table('operator_shift_assignments')->insert([
    'id' => (string) Str::uuid(),
    'operator_eligible_shift_id' => $eligibleId,
    'operator_profile_id' => $profileId,
    'assigned_by_user_id' => $operator->id,
    'status' => 'active',
    'assigned_at' => $now,
    'revoked_at' => null,
    'reason' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);

// Roles and permissions
DB::table('authorization_role_assignments')->insert([
    'id' => (string) Str::uuid(),
    'user_id' => $operator->id,
    'role' => 'operator',
    'assigned_by_user_id' => null,
    'active' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);

$permissions = [
    'operator.portal.access',
    'operator.site.read',
    'operator.assignment.read',
    'operator.shift.read',
    'operator.attendance.read',
    'operator.arrival.record',
    'operator.audit.read',
    'operator.site.manage',
    'operator.assignment.manage',
    'operator.shift.manage',
];

foreach ($permissions as $permission) {
    DB::table('authorization_permission_assignments')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $operator->id,
        'permission' => $permission,
        'assigned_by_user_id' => null,
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

echo json_encode([
    'email' => $email,
    'password' => $password,
    'site_id' => $siteLocalId,
    'operator_id' => $operator->id,
]);
