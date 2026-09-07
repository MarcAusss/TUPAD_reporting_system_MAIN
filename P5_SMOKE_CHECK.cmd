@echo off
setlocal
cd /d "%~dp0"

echo TUPAD P5 post-deployment automated smoke gate
php artisan tupad:production-health --report=storage/app/health/latest.json
if errorlevel 1 exit /b 1
php artisan tupad:phase5-audit --production --report=storage/app/release-audits/p5-post-deploy.json
if errorlevel 1 exit /b 1

echo.
echo Automated smoke gate PASSED.
echo Complete the manual role-based smoke checklist in docs\go-live\P5_5_GO_LIVE_SMOKE_TEST.md.
exit /b 0
