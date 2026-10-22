#!/usr/bin/env bash
# Starts the app for Playwright (webServer) in GitHub Actions: native PHP and the
# job's PostgreSQL service, no Sail, no container. Unlike scripts/e2e-server.sh it
# does not build assets, start the database or create it: the workflow has already
# run `composer setup` (vendor/, .env with a key, build) and the service owns the
# database `e2e`. DB_*, APP_URL, E2E_USERNAME and E2E_PASSWORD come from the job env.
# Uses php -S, not `artisan serve`: artisan serve drops DB_DATABASE and re-reads .env.
set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${E2E_PORT:-8100}"

php artisan migrate --force --no-interaction
php artisan tinker --execute '$u = App\Models\User::firstOrCreate(["email" => env("E2E_USERNAME")], ["name" => "E2E User", "password" => env("E2E_PASSWORD"), "email_verified_at" => now()]); $u->forceFill(["email_verified_at" => $u->email_verified_at ?? now()])->save(); if (! $u->tree()->exists()) { $u->tree()->create(); }'

# exec, so Playwright's SIGTERM reaches the server directly.
cd public && PHP_CLI_SERVER_WORKERS=4 exec php -S "127.0.0.1:${PORT}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
