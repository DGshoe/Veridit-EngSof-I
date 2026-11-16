#!/bin/sh
# Production entrypoint: cache config/routes with the container's environment
# (.env.production via env_file) before starting the main process.
set -e

php artisan optimize --no-interaction >/dev/null

exec "$@"
