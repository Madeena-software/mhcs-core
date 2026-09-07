<?php

declare(strict_types=1);

use App\Http\Controllers\Operator\PortalController;
use App\Models\User;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Shared\Authorization\AuthorizationClaimResolver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "\n=======================================================\n";
echo "=== OPERATOR ELIGIBLE SHIFTS PRODUCTION DIAGNOSTICS ===\n";
echo "=======================================================\n";

echo "\n--- 1. RUNTIME, ENVIRONMENT & CACHES ---\n";
echo "laravel_environment=" . $app->environment() . "\n";
echo "php_version=" . PHP_VERSION . "\n";
echo "laravel_framework_version=" . $app->version() . "\n";
try {
    $mysqlVer = DB::select("SELECT VERSION() as v")[0]->v ?? 'unknown';
    echo "mysql_server_version=" . $mysqlVer . "\n";
    $sqlModes = DB::select("SELECT @@SESSION.sql_mode as sm, @@GLOBAL.sql_mode as gm")[0] ?? null;
    if ($sqlModes) {
        echo "mysql_session_sql_mode=" . $sqlModes->sm . "\n";
        echo "mysql_global_sql_mode=" . $sqlModes->gm . "\n";
    }
} catch (Throwable $e) {
    echo "mysql_server_version_error=" . get_class($e) . "\n";
}

echo "config_cached=" . (file_exists(base_path('bootstrap/cache/config.php')) ? 'true' : 'false') . "\n";
echo "routes_cached=" . (file_exists(base_path('bootstrap/cache/routes-v7.php')) ? 'true' : 'false') . "\n";
echo "events_cached=" . (file_exists(base_path('bootstrap/cache/events.php')) ? 'true' : 'false') . "\n";
$compiledViews = glob(storage_path('framework/views/*.php')) ?: [];
echo "views_cached_count=" . count($compiledViews) . "\n";

echo "\n--- 2. ENVIRONMENT VARIABLE NAMES (PRESENCE ONLY) ---\n";
$envKeys = array_unique(array_merge(array_keys($_ENV), array_keys($_SERVER)));
sort($envKeys);
foreach ($envKeys as $key) {
    if (str_starts_with($key, 'PHP_') || str_starts_with($key, 'APP_') || str_starts_with($key, 'DB_') || str_starts_with($key, 'MHCS_') || str_starts_with($key, 'AI_PACS_') || str_starts_with($key, 'QUEUE_') || str_starts_with($key, 'CACHE_') || str_starts_with($key, 'SESSION_')) {
        echo "ENV_PRESENT: " . $key . "\n";
    }
}

echo "\n--- 3. RECENT LARAVEL LOG ERRORS (LAST 10 BLOCKS) ---\n";
$logPath = storage_path('logs/laravel.log');
if (!file_exists($logPath)) {
    echo "laravel.log does not exist\n";
} else {
    $lines = file($logPath);
    $totalLines = count($lines);
    echo "Total log lines: " . $totalLines . "\n";

    $errorBlocks = [];
    $currentBlock = [];
    for ($i = $totalLines - 1; $i >= 0 && count($errorBlocks) < 10; $i--) {
        $line = $lines[$i];
        if (preg_match('/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $line)) {
            $currentBlock[] = $line;
            $blockText = implode('', array_reverse($currentBlock));
            if (str_contains($blockText, 'ERROR') || str_contains($blockText, 'Exception') || str_contains($blockText, 'eligible-shifts') || str_contains($blockText, 'PortalController')) {
                $errorBlocks[] = $blockText;
            }
            $currentBlock = [];
        } else {
            $currentBlock[] = $line;
        }
    }

    echo "Found " . count($errorBlocks) . " recent relevant error blocks.\n";
    foreach ($errorBlocks as $idx => $block) {
        echo "\n--- Error Block #" . ($idx + 1) . " ---\n";
        $sanitized = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $block);
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $sanitized);
        $sanitized = preg_replace('/(password|token|key|secret)=([^\s&]+)/i', '$1=[REDACTED]', $sanitized);
        $blockLines = explode("\n", $sanitized);
        echo implode("\n", array_slice($blockLines, 0, 30)) . "\n";
    }
}

