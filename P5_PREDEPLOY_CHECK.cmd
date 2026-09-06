@echo off
setlocal
cd /d "%~dp0"

echo ============================================================
echo TUPAD P5 PRE-DEPLOYMENT CHECK
echo Run this on the release/UAT machine before production copy.
echo ============================================================

call :run php artisan optimize:clear || goto :fail
call :run php artisan test --stop-on-failure || goto :fail
call :run npm ci || goto :fail
call :run npm run build || goto :fail
call :run php artisan tupad:phase5-audit --report=storage/app/release-audits/p5-predeploy.json || goto :fail

echo.
echo P5 pre-deployment verification PASSED.
exit /b 0

:run
echo.
echo ^> %*
%*
exit /b %errorlevel%

:fail
echo.
echo P5 pre-deployment verification FAILED. Do not deploy this build.
exit /b 1
