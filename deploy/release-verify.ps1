[CmdletBinding()]
param(
    [switch]$Production,
    [switch]$SkipNpm
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

function Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

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

Write-Host 'TUPAD Reporting System - Final Release Verification' -ForegroundColor Green
Assert-Command 'php'
Assert-Command 'composer'
if (-not $SkipNpm) { Assert-Command 'npm' }

if ($Production) {
    Step 'Checking production environment flags'
    if (-not (Test-Path '.env')) { throw 'Missing .env.' }

    $AppEnv = Read-EnvValue 'APP_ENV'
    $AppDebug = Read-EnvValue 'APP_DEBUG'
    $AppUrl = Read-EnvValue 'APP_URL'
    $AppKey = Read-EnvValue 'APP_KEY'
    $LogLevel = Read-EnvValue 'LOG_LEVEL'

    if ($AppEnv -ne 'production') { throw "APP_ENV must be production. Current: '$AppEnv'" }
    if ($AppDebug -notin @('false','0')) { throw "APP_DEBUG must be false. Current: '$AppDebug'" }
    if ([string]::IsNullOrWhiteSpace($AppKey)) { throw 'APP_KEY is empty.' }
    if ($AppUrl -notmatch '^https://') { throw "APP_URL must use HTTPS for production. Current: '$AppUrl'" }
    if ($LogLevel -eq 'debug') { throw 'LOG_LEVEL must not be debug in production.' }
}

Step 'PHP version'
php --version

Step 'Composer manifest validation'
composer validate --no-check-publish

Step 'Laravel application information'
php artisan about

Step 'Full automated test suite'
php artisan test

if (-not $SkipNpm) {
    Step 'Production frontend build'
    npm run build
}

Step 'Migration status'
php artisan migrate:status

Step 'Registered routes'
php artisan route:list

Step 'Scheduler configuration'
php artisan schedule:list

Step 'Laravel production cache compilation'
php artisan optimize

Step 'Health route registration check'
$routeOutput = php artisan route:list --path=up 2>&1 | Out-String
Write-Host $routeOutput
if ($routeOutput -notmatch '/up') {
    throw 'Laravel health route /up was not found.'
}

if (-not $Production) {
    Step 'Returning development environment to uncached state'
    php artisan optimize:clear
}

Write-Host "`nAll automated release verification steps passed." -ForegroundColor Green
Write-Host 'Complete the manual smoke tests in deploy\RELEASE_CHECKLIST.md before release.' -ForegroundColor Yellow