echo "\n--- 4. SIMULATED OPERATOR ELIGIBLE SHIFTS INVOCATION ---\n";
$profiles = OperatorProfile::where('active', true)->get();
echo "Active Operator Profiles in Production: " . $profiles->count() . "\n";

$claimResolver = $app->make(AuthorizationClaimResolver::class);
$controller = $app->make(PortalController::class);
$auth = $app->make(OperatorAuthorization::class);
$assign = $app->make(OperatorShiftAssignmentService::class);

$counter = 0;
foreach ($profiles as $profile) {
    $counter++;
    echo "\n--- Operator Profile #{$counter} ---\n";
    $user = User::find($profile->user_id);
    if (!$user) {
        echo "user_exists=false\n";
        continue;
    }
    echo "user_can_authenticate=" . ($user->canAuthenticate() ? 'true' : 'false') . "\n";
    echo "user_account_status=" . $user->account_status . "\n";
    echo "user_login_enabled=" . ($user->login_enabled ? 'true' : 'false') . "\n";

    $roles = $claimResolver->roles($user);
    $perms = $claimResolver->permissions($user);
    echo "has_role_operator=" . (in_array(OperatorAuthorization::ROLE, $roles, true) ? 'true' : 'false') . "\n";
    echo "has_perm_portal_access=" . (in_array(OperatorAuthorization::PORTAL_ACCESS, $perms, true) ? 'true' : 'false') . "\n";

    $siteAssignments = DB::table('operator_site_assignments')
        ->where('operator_profile_id', $profile->id)
        ->where('active', true)
        ->get();
    echo "active_site_assignments_count=" . $siteAssignments->count() . "\n";

    $activeShifts = DB::table('operator_shift_assignments')
        ->where('operator_profile_id', $profile->id)
        ->where('status', 'active')
        ->count();
    echo "active_shift_assignments_count=" . $activeShifts . "\n";

    foreach ($siteAssignments as $sa) {
        $site = OperatorSite::find($sa->operator_site_id);
        if (!$site) {
            echo "assigned_site_exists=false\n";
            continue;
        }
        echo "site_active=" . ($site->active ? 'true' : 'false') . "\n";

        Auth::login($user);
        session()->put('operator.active_site_id', (string)$site->getKey());
        session()->save();
        View::share('errors', new ViewErrorBag);

        echo "Calling PortalController::eligible()...\n";
        try {
            $resp = $controller->eligible($auth, $assign);
            if ($resp instanceof \Illuminate\View\View) {
                echo "eligible_result=ViewReturned\n";
                echo "View name: " . $resp->name() . "\n";
                $shiftsData = $resp->getData()['shifts'] ?? [];
                echo "Shifts count: " . count($shiftsData) . "\n";
                foreach ($shiftsData as $sIdx => $s) {
                    echo "  Shift #$sIdx: starts_at type=" . gettype($s->schedule_starts_at) . ", class=" . (is_object($s->schedule_starts_at) ? get_class($s->schedule_starts_at) : 'not_object') . ", raw_val=" . json_encode($s->getAttributes()['schedule_starts_at'] ?? null) . "\n";
                }
                echo "Rendering Blade template...\n";
                $html = $resp->render();
                echo "render_result=SUCCESS (length: " . strlen($html) . " bytes)\n";
            } elseif ($resp instanceof \Illuminate\Http\RedirectResponse) {
                echo "eligible_result=RedirectResponse to " . $resp->getTargetUrl() . "\n";
            } else {
                echo "eligible_result=" . get_class($resp) . "\n";
            }
        } catch (Throwable $e) {
            echo "CAPTURED_EXCEPTION=" . get_class($e) . "\n";
            echo "EXCEPTION_CODE=" . $e->getCode() . "\n";
            if ($e instanceof \PDOException || $e instanceof \Illuminate\Database\QueryException) {
                echo "SQLSTATE=" . ($e->errorInfo[0] ?? 'unknown') . "\n";
                echo "DB_ERROR_CODE=" . ($e->errorInfo[1] ?? 'unknown') . "\n";
            }
            $sanitizedMsg = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $e->getMessage());
            echo "EXCEPTION_MESSAGE=" . $sanitizedMsg . "\n";
            echo "EXCEPTION_FILE=" . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "STACK_TRACE:\n";
            $traceLines = explode("\n", $e->getTraceAsString());
            foreach (array_slice($traceLines, 0, 20) as $tl) {
                echo "  " . $tl . "\n";
            }
        }
    }
}

