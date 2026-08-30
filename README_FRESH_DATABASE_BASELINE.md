# TUPAD Fresh Database Baseline

This overlay converts the current TUPAD Reporting System into a clean fresh-install database baseline.

## What changes

- 44 historical migrations are replaced by 4 clean baseline migrations.
- The four migrations reproduce the current final schema directly instead of creating legacy columns and later altering them.
- `database/seeders` is reduced to:
  - `DatabaseSeeder.php` (Laravel's required default seeder entry point)
  - `Fy2025TupadProjectSeeder.php` (the only actual development data seeder)
- The FY2025 seeder now restores all Bicol references from the reviewed local mapping GeoJSON:
  - 6 provinces
  - 114 municipalities/cities
  - 3,465 barangays
- The FY2025 seeder creates the four development accounts (`admin`, `focal`, `tc`, `gip`).
- The TC demo account is assigned to active Albay PSGC `050500000` so Geographic Mapping works immediately.
- It then creates 30 FY2025 spreadsheet projects, exactly 5 per Bicol province, all forced to `ongoing_profiling`.
- Retired individual-beneficiary CRUD controller/views and the unused legacy payout controller are removed.
- Obsolete demo-seeder-specific tests/references are removed or updated.

## IMPORTANT

`php artisan migrate:fresh --seed` DESTROYS all current database tables/data. Back up anything you need first.

## Apply

1. Extract this ZIP into the Laravel project root and replace matching files.
2. Open PowerShell in the Laravel project root.
3. Run:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\APPLY_FRESH_BASELINE.ps1 -MigrateFresh
```

Or apply cleanup without immediately rebuilding the database:

```powershell
.\APPLY_FRESH_BASELINE.ps1
php artisan migrate:fresh --seed
```

## After migrate:fresh --seed

Expected development accounts (password: `password`):
- `admin`
- `focal`
- `tc` (assigned to Albay)
- `gip`

Expected reference/project counts:
- Provinces: 6
- Municipalities/cities: 114
- Barangays: 3,465
- FY2025 projects: 30
- Projects per province: 5
- Project status: all `Ongoing Profiling`

## Verify

```powershell
php artisan test tests/Feature/FreshDatabaseBaselineTest.php
php artisan test tests/Feature/GeographicMappingPhase5CoordinatorScopeTest.php
php artisan test tests/Feature/GeographicMappingPhase6MetricsPolishTest.php
php artisan test tests/Feature/SystemWideUiDataConnectivityStabilizationTest.php
php artisan tupad:release-verify
npm run build
```

The FY2025 seeder depends on the reviewed `public/geojson/bicol/municipalities` and `public/geojson/bicol/barangay-labels` files already present in the current project. It does not download PSGC data from the internet.
