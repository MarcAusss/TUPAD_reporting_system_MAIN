#!/usr/bin/env bash
set -euo pipefail

PRODUCTION=0
SKIP_NPM=0
for arg in "$@"; do
  case "$arg" in
    --production) PRODUCTION=1 ;;
    --skip-npm) SKIP_NPM=1 ;;
  esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

env_value() {
  local key="$1"
  [ -f .env ] || return 0
  grep -E "^${key}=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

if [ "$PRODUCTION" -eq 1 ]; then
  [ -f .env ] || { echo 'Missing .env.' >&2; exit 1; }
  [ "$(env_value APP_ENV)" = production ] || { echo 'APP_ENV must be production.' >&2; exit 1; }
  case "$(env_value APP_DEBUG)" in false|0) ;; *) echo 'APP_DEBUG must be false.' >&2; exit 1 ;; esac
  [ -n "$(env_value APP_KEY)" ] || { echo 'APP_KEY is empty.' >&2; exit 1; }
  [[ "$(env_value APP_URL)" =~ ^https:// ]] || { echo 'APP_URL must use HTTPS.' >&2; exit 1; }
  [ "$(env_value LOG_LEVEL)" != debug ] || { echo 'LOG_LEVEL must not be debug.' >&2; exit 1; }
fi

php --version
composer validate --no-check-publish
php artisan about
php artisan test
if [ "$SKIP_NPM" -eq 0 ]; then npm run build; fi
php artisan migrate:status
php artisan route:list
php artisan schedule:list
php artisan optimize
php artisan route:list --path=up | grep -q '/up'

if [ "$PRODUCTION" -eq 0 ]; then php artisan optimize:clear; fi

echo 'All automated release verification steps passed.'
echo 'Complete deploy/RELEASE_CHECKLIST.md before release.'
