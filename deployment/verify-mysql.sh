#!/usr/bin/env bash
set -euo pipefail

if ! command -v docker >/dev/null 2>&1; then
  echo 'MySQL verification requires Docker.' >&2
  exit 2
fi

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
suffix="${GITHUB_RUN_ID:-local}-$$"
container="mhcs-mysql-verification-${suffix}"
database="mhcs_verification"
username="mhcs_verification"
root_password="$(php -r 'echo bin2hex(random_bytes(24));')"
password="$(php -r 'echo bin2hex(random_bytes(24));')"

cleanup() {
  docker rm --force "$container" >/dev/null 2>&1 || true
}

trap cleanup EXIT

docker run --detach \
  --name "$container" \
  --publish 127.0.0.1::3306 \
  --env MYSQL_ROOT_PASSWORD="$root_password" \
  --env MYSQL_DATABASE="$database" \
  --env MYSQL_USER="$username" \
  --env MYSQL_PASSWORD="$password" \
  mysql:8.4 >/dev/null

attempt=0
until docker exec "$container" mysqladmin ping -h 127.0.0.1 -u root -p"$root_password" --silent >/dev/null 2>&1; do
  attempt=$((attempt + 1))

  if [ "$attempt" -ge 60 ]; then
    docker logs "$container"
    exit 1
  fi

  sleep 2
done

host_port="$(docker port "$container" 3306/tcp | awk -F: 'NR == 1 { print $NF }')"
test -n "$host_port"

export APP_ENV=testing
export APP_DEBUG=false
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export DB_CONNECTION=mysql
export DB_URL=
export DB_HOST=127.0.0.1
export DB_PORT="$host_port"
export DB_DATABASE="$database"
export DB_USERNAME="$username"
export DB_PASSWORD="$password"
export DB_CHARSET=utf8mb4
export DB_COLLATION=utf8mb4_unicode_ci
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export SESSION_DRIVER=array
export MAIL_MAILER=array

cd "$root"
php artisan migrate:fresh --force
echo 'MySQL 8.4 migrate:fresh passed'
php artisan test tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php --filter=test_mysql_concurrent_first_publications_leave_one_current_version --fail-on-skipped
echo 'MySQL X-ray protocol first-publication concurrency probe passed'
php artisan test tests/Operator/Mvp04OperatorFoundationTest.php --filter=test_eligible_shift_intake_normalizes_post_2038_explicit_offset_projection_instants
echo 'MySQL post-2038 eligible-shift projection regression passed'
php artisan test --testsuite=Member
echo 'MySQL Member identity tests passed'
php artisan test --testsuite=Integration
echo 'MySQL integration and conformance tests passed'
php artisan test
echo 'MySQL full PHP suite passed'
schedule_instant_types() {
  php artisan tinker --execute='echo implode(",", array_map(static fn (string $column): string => \Illuminate\Support\Facades\Schema::getColumnType("shift_schedules", $column), ["starts_at", "ends_at", "eligible_at"]));'
}

operator_schedule_instant_types() {
  php artisan tinker --execute='echo implode(",", array_map(static fn (string $column): string => \Illuminate\Support\Facades\Schema::getColumnType("operator_eligible_shifts", $column), ["schedule_starts_at", "schedule_ends_at"]));'
}

operator_portability_migration='database/migrations/2026_08_09_000002_make_operator_eligible_shift_instants_mysql_portable.php'
operator_portability_probe_id='00000000-0000-4000-8000-000000000002'
operator_portability_probe_event='mysql-portability-negative-rollback'

test "$(operator_schedule_instant_types)" = 'datetime,datetime'
php artisan tinker --execute='\Illuminate\Support\Facades\DB::table("operator_eligible_shifts")->insert(["id" => "00000000-0000-4000-8000-000000000002", "member_schedule_id" => "00000000-0000-4000-8000-000000000003", "operator_site_id" => "mysql-portability-probe", "schedule_starts_at" => "2040-01-10 03:00:00", "schedule_ends_at" => "2040-01-10 04:00:00", "confirmed_count_at_eligibility" => 5, "quota" => 5, "event_version" => 1, "source_event_id" => "mysql-portability-negative-rollback", "eligible_at" => now(), "sync_status" => "eligible", "created_at" => now(), "updated_at" => now()]);'
if operator_rollback_output="$(php artisan migrate:rollback --path="$operator_portability_migration" --force 2>&1)"; then
  echo 'Operator portability rollback unexpectedly accepted a post-2038 projection.' >&2
  exit 1
fi
case "$operator_rollback_output" in
  *'Cannot roll back operator eligible-shift schedule instants while values exceed the MySQL TIMESTAMP range.'*) ;;
  *) printf '%s\n' "$operator_rollback_output" >&2; exit 1 ;;
esac
test "$(operator_schedule_instant_types)" = 'datetime,datetime'
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\DB::table("operator_eligible_shifts")->where("id", "00000000-0000-4000-8000-000000000002")->value("schedule_starts_at");')" = '2040-01-10 03:00:00'
php artisan tinker --execute='\Illuminate\Support\Facades\DB::table("operator_eligible_shifts")->where("id", "00000000-0000-4000-8000-000000000002")->delete();'
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\DB::table("operator_eligible_shifts")->where("id", "00000000-0000-4000-8000-000000000002")->count();')" = '0'
php artisan migrate:rollback --path="$operator_portability_migration" --force
test "$(operator_schedule_instant_types)" = 'timestamp,timestamp'
php artisan migrate --path="$operator_portability_migration" --force
test "$(operator_schedule_instant_types)" = 'datetime,datetime'
echo 'MySQL Operator eligible-shift portability migration rollback and reapplication passed'

test "$(schedule_instant_types)" = 'datetime,datetime,datetime'
php artisan migrate:rollback --path=database/migrations/2026_08_09_000001_make_shift_schedule_instants_mysql_portable.php --force
test "$(schedule_instant_types)" = 'timestamp,timestamp,timestamp'
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("members") ? "present" : "absent";')" = 'present'
php artisan migrate --path=database/migrations/2026_08_09_000001_make_shift_schedule_instants_mysql_portable.php --force
test "$(schedule_instant_types)" = 'datetime,datetime,datetime'
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("members") ? "present" : "absent";')" = 'present'
echo 'MySQL shift schedule portability migration rollback and reapplication passed'
