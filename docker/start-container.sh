#!/bin/sh
# Runs each time the app's service starts.
#
# - Refuses to start with settings that would be unsafe in production,
#   including a fake speech-to-text or voice driver, or a real one without
#   its key (php artisan speech:check).
# - Creates the SQLite database file on the first start (it lives on the
#   database volume, so it outlives every redeploy).
# - Caches the configuration, routes, events and views for speed (the
#   settings come from Coolify's environment variables, so this runs at
#   start-up, not when the image is built).
# - Brings the database up to date and only then marks the service ready,
#   so a deploy never serves code ahead of its tables: it saves a copy of
#   the database when this version changes it (php artisan
#   backup:before-deploy), runs the migrations, then marks itself ready.
#   nginx waits for that (see the healthcheck in compose.production.yaml).
#   If the copy can't be made, nothing is changed and the service does not
#   start. RUN_MIGRATIONS=false skips both for one deploy;
#   BACKUP_BEFORE_MIGRATIONS=false skips only the copy.
set -eu

: "${APP_KEY:?Set APP_KEY once in Coolify; never regenerate it at start-up}"
: "${APP_URL:?Set the public HTTPS APP_URL}"
: "${DB_DATABASE:?Set the path of the SQLite database file}"

case "${APP_URL}" in
    https://*) ;;
    *) echo 'APP_URL must use HTTPS in this production stack.' >&2; exit 1 ;;
esac

# The volumes start empty on a new server: the voice clips go in
# storage/app/public, the database in its own folder.
mkdir -p storage/app/private storage/app/public "$(dirname "$DB_DATABASE")"

if [ ! -f "$DB_DATABASE" ]; then
    echo "Creating the SQLite database at $DB_DATABASE."
    touch "$DB_DATABASE"
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction
php artisan view:cache --no-interaction

# The fake listener scores every sentence as perfect: a server set up with
# it must not start (see docs/deployment.md, "Speech-to-text and voice").
php artisan speech:check --no-interaction

if [ "${1:-}" = "php-fpm" ]; then
    rm -f /tmp/ready

    case "${RUN_MIGRATIONS:-true}" in
        true)
            case "${BACKUP_BEFORE_MIGRATIONS:-true}" in
                true) php artisan backup:before-deploy --no-interaction ;;
                false) echo 'BACKUP_BEFORE_MIGRATIONS=false: no copy of the database before the migrations.' ;;
                *) echo 'BACKUP_BEFORE_MIGRATIONS must be true or false.' >&2; exit 1 ;;
            esac

            php artisan migrate --force --no-interaction
            ;;
        false) echo 'RUN_MIGRATIONS=false: skipping the database migrations.' ;;
        *) echo 'RUN_MIGRATIONS must be true or false.' >&2; exit 1 ;;
    esac

    touch /tmp/ready
fi

exec docker-php-entrypoint "$@"
