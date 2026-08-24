[CmdletBinding()]
param(
    [switch]$SkipNpm,
    [switch]$SkipComposer,
    [switch]$SkipMigrate
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

function Read-EnvValue([string]$Name) {
    if (-not (Test-Path '.env')) { return $null }
    $line = Get-Content '.env' | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -Last 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Assert-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' was not found in PATH."
    }
}

Write-Host 'TUPAD Reporting System - Production Deployment' -ForegroundColor Cyan

if (-not (Test-Path '.env')) {
    throw 'Missing .env. Create it from .env.production.example and fill production values first.'
}

$AppEnv = Read-EnvValue 'APP_ENV'
$AppDebug = Read-EnvValue 'APP_DEBUG'
$AppUrl = Read-EnvValue 'APP_URL'
$AppKey = Read-EnvValue 'APP_KEY'

if ($AppEnv -ne 'production') { throw "APP_ENV must be production. Current: '$AppEnv'" }
if ($AppDebug -notin @('false','0')) { throw "APP_DEBUG must be false. Current: '$AppDebug'" }
if ([string]::IsNullOrWhiteSpace($AppKey)) { throw 'APP_KEY is empty.' }
if ($AppUrl -notmatch '^https://') { Write-Warning "APP_URL is not HTTPS: $AppUrl" }

Assert-Command 'php'
Assert-Command 'composer'
if (-not $SkipNpm) { Assert-Command 'npm' }

$MaintenanceEnabled = $false

try {
    Write-Host 'Enabling maintenance mode...'
    php artisan down --retry=60
    $MaintenanceEnabled = $true

    if (-not $SkipComposer) {
        Write-Host 'Installing optimized PHP dependencies...'
        composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    }

    if (-not $SkipNpm) {
        Write-Host 'Installing/building frontend assets...'
        npm ci
        npm run build
    }

    Write-Host 'Clearing stale Laravel caches...'
    php artisan optimize:clear

    if (-not $SkipMigrate) {
        Write-Host 'Running database migrations...'
        php artisan migrate --force
    }

    Write-Host 'Ensuring public storage link exists...'
    php artisan storage:link 2>$null

    Write-Host 'Building Laravel production caches...'
    php artisan optimize

    Write-Host 'Restarting queue workers (safe even when no persistent worker is active)...'
    php artisan queue:restart

    Write-Host 'Deployment steps completed.' -ForegroundColor Green
}
finally {
    if ($MaintenanceEnabled) {
        Write-Host 'Disabling maintenance mode...'
        php artisan up
    }
}

Write-Host 'Run .\deploy\release-verify.ps1 -Production and complete deploy\RELEASE_CHECKLIST.md.' -ForegroundColor Cyan
