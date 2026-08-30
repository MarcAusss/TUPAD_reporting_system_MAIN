param(
    [switch]$MigrateFresh = $true
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

if (-not (Test-Path (Join-Path $root 'artisan'))) {
    throw 'FIX_FRESH_BASELINE.ps1 must be located in the Laravel project root beside artisan.'
}

$keepMigrations = @(
    '0001_01_01_000000_create_platform_geography_and_users.php',
    '0001_01_01_000100_create_funding_and_project_core.php',
    '0001_01_01_000200_create_project_workflow_and_payments.php',
    '0001_01_01_000300_create_classification_acp_and_audit.php'
)

$migrationDir = Join-Path $root 'database/migrations'
$seederDir = Join-Path $root 'database/seeders'

Write-Host 'Cleaning historical/default migrations...' -ForegroundColor Cyan
Get-ChildItem $migrationDir -Filter '*.php' -File | ForEach-Object {
    if ($keepMigrations -notcontains $_.Name) {
        Write-Host ('  DELETE ' + $_.Name) -ForegroundColor DarkYellow
        Remove-Item $_.FullName -Force
    }
}

$remainingMigrations = @(Get-ChildItem $migrationDir -Filter '*.php' -File | Sort-Object Name)
if ($remainingMigrations.Count -ne 4) {
    throw "Migration cleanup failed. Expected exactly 4 migration files, found $($remainingMigrations.Count)."
}

foreach ($required in $keepMigrations) {
    if (-not (Test-Path (Join-Path $migrationDir $required))) {
        throw "Required clean baseline migration is missing: $required"
    }
}

Write-Host 'Migration baseline verified:' -ForegroundColor Green
$remainingMigrations | ForEach-Object { Write-Host ('  KEEP   ' + $_.Name) -ForegroundColor Green }

$keepSeeders = @(
    'DatabaseSeeder.php',
    'Fy2025TupadProjectSeeder.php'
)

Write-Host 'Cleaning obsolete seeders...' -ForegroundColor Cyan
Get-ChildItem $seederDir -Filter '*.php' -File | ForEach-Object {
    if ($keepSeeders -notcontains $_.Name) {
        Write-Host ('  DELETE ' + $_.Name) -ForegroundColor DarkYellow
        Remove-Item $_.FullName -Force
    }
}

$remainingSeeders = @(Get-ChildItem $seederDir -Filter '*.php' -File | Sort-Object Name)
if ($remainingSeeders.Count -ne 2) {
    throw "Seeder cleanup failed. Expected exactly 2 seeder files, found $($remainingSeeders.Count)."
}

$obsoleteFiles = @(
    'app/Http/Controllers/ProjectBeneficiaryController.php',
    'app/Http/Controllers/ProjectPayoutController.php',
    'tests/Feature/CurrentSystemDemoSeederTest.php',
    'tests/Feature/CurrentSystemDemoSeederPsgcCompatibilityTest.php'
)

foreach ($relative in $obsoleteFiles) {
    $path = Join-Path $root $relative
    if (Test-Path $path) {
        Write-Host ('Removing retired backend file: ' + $relative) -ForegroundColor DarkYellow
        Remove-Item $path -Force
    }
}

$beneficiaryViews = Join-Path $root 'resources/views/projects/beneficiaries'
if (Test-Path $beneficiaryViews) {
    Write-Host 'Removing retired individual-beneficiary CRUD views.' -ForegroundColor DarkYellow
    Remove-Item $beneficiaryViews -Recurse -Force
}

Write-Host 'Clearing Laravel caches...' -ForegroundColor Cyan
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'php artisan optimize:clear failed.' }

if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Host 'Regenerating Composer autoload...' -ForegroundColor Cyan
    composer dump-autoload -o
    if ($LASTEXITCODE -ne 0) { throw 'composer dump-autoload -o failed.' }
} else {
    Write-Warning 'Composer is not available in PATH. Run composer dump-autoload -o manually.'
}

Write-Host ''
Write-Host 'Clean database baseline is ready.' -ForegroundColor Green
Write-Host 'Migrations: 4 clean baseline files' -ForegroundColor Green
Write-Host 'Seeders: DatabaseSeeder + Fy2025TupadProjectSeeder' -ForegroundColor Green

if ($MigrateFresh) {
    Write-Host ''
    Write-Warning 'Running migrate:fresh --seed. ALL DATABASE TABLES/DATA WILL BE DROPPED.'
    php artisan migrate:fresh --seed
    if ($LASTEXITCODE -ne 0) {
        throw 'php artisan migrate:fresh --seed failed.'
    }

    Write-Host ''
    Write-Host 'Fresh migration and FY2025 seeding completed.' -ForegroundColor Green
}