echo "\n--- 5. PRODUCTION SCHEMA METADATA ---\n";
$tables = [
    'users', 'operator_profiles', 'operator_sites', 'operator_site_assignments',
    'operator_eligible_shifts', 'operator_shift_assignments', 'shift_schedules',
    'bookings', 'authorization_role_assignments', 'authorization_permission_assignments'
];

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        echo "TABLE $table: MISSING\n";
        continue;
    }
    $columns = DB::select("SHOW FULL COLUMNS FROM `$table`");
    echo "TABLE $table (" . count($columns) . " columns):\n";
    foreach ($columns as $c) {
        echo "  col: {$c->Field} | type: {$c->Type} | collation: {$c->Collation} | null: {$c->Null} | key: {$c->Key} | default: " . ($c->Default === null ? 'NULL' : $c->Default) . " | extra: {$c->Extra}\n";
    }
    $indexes = DB::select("SHOW INDEX FROM `$table`");
    echo "  indexes:\n";
    foreach ($indexes as $idx) {
        echo "    {$idx->Key_name} (unique: " . ($idx->Non_unique == 0 ? 'yes' : 'no') . ", col: {$idx->Column_name})\n";
    }
}

echo "\n--- 6. SAFE AGGREGATE DATA-SHAPE FINDINGS ---\n";
echo "-- Row counts --\n";
foreach ($tables as $t) {
    echo "$t: " . DB::table($t)->count() . "\n";
}

echo "\n-- Operator eligible shifts status distribution --\n";
$syncStatuses = DB::table('operator_eligible_shifts')->select('sync_status', DB::raw('count(*) as c'))->groupBy('sync_status')->get();
foreach ($syncStatuses as $s) {
    echo "sync_status={$s->sync_status}: {$s->c}\n";
}

echo "\n-- Shift schedules status distribution --\n";
$schedStatuses = DB::table('shift_schedules')->select('status', DB::raw('count(*) as c'))->groupBy('status')->get();
foreach ($schedStatuses as $s) {
    echo "schedule_status={$s->status}: {$s->c}\n";
}

echo "\n-- Operator shift assignments status distribution --\n";
$assignStatuses = DB::table('operator_shift_assignments')->select('status', DB::raw('count(*) as c'))->groupBy('status')->get();
foreach ($assignStatuses as $s) {
    echo "assignment_status={$s->status}: {$s->c}\n";
}

echo "\n-- Null checks on key columns --\n";
echo "operator_eligible_shifts.schedule_starts_at IS NULL: " . DB::table('operator_eligible_shifts')->whereNull('schedule_starts_at')->count() . "\n";
echo "operator_eligible_shifts.schedule_ends_at IS NULL: " . DB::table('operator_eligible_shifts')->whereNull('schedule_ends_at')->count() . "\n";
echo "operator_eligible_shifts.member_schedule_id IS NULL: " . DB::table('operator_eligible_shifts')->whereNull('member_schedule_id')->count() . "\n";
echo "shift_schedules.starts_at IS NULL: " . DB::table('shift_schedules')->whereNull('starts_at')->count() . "\n";
echo "shift_schedules.ends_at IS NULL: " . DB::table('shift_schedules')->whereNull('ends_at')->count() . "\n";
echo "shift_schedules.display_reference IS NULL: " . DB::table('shift_schedules')->whereNull('display_reference')->count() . "\n";
echo "shift_schedules.quota IS NULL: " . DB::table('shift_schedules')->whereNull('quota')->count() . "\n";

