#!/bin/sh
# Solar Proposal MVP backend entrypoint.
#
# - Seeds .env from .env.example if present, otherwise from a built-in
#   minimal template (handles a bad checkout that drops the example file).
# - Generates APP_KEY if missing.
# - Clears cached config so env overrides from docker-compose win.

set -e

cd /app

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        cat > .env <<'ENVEOF'
APP_NAME="Solar Proposal"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
SESSION_DRIVER=array
CACHE_STORE=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
MAIL_MAILER=log
FILESYSTEM_DISK=local
BCRYPT_ROUNDS=12
FAKE_PROVIDERS=true
GOOGLE_MAPS_API_KEY=
GOOGLE_SOLAR_API_KEY=
GEMINI_API_KEY=
FRONTEND_URL=http://localhost:5173
ENVEOF
    fi
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear >/dev/null 2>&1 || true

exec "$@"
