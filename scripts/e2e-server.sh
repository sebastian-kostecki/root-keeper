#!/usr/bin/env bash
# Starts the app for Playwright (webServer): its own port and its own database,
# so E2E never touches the dev data. Runs through Sail, in the foreground.
# Uses php -S, not `artisan serve`: artisan serve drops DB_DATABASE and re-reads .env.
set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${E2E_PORT:-8100}"
DB="${E2E_DB_DATABASE:-e2e}"

vendor/bin/sail up -d pgsql
vendor/bin/sail npm run build

vendor/bin/sail exec -T pgsql sh -c "until pg_isready -q -U \"\$POSTGRES_USER\"; do sleep 1; done"
vendor/bin/sail exec -T pgsql sh -c "psql -U \"\$POSTGRES_USER\" -d postgres -tAc \"SELECT 1 FROM pg_database WHERE datname='${DB}'\" | grep -q 1 || createdb -U \"\$POSTGRES_USER\" ${DB}"

PREPARE='php artisan migrate --force --no-interaction'
PREPARE+=" && php artisan tinker --execute '"
PREPARE+='$u = App\Models\User::firstOrCreate(["email" => env("E2E_USERNAME")], ["name" => "E2E User", "password" => env("E2E_PASSWORD"), "email_verified_at" => now()]);'
PREPARE+=' $u->forceFill(["email_verified_at" => $u->email_verified_at ?? now()])->save();'
PREPARE+=' if (! $u->tree()->exists()) { $u->tree()->create(); }'"'"

export WWWUSER="${WWWUSER:-$(id -u)}" WWWGROUP="${WWWGROUP:-$(id -g)}"

NAME="root-keeper-e2e"
docker rm -f "${NAME}" >/dev/null 2>&1 || true
# docker compose run does not stop its container when killed; Playwright sends SIGTERM.
trap 'docker stop "${NAME}" >/dev/null 2>&1 || true' EXIT
trap 'exit 143' TERM INT # bash runs traps only between commands, hence `& wait`

docker compose run --rm -T --name "${NAME}" -p "${PORT}:${PORT}" \
    -e PHP_CLI_SERVER_WORKERS=4 -e "DB_DATABASE=${DB}" -e "APP_URL=http://localhost:${PORT}" \
    laravel.test sh -c "${PREPARE} && cd public && exec php -S 0.0.0.0:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php" &
wait $!
