#!/usr/bin/env bash
set -euo pipefail

SKIP_NPM=0
SKIP_COMPOSER=0
SKIP_MIGRATE=0
for arg in "$@"; do
  case "$arg" in
    --skip-npm) SKIP_NPM=1 ;;
    --skip-composer) SKIP_COMPOSER=1 ;;
    --skip-migrate) SKIP_MIGRATE=1 ;;
  esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

env_value() {
  local key="$1"
  [ -f .env ] || return 0
  grep -E "^${key}=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

[ -f .env ] || { echo 'Missing .env. Create it from .env.production.example first.' >&2; exit 1; }
[ "$(env_value APP_ENV)" = production ] || { echo 'APP_ENV must be production.' >&2; exit 1; }
case "$(env_value APP_DEBUG)" in false|0) ;; *) echo 'APP_DEBUG must be false.' >&2; exit 1 ;; esac
[ -n "$(env_value APP_KEY)" ] || { echo 'APP_KEY is empty.' >&2; exit 1; }

command -v php >/dev/null
command -v composer >/dev/null
[ "$SKIP_NPM" -eq 1 ] || command -v npm >/dev/null

maintenance=0
cleanup() {
  if [ "$maintenance" -eq 1 ]; then php artisan up || true; fi
}
trap cleanup EXIT

php artisan down --retry=60
maintenance=1

if [ "$SKIP_COMPOSER" -eq 0 ]; then
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

if [ "$SKIP_NPM" -eq 0 ]; then
  npm ci
  npm run build
fi

php artisan optimize:clear
if [ "$SKIP_MIGRATE" -eq 0 ]; then php artisan migrate --force; fi
php artisan storage:link || true
php artisan optimize
php artisan queue:restart
php artisan up
maintenance=0

echo 'Deployment completed. Run deploy/release-verify.sh --production and complete deploy/RELEASE_CHECKLIST.md.'
