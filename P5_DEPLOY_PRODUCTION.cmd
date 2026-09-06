@echo off
setlocal
cd /d "%~dp0"

echo ============================================================
echo TUPAD P5 PRODUCTION DEPLOYMENT
echo Three live roles: Administrator, Focal, TC
echo Initial project workflow: Ongoing Profiling ^> TSSD Evaluation
echo ============================================================
echo.
echo Confirm the production .env and database backup destination before continuing.
choice /C YN /M "Continue with production deployment"
if errorlevel 2 exit /b 1

call :run composer install --no-dev --optimize-autoloader --no-interaction || goto :fail
call :run npm ci || goto :fail
call :run npm run build || goto :fail

rem A successful database backup is mandatory before schema changes.
call :run php artisan tupad:backup-database || goto :fail
call :run php artisan down || goto :fail
call :run php artisan optimize:clear || goto :fail
call :run php artisan migrate --force || goto :fail
call :run php artisan tupad:reference-data-sync || goto :fail
call :run php artisan tupad:initial-admin || goto :fail

rem Strict gate must pass before cached production configuration is published.
call :run php artisan tupad:phase5-audit --production --report=storage/app/release-audits/p5-production.json || goto :fail
call :run php artisan config:cache || goto :fail
call :run php artisan route:cache || goto :fail
call :run php artisan view:cache || goto :fail
call :run php artisan event:cache || goto :fail
call :run php artisan up || goto :fail

echo.
echo Production deployment completed successfully.
echo Run P5_SMOKE_CHECK.cmd, then complete docs\go-live\P5_5_GO_LIVE_SMOKE_TEST.md.
exit /b 0

:run
echo.
echo ^> %*
%*
exit /b %errorlevel%

:fail
echo.
echo DEPLOYMENT STOPPED because a required step failed.
echo If maintenance mode was already enabled, the application may still be down.
echo Fix the reported issue, re-run the strict audit, then run: php artisan up
exit /b 1
