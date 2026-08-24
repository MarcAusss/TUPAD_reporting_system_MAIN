$ErrorActionPreference = 'Stop'

function Backup-File([string]$Path) {
    if (-not (Test-Path $Path)) { return }
    $backup = "$Path.phase13_4_backup"
    if (-not (Test-Path $backup)) {
        Copy-Item $Path $backup
        Write-Host "Backup: $backup" -ForegroundColor DarkGray
    }
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $Content, $utf8NoBom)
}

if (-not (Test-Path 'artisan')) {
    throw 'Run this script from the root of the TUPAD Laravel project (the folder containing artisan).'
}

Write-Host 'Applying Phase 13.4B safe UI patches...' -ForegroundColor Cyan

# -----------------------------------------------------------------------------
# resources/js/app.js
# -----------------------------------------------------------------------------
$appJs = 'resources/js/app.js'
if (Test-Path $appJs) {
    Backup-File $appJs
    $content = Get-Content $appJs -Raw

    if ($content -notmatch "tupad-ui") {
        $addition = @"

import { initializeTupadUi } from './tupad-ui';

document.addEventListener('DOMContentLoaded', () => {
    initializeTupadUi();
});
"@
        $content = $content.TrimEnd() + $addition + "`r`n"
        Write-Utf8NoBom $appJs $content
        Write-Host 'Patched resources/js/app.js' -ForegroundColor Green
    } else {
        Write-Host 'resources/js/app.js already contains Phase 13.4 UI initialization.' -ForegroundColor Yellow
    }
}

# -----------------------------------------------------------------------------
# resources/views/layouts/app.blade.php
# -----------------------------------------------------------------------------
$layout = 'resources/views/layouts/app.blade.php'
if (Test-Path $layout) {
    Backup-File $layout
    $content = Get-Content $layout -Raw
    $changed = $false

    # Replace the old disabled Reports item only when a real reports route link is absent.
    if ($content -notmatch "route\('reports\.index'\)") {
        $reportsPattern = '(?s)\{\{--\s*Reports\s*--\}\}\s*<button\s+type="button"\s+disabled.*?</button>'
        $reportsReplacement = @'
{{-- Reports --}}
                        @if (auth()->user()->isAdmin() || auth()->user()->isTc() || auth()->user()->isFocal())
                            <a href="{{ route('reports.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                                {{ request()->routeIs('reports.*')
                                    ? 'bg-slate-100 text-slate-900'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 19V9"></path>
                                    <path d="M10 19V5"></path>
                                    <path d="M16 19v-7"></path>
                                    <path d="M22 19H2"></path>
                                </svg>
                                <span>Reports</span>
                            </a>
                        @endif
'@
        $newContent = [regex]::Replace($content, $reportsPattern, $reportsReplacement, 1)
        if ($newContent -ne $content) {
            $content = $newContent
            $changed = $true
            Write-Host 'Enabled role-aware Reports navigation.' -ForegroundColor Green
        } else {
            Write-Host 'Reports block did not match; left layout unchanged for this item.' -ForegroundColor Yellow
        }
    }

    # Make the non-functional global search visibly non-interactive.
    if ($content -match 'type="search"' -and $content -notmatch 'type="search"[^>]*disabled') {
        $content = [regex]::Replace(
            $content,
            '<input type="search" placeholder="Search\.\.\."',
            '<input type="search" placeholder="Search coming soon" disabled aria-disabled="true" title="Global search is not enabled yet"',
            1
        )
        $changed = $true
        Write-Host 'Marked global search as disabled.' -ForegroundColor Green
    }

    # Make notification control explicitly disabled if it has no implementation.
    if ($content -match 'aria-label="Notifications"' -and $content -notmatch 'disabled[^>]*aria-label="Notifications"') {
        $content = $content -replace '<button type="button"\s+class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"\s+aria-label="Notifications">', '<button type="button" disabled aria-disabled="true" title="Notifications are not enabled yet" class="flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg text-slate-300" aria-label="Notifications">'
        $changed = $true
        Write-Host 'Marked notifications as disabled.' -ForegroundColor Green
    }

    if ($changed) {
        Write-Utf8NoBom $layout $content
    } else {
        Write-Host 'No layout patch was required.' -ForegroundColor Yellow
    }
}

# -----------------------------------------------------------------------------
# resources/css/app.css
# -----------------------------------------------------------------------------
$css = 'resources/css/app.css'
if (Test-Path $css) {
    Backup-File $css
    $content = Get-Content $css -Raw
    $marker = '/* Phase 13.4 UI hardening */'

    if ($content -notmatch [regex]::Escape($marker)) {
        $addition = @'

/* Phase 13.4 UI hardening */
:where(a, button, input, select, textarea):focus-visible {
    outline: 2px solid rgb(51 65 85);
    outline-offset: 2px;
}

:where(button, input, select, textarea):disabled {
    cursor: not-allowed;
}

:where(input, textarea)[readonly] {
    cursor: default;
}

form[aria-busy="true"] {
    cursor: progress;
}

form[aria-busy="true"] button[type="submit"] {
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
'@
        $content = $content.TrimEnd() + $addition + "`r`n"
        Write-Utf8NoBom $css $content
        Write-Host 'Patched resources/css/app.css' -ForegroundColor Green
    } else {
        Write-Host 'resources/css/app.css already contains Phase 13.4 hardening.' -ForegroundColor Yellow
    }
}

Write-Host ''
Write-Host 'Phase 13.4B patch complete.' -ForegroundColor Cyan
Write-Host 'Run:' -ForegroundColor White
Write-Host '  php artisan optimize:clear'
Write-Host '  npm run build'
Write-Host '  php artisan test'
