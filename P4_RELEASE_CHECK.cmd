@echo off
setlocal EnableExtensions
cd /d "%~dp0"

if not exist artisan (
    echo [FAIL] Run this script from the TUPAD project root. artisan was not found.
    exit /b 1
)

if /I "%~1"=="production" goto production

echo ============================================================
echo TUPAD PHASE 4 - UAT / PRE-RELEASE GATE
echo ============================================================

echo [1/5] Clearing Laravel caches...
php artisan optimize:clear || goto fail

echo [2/5] Running non-destructive Phase 4 audit...
php artisan tupad:phase4-audit --report=storage/app/release-audits/latest.json || goto fail

echo [3/5] Running complete PHPUnit suite...
php artisan test || goto fail

echo [4/5] Building production frontend assets...
npm run build || goto fail

echo [5/5] Re-running Phase 4 audit after frontend build...
php artisan tupad:phase4-audit --report=storage/app/release-audits/latest.json || goto fail

echo.
echo [PASS] Phase 4 UAT / pre-release gate completed successfully.
echo Audit report: storage\app\release-audits\latest.json
exit /b 0

:production
echo ============================================================
echo TUPAD PHASE 4 - PRODUCTION RELEASE GATE
echo ============================================================

echo [1/3] Clearing Laravel caches...
php artisan optimize:clear || goto fail

echo [2/3] Running existing production release verifier...
php artisan tupad:release-verify --production || goto fail

echo [3/3] Running Phase 4 production audit...
php artisan tupad:phase4-audit --production --report=storage/app/release-audits/production.json || goto fail

echo.
echo [PASS] Production release gate completed successfully.
echo Audit report: storage\app\release-audits\production.json
exit /b 0

:fail
echo.
echo [FAIL] Phase 4 release gate stopped because a command failed.
echo Fix the reported issue before release.
exit /b 1
