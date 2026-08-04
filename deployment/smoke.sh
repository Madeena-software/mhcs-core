#!/usr/bin/env bash
set -euo pipefail

image="${1:?validation image tag required}"
suffix="${GITHUB_RUN_ID:-local}-$$"
network="mhcs-validation-${suffix}"
database_container="mhcs-validation-db-${suffix}"
app_container="mhcs-validation-app-${suffix}"
database_volume="mhcs-validation-db-${suffix}"
storage_volume="mhcs-validation-storage-${suffix}"
cache_volume="mhcs-validation-cache-${suffix}"
public_volume="mhcs-validation-public-${suffix}"

cleanup() {
  docker rm -f "$app_container" "$database_container" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  docker volume rm "$database_volume" "$storage_volume" "$cache_volume" "$public_volume" >/dev/null 2>&1 || true
}

trap cleanup EXIT

docker network create "$network" >/dev/null
docker volume create "$database_volume" >/dev/null
docker volume create "$storage_volume" >/dev/null
docker volume create "$cache_volume" >/dev/null
docker volume create "$public_volume" >/dev/null

docker run --detach \
  --name "$database_container" \
  --network "$network" \
  --network-alias db \
  --mount "type=volume,source=$database_volume,destination=/var/lib/mysql" \
  --env MYSQL_ROOT_PASSWORD=validation-root \
  --env MYSQL_DATABASE=validation \
  --env MYSQL_USER=validation \
  --env MYSQL_PASSWORD=validation-password \
  mysql:8.4 >/dev/null

attempt=0
until docker exec "$database_container" mysqladmin ping -h 127.0.0.1 -u root -pvalidation-root --silent >/dev/null 2>&1; do
  attempt=$((attempt + 1))

  if [ "$attempt" -ge 60 ]; then
    docker logs "$database_container"
    exit 1
  fi

  sleep 2
done

docker run --detach \
  --name "$app_container" \
  --network "$network" \
  --read-only \
  --tmpfs /tmp:size=64M \
  --mount "type=volume,source=$storage_volume,destination=/var/www/html/storage" \
  --mount "type=volume,source=$cache_volume,destination=/var/www/html/bootstrap/cache" \
  --mount "type=volume,source=$public_volume,destination=/var/www/public-files" \
  --env APP_ENV=testing \
  --env APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  --env APP_DEBUG=false \
  --env LOG_CHANNEL=stderr \
  --env DB_CONNECTION=mysql \
  --env DB_HOST=db \
  --env DB_PORT=3306 \
  --env DB_DATABASE=validation \
  --env DB_USERNAME=validation \
  --env DB_PASSWORD=validation-password \
  --env SESSION_DRIVER=database \
  --env CACHE_STORE=array \
  --env QUEUE_CONNECTION=sync \
  --env FILESYSTEM_DISK=local \
  "$image" >/dev/null

attempt=0
while :; do
  health="$(docker inspect --format '{{.State.Health.Status}}' "$app_container" 2>/dev/null || true)"

  case "$health" in
    healthy)
      break
      ;;
    unhealthy)
      docker logs "$app_container"
      exit 1
      ;;
  esac

  attempt=$((attempt + 1))

  if [ "$attempt" -ge 180 ]; then
    docker logs "$app_container"
    exit 1
  fi

  sleep 1
done

docker exec "$app_container" php -r 'exit(is_file("bootstrap/cache/config.php") && is_dir("bootstrap/cache") && is_writable("bootstrap/cache") ? 0 : 1);'
echo 'isolated application startup and health check passed'
