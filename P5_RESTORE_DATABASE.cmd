@echo off
setlocal
cd /d "%~dp0"
if "%~1"=="" (
    echo Usage: P5_RESTORE_DATABASE.cmd path\to\backup.sql
    echo.
    echo This is destructive. Put the application in maintenance mode and create a separate pre-restore backup first.
    exit /b 1
)

echo WARNING: This will import "%~1" into the configured MySQL database.
choice /C YN /M "Have you put the application in maintenance mode and created a pre-restore backup"
if errorlevel 2 exit /b 1
php artisan tupad:restore-database "%~1" --confirm=RESTORE
exit /b %errorlevel%
