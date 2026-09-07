# TUPAD Reformulated Target — Focal-Only Editable Overlay

## What this overlay adds

- New `reformulated_targets` database table keyed by Province + Fiscal Year.
- Focal-only management page at `/reports/reformulated-targets`.
- Focal-only PUT endpoint for saving Physical and Financial targets.
- Backend authorization through the existing `role:focal` middleware.
- Dynamic regional totals while editing.
- Physical & Financial reports automatically use saved Focal targets for the selected fiscal year.
- Existing project-derived targets remain as a fallback for province/year combinations that have not yet been manually saved.
- Target create/update changes are included in the existing audit observer.
- Admin and TC may continue viewing reports, but cannot open or submit the target-management endpoint.

## Install

Extract this ZIP directly into the TUPAD Reporting System project root and allow file replacement.

Then run:

```bash
php artisan migrate
php artisan optimize:clear
```

Optional verification:

```bash
php artisan test --filter=ReformulatedTargetManagementTest
```

## Use

1. Sign in as Focal.
2. Open **Reports → Physical & Financial**.
3. Click **Edit Reformulated Target**.
4. Select the fiscal year.
5. Edit Physical Target and Financial Target per province.
6. Click **Save Reformulated Target**.
7. Return to the Physical & Financial report with the same fiscal year selected.

## Important behavior

A saved record is authoritative for that province and fiscal year. If no saved record exists, the report keeps the previous project-derived target calculation so applying the overlay does not immediately zero existing reports.
