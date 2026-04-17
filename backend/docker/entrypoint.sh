#!/bin/sh
# Solar Proposal MVP backend entrypoint.
#
# - Seeds .env from .env.example on first boot (mounted volume may be empty).
# - Generates APP_KEY if missing.
# - Clears cached config so env overrides from docker-compose win.

set -e

cd /app

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear >/dev/null 2>&1 || true

exec "$@"
