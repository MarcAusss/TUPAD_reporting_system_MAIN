@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

if not exist "artisan" (
    echo.
    echo ERROR: RESET_FRESH_BASELINE.cmd must be in the Laravel project root beside artisan.
    exit /b 1
)

set "MIGDIR=%CD%\database\migrations"
set "SEEDDIR=%CD%\database\seeders"

if not exist "%MIGDIR%" (
    echo ERROR: database\migrations folder was not found.
    exit /b 1
)

if not exist "%SEEDDIR%" (
    echo ERROR: database\seeders folder was not found.
    exit /b 1
)

echo.
echo ============================================================
echo TUPAD CLEAN FRESH DATABASE BASELINE
echo ============================================================
echo This will DELETE historical migrations and old seeders,
echo then run: php artisan migrate:fresh --seed
echo ALL CURRENT DATABASE TABLES AND DATA WILL BE DROPPED.
echo ============================================================
echo.

rem ------------------------------------------------------------
rem Keep only the 4 clean baseline migrations.
rem ------------------------------------------------------------
echo [1/7] Cleaning historical and Laravel default migrations...
for %%F in ("%MIGDIR%\*.php") do (
    set "NAME=%%~nxF"
    set "KEEP="

    if /I "!NAME!"=="0001_01_01_000000_create_platform_geography_and_users.php" set "KEEP=1"
    if /I "!NAME!"=="0001_01_01_000100_create_funding_and_project_core.php" set "KEEP=1"
    if /I "!NAME!"=="0001_01_01_000200_create_project_workflow_and_payments.php" set "KEEP=1"
    if /I "!NAME!"=="0001_01_01_000300_create_classification_acp_and_audit.php" set "KEEP=1"

    if not defined KEEP (
        echo       DELETE !NAME!
        del /F /Q "%%~fF" >nul 2>&1
        if exist "%%~fF" (
            echo ERROR: Could not delete migration: !NAME!
            exit /b 1
        )
    )
)

for %%R in (
    "0001_01_01_000000_create_platform_geography_and_users.php"
    "0001_01_01_000100_create_funding_and_project_core.php"
    "0001_01_01_000200_create_project_workflow_and_payments.php"
    "0001_01_01_000300_create_classification_acp_and_audit.php"
) do (
    if not exist "%MIGDIR%\%%~R" (
        echo ERROR: Required clean migration is missing: %%~R
        exit /b 1
    )
)

set /A MIGCOUNT=0
for %%F in ("%MIGDIR%\*.php") do set /A MIGCOUNT+=1
if not "!MIGCOUNT!"=="4" (
    echo ERROR: Expected exactly 4 migration files after cleanup, found !MIGCOUNT!.
    echo Current migration files:
    dir /B "%MIGDIR%\*.php"
    exit /b 1
)

echo       OK - exactly 4 baseline migrations remain.

rem ------------------------------------------------------------
rem Keep only DatabaseSeeder and FY2025 seeder.
rem ------------------------------------------------------------
echo [2/7] Cleaning obsolete seeders...
for %%F in ("%SEEDDIR%\*.php") do (
    set "NAME=%%~nxF"
    set "KEEP="

    if /I "!NAME!"=="DatabaseSeeder.php" set "KEEP=1"
    if /I "!NAME!"=="Fy2025TupadProjectSeeder.php" set "KEEP=1"

    if not defined KEEP (
        echo       DELETE !NAME!
        del /F /Q "%%~fF" >nul 2>&1
        if exist "%%~fF" (
            echo ERROR: Could not delete seeder: !NAME!
            exit /b 1
        )
    )
)

if not exist "%SEEDDIR%\DatabaseSeeder.php" (
    echo ERROR: DatabaseSeeder.php is missing.
    exit /b 1
)
if not exist "%SEEDDIR%\Fy2025TupadProjectSeeder.php" (
    echo ERROR: Fy2025TupadProjectSeeder.php is missing.
    exit /b 1
)

set /A SEEDCOUNT=0
for %%F in ("%SEEDDIR%\*.php") do set /A SEEDCOUNT+=1
if not "!SEEDCOUNT!"=="2" (
    echo ERROR: Expected exactly 2 seeder files after cleanup, found !SEEDCOUNT!.
    dir /B "%SEEDDIR%\*.php"
    exit /b 1
)

echo       OK - DatabaseSeeder + FY2025 seeder only.

rem ------------------------------------------------------------
rem Remove proven-retired backend leftovers.
rem ------------------------------------------------------------
echo [3/7] Removing retired backend leftovers...
if exist "app\Http\Controllers\ProjectBeneficiaryController.php" del /F /Q "app\Http\Controllers\ProjectBeneficiaryController.php"
if exist "app\Http\Controllers\ProjectPayoutController.php" del /F /Q "app\Http\Controllers\ProjectPayoutController.php"
if exist "tests\Feature\CurrentSystemDemoSeederTest.php" del /F /Q "tests\Feature\CurrentSystemDemoSeederTest.php"
if exist "tests\Feature\CurrentSystemDemoSeederPsgcCompatibilityTest.php" del /F /Q "tests\Feature\CurrentSystemDemoSeederPsgcCompatibilityTest.php"
if exist "resources\views\projects\beneficiaries" rmdir /S /Q "resources\views\projects\beneficiaries"
echo       OK.

rem ------------------------------------------------------------
rem Clear Laravel caches.
rem ------------------------------------------------------------
echo [4/7] Clearing Laravel caches...
php artisan optimize:clear
if errorlevel 1 (
    echo ERROR: php artisan optimize:clear failed.
    exit /b 1
)

rem ------------------------------------------------------------
rem Composer autoload.
rem ------------------------------------------------------------
echo [5/7] Regenerating Composer autoload...
where composer >nul 2>&1
if errorlevel 1 (
    echo WARNING: composer was not found in PATH.
    echo          Run composer dump-autoload -o manually after this script.
) else (
    call composer dump-autoload -o
    if errorlevel 1 (
        echo ERROR: composer dump-autoload -o failed.
        exit /b 1
    )
)

rem ------------------------------------------------------------
rem Final preflight display.
rem ------------------------------------------------------------
echo [6/7] Verifying clean migration baseline...
echo.
echo Migrations that will run:
dir /B "%MIGDIR%\*.php"
echo.
echo Seeders present:
dir /B "%SEEDDIR%\*.php"
echo.

rem ------------------------------------------------------------
rem Destructive fresh migration + seed.
rem ------------------------------------------------------------
echo [7/7] Running migrate:fresh --seed...
echo.
php artisan migrate:fresh --seed
if errorlevel 1 (
    echo.
    echo ERROR: migrate:fresh --seed failed.
    exit /b 1
)

echo.
echo ============================================================
echo SUCCESS: Fresh TUPAD database baseline completed.
echo ============================================================
echo Migrations: 4 clean baseline files
echo Seed data : Fy2025TupadProjectSeeder only
echo ============================================================

endlocal
exit /b 0
