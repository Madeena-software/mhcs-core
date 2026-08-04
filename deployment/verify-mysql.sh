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
php artisan test --testsuite=Member
echo 'MySQL Member identity tests passed'
php artisan test --testsuite=Integration
echo 'MySQL integration and conformance tests passed'
php artisan test
echo 'MySQL full PHP suite passed'
php artisan migrate:rollback --step=1 --force
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("members") ? "present" : "absent";')" = 'absent'
php artisan migrate --force
test "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("members") ? "present" : "absent";')" = 'present'
echo 'MySQL migration rollback and reapplication passed'
