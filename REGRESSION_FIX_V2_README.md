# TUPAD Current-Phase Regression Fix V2

Apply this overlay after the previous P0/P1/current regression overlays.

## Fixes in this overlay

- Keeps Physical & Financial Accomplishment to four report views only:
  - Overall
  - Semester
  - Quarter
  - Month
- Removes remaining report-facing references to separate Short-Term / Long-Term report subdivisions.
- Renames the Physical/Financial detailed-report action to **Detailed Generator** and preserves Print/PDF/Excel/CSV actions.
- Corrects TC test fixtures to use valid Region V PSGC province codes so the P0 province-scope middleware allows requests to reach the intended validation logic.
- Corrects the exact barangay allocation classification regression fixture.
- Corrects MultiLocation project/location API fixtures for strict coordinator province scope.
- Aligns Phase 14B period assertions with the authoritative fixed matrix labels and fiscal-year filtering.

## Apply

```bash
php artisan optimize:clear
npm run build
```

No migration or reseed is required for this overlay.

## Focused tests

```bash
php artisan test --filter=DatabaseValidationHardeningTest
php artisan test --filter=MajorRevisionPhase14BPhysicalFinancialAccomplishmentReportsTest
php artisan test --filter=MajorRevisionPhase7ClassificationTest
php artisan test --filter=MultiLocationProjectTest
php artisan test --filter=PhysicalFinancialTableFirstReportTest
```

Then run:

```bash
php artisan test
```
