param(
    [string]$ProjectRoot = "."
)

$ErrorActionPreference = "Stop"

$projectPath = (Resolve-Path $ProjectRoot).Path
$faviconPath = Join-Path $projectPath "public\images\favicon.png"
$viewsPath = Join-Path $projectPath "resources\views"

Write-Host ""
Write-Host "TUPAD Reporting System - Favicon Overlay" -ForegroundColor Cyan
Write-Host "Project: $projectPath"
Write-Host ""

if (-not (Test-Path $faviconPath)) {
    Write-Host "ERROR: Favicon image was not found:" -ForegroundColor Red
    Write-Host "  public\images\favicon.png"
    Write-Host ""
    Write-Host "Place favicon.png in public\images\ and run this script again."
    exit 1
}

if (-not (Test-Path $viewsPath)) {
    Write-Host "ERROR: resources\views was not found." -ForegroundColor Red
    Write-Host "Run this script from the root folder of your Laravel project."
    exit 1
}

$faviconBlock = @'
    {{-- TUPAD Reporting System favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">
'@

$bladeFiles = Get-ChildItem -Path $viewsPath -Filter "*.blade.php" -Recurse
$updated = @()
$alreadyConfigured = @()

foreach ($file in $bladeFiles) {
    $content = Get-Content -Path $file.FullName -Raw

    # Only full HTML/Blade layouts that actually contain a <head> section.
    if ($content -notmatch '(?is)<head\b' -or $content -notmatch '(?is)</head>') {
        continue
    }

    if ($content -match "asset\(['""]images/favicon\.png['""]\)") {
        $alreadyConfigured += $file.FullName
        continue
    }

    $backup = $file.FullName + ".favicon-backup"
    if (-not (Test-Path $backup)) {
        Copy-Item $file.FullName $backup
    }

    $replacement = $faviconBlock + "`r`n</head>"
    $newContent = [regex]::Replace(
        $content,
        '(?is)</head>',
        [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $replacement },
        1
    )

    Set-Content -Path $file.FullName -Value $newContent -Encoding UTF8
    $updated += $file.FullName
}

Write-Host ""

if ($updated.Count -gt 0) {
    Write-Host "Updated Blade layout(s):" -ForegroundColor Green
    foreach ($file in $updated) {
        $relative = $file.Substring($projectPath.Length).TrimStart('\')
        Write-Host "  $relative"
    }
} elseif ($alreadyConfigured.Count -gt 0) {
    Write-Host "Favicon is already configured in the detected layout(s)." -ForegroundColor Yellow
} else {
    Write-Host "No Blade file containing a full <head>...</head> section was found." -ForegroundColor Yellow
    Write-Host "Add the following inside your main layout <head> manually:"
    Write-Host ""
    Write-Host $faviconBlock
    exit 2
}

Write-Host ""
Write-Host "Clearing Laravel caches..." -ForegroundColor Cyan

Push-Location $projectPath
try {
    php artisan optimize:clear
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
Write-Host "Hard-refresh the browser with Ctrl + Shift + R."
Write-Host ""
