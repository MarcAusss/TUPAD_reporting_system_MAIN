param(
    [switch]$MigrateFresh
)

$ErrorActionPreference = 'Stop'
$root = (Get-Location).Path

if (-not (Test-Path (Join-Path $root 'artisan'))) {
    throw 'Run this script from the Laravel project root (the folder containing artisan).'
}

$keepMigrations = @(
    '0001_01_01_000000_create_platform_geography_and_users.php',
    '0001_01_01_000100_create_funding_and_project_core.php',
    '0001_01_01_000200_create_project_workflow_and_payments.php',
    '0001_01_01_000300_create_classification_acp_and_audit.php'
)

Get-ChildItem (Join-Path $root 'database/migrations') -Filter '*.php' -File | ForEach-Object {
    if ($keepMigrations -notcontains $_.Name) {
        Remove-Item $_.FullName -Force
    }
}

$keepSeeders = @('DatabaseSeeder.php', 'Fy2025TupadProjectSeeder.php')
Get-ChildItem (Join-Path $root 'database/seeders') -Filter '*.php' -File | ForEach-Object {
    if ($keepSeeders -notcontains $_.Name) {
        Remove-Item $_.FullName -Force
    }
}

$obsoleteFiles = @(
    'app/Http/Controllers/ProjectBeneficiaryController.php',
    'app/Http/Controllers/ProjectPayoutController.php',
    'tests/Feature/CurrentSystemDemoSeederTest.php',
    'tests/Feature/CurrentSystemDemoSeederPsgcCompatibilityTest.php'
)

foreach ($relative in $obsoleteFiles) {
    $path = Join-Path $root $relative
    if (Test-Path $path) { Remove-Item $path -Force }
}

$beneficiaryViews = Join-Path $root 'resources/views/projects/beneficiaries'
if (Test-Path $beneficiaryViews) {
    Remove-Item $beneficiaryViews -Recurse -Force
}

Write-Host 'Clean migration/seeder/backend baseline applied.' -ForegroundColor Green
Write-Host 'Migrations kept: 4' -ForegroundColor Cyan
Write-Host 'Seeders kept: DatabaseSeeder + Fy2025TupadProjectSeeder' -ForegroundColor Cyan

php artisan optimize:clear

if (Get-Command composer -ErrorAction SilentlyContinue) {
    composer dump-autoload -o
} else {
    Write-Warning 'Composer was not found in PATH. Run: composer dump-autoload -o'
}

if ($MigrateFresh) {
    Write-Warning 'migrate:fresh will DROP ALL DATABASE TABLES AND DATA.'
    php artisan migrate:fresh --seed
} else {
    Write-Host 'When ready to destroy/rebuild the current database, run:' -ForegroundColor Yellow
    Write-Host '  php artisan migrate:fresh --seed' -ForegroundColor Yellow
}