echo "\n-- Timestamps range --\n";
$minStartsAt = DB::table('operator_eligible_shifts')->min('schedule_starts_at');
$maxStartsAt = DB::table('operator_eligible_shifts')->max('schedule_starts_at');
echo "operator_eligible_shifts.schedule_starts_at min: " . ($minStartsAt ?? 'none') . " | max: " . ($maxStartsAt ?? 'none') . "\n";

echo "\n-- Orphaned references --\n";
$orphanedAssignments = DB::table('operator_shift_assignments')
    ->leftJoin('operator_eligible_shifts', 'operator_eligible_shifts.id', '=', 'operator_shift_assignments.operator_eligible_shift_id')
    ->whereNull('operator_eligible_shifts.id')
    ->count();
echo "orphaned_shift_assignments_missing_eligible_shift=" . $orphanedAssignments . "\n";

$orphanedAssignmentsProfile = DB::table('operator_shift_assignments')
    ->leftJoin('operator_profiles', 'operator_profiles.id', '=', 'operator_shift_assignments.operator_profile_id')
    ->whereNull('operator_profiles.id')
    ->count();
echo "orphaned_shift_assignments_missing_profile=" . $orphanedAssignmentsProfile . "\n";

$orphanedEligibleSchedule = DB::table('operator_eligible_shifts')
    ->leftJoin('shift_schedules', 'shift_schedules.id', '=', 'operator_eligible_shifts.member_schedule_id')
    ->whereNull('shift_schedules.id')
    ->count();
echo "orphaned_eligible_shifts_missing_schedule=" . $orphanedEligibleSchedule . "\n";

$orphanedEligibleSite = DB::table('operator_eligible_shifts')
    ->leftJoin('operator_sites', 'operator_sites.operator_site_id', '=', 'operator_eligible_shifts.operator_site_id')
    ->whereNull('operator_sites.id')
    ->count();
echo "orphaned_eligible_shifts_missing_site=" . $orphanedEligibleSite . "\n";

echo "\n-- Direct join query from OperatorShiftAssignmentService::assignedToCurrentOperator --\n";
$participatingStatuses = ['pending', 'confirmed', 'attended', 'completed'];
$queryRows = DB::table('operator_eligible_shifts')
    ->join('operator_shift_assignments', 'operator_shift_assignments.operator_eligible_shift_id', '=', 'operator_eligible_shifts.id')
    ->join('shift_schedules', 'shift_schedules.id', '=', 'operator_eligible_shifts.member_schedule_id')
    ->select('operator_eligible_shifts.*')
    ->addSelect('shift_schedules.display_reference as schedule_display_reference')
    ->addSelect('shift_schedules.quota as schedule_quota')
    ->addSelect(['current_confirmed_count' => DB::table('bookings')
        ->selectRaw('count(*)')
        ->whereColumn('bookings.shift_schedule_id', 'operator_eligible_shifts.member_schedule_id')
        ->whereIn('bookings.status', $participatingStatuses)])
    ->get();

echo "Direct join query matched rows count: " . $queryRows->count() . "\n";
$rIdx = 0;
foreach ($queryRows as $row) {
    $rIdx++;
    echo "  Row #$rIdx: sync_status={$row->sync_status} | display_reference={$row->schedule_display_reference} | quota={$row->schedule_quota} | current_confirmed={$row->current_confirmed_count} | starts_at={$row->schedule_starts_at} (type: " . gettype($row->schedule_starts_at) . ")\n";
}
