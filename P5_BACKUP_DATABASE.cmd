@echo off
setlocal
cd /d "%~dp0"
php artisan tupad:backup-database
exit /b %errorlevel%
