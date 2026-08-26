$ErrorActionPreference = "Stop"

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Label,

        [Parameter(Mandatory = $true)]
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "============================================================"
    Write-Host $Label
    Write-Host "============================================================"

    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

Invoke-CheckedCommand `
    -Label "1. Clear Laravel caches" `
    -Command {
        php artisan optimize:clear
    }

Invoke-CheckedCommand `
    -Label "2. Check migration status" `
    -Command {
        php artisan migrate:status
    }

Invoke-CheckedCommand `
    -Label "3. Build production frontend" `
    -Command {
        npm run build
    }

Invoke-CheckedCommand `
    -Label "4. Release readiness structure test" `
    -Command {
        php artisan test --filter=RevisionR142ReleaseReadinessTest
    }

Invoke-CheckedCommand `
    -Label "5. Separate insurance + approved insurance lock" `
    -Command {
        php artisan test --filter=ApprovedInsuranceLockTest
    }

Invoke-CheckedCommand `
    -Label "6. Sponsor / Partner reference UX" `
    -Command {
        php artisan test --filter=SponsorPartnerReferenceUxTest
    }

Invoke-CheckedCommand `
    -Label "7. Bidirectional ADL realignment" `
    -Command {
        php artisan test --filter=AdlBidirectionalRealignmentTest
    }

Invoke-CheckedCommand `
    -Label "8. Merged implementation requirements" `
    -Command {
        php artisan test --filter=MergedImplementationRequirementsTest
    }

Invoke-CheckedCommand `
    -Label "9. Automatic implementation work period" `
    -Command {
        php artisan test --filter=AutomaticImplementationPeriodTest
    }

Invoke-CheckedCommand `
    -Label "10. Automatic three-column implementation board" `
    -Command {
        php artisan test --filter=AutomaticImplementationWorkflowBoardTest
    }

Invoke-CheckedCommand `
    -Label "11. Set Work Period modal" `
    -Command {
        php artisan test --filter=ImplementationWorkPeriodModalTest
    }

Invoke-CheckedCommand `
    -Label "12. Existing TC workflow queues" `
    -Command {
        php artisan test --filter=ProjectWorkflowQueueTest
    }

Invoke-CheckedCommand `
    -Label "13. Database validation hardening" `
    -Command {
        php artisan test --filter=DatabaseValidationHardeningTest
    }

Invoke-CheckedCommand `
    -Label "14. Security / authorization regression" `
    -Command {
        php artisan test --filter=SecurityAuthorizationTest
    }

Invoke-CheckedCommand `
    -Label "15. Full regression suite" `
    -Command {
        php artisan test
    }

Write-Host ""
Write-Host "============================================================"
Write-Host "R14.2 / Phase 8 verification completed successfully."
Write-Host "============================================================"
