#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

# queue:listen spawns a fresh `php artisan queue:work`. -d flags on the
# parent are not copied, but this env var is. The dev server adds its own
# -d flags because `artisan serve` does not pass the env through.
modules="$(php -m)"
if ! grep -qx 'pdo_sqlite' <<<"$modules" || ! grep -qx 'intl' <<<"$modules"; then
    export PHP_INI_SCAN_DIR=":${PWD}/php-ini"
fi

exec npx concurrently \
    -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
    "php artisan serve" \
    "php artisan queue:listen --tries=1 --timeout=0" \
    "php artisan pail --timeout=0" \
    "npm run dev" \
    --names=server,queue,logs,vite \
    --kill-others
