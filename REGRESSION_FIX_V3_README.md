# TUPAD Current-Phase Regression Fix V3

Apply this overlay **after** `TUPAD_CURRENT_PHASE_REGRESSION_FIX_V2_OVERLAY`.

## Scope

This is a focused correction for the remaining failures reported after V2.
No migration or reseeding is required.

### 1. DatabaseValidationHardeningTest

The test for a municipality belonging to a different province now expects **404 Not Found**.
This is the current intended flow:

1. TC province scope accepts the submitted province because it is the TC's assigned province.
2. The project controller performs a province-scoped municipality lookup.
3. A municipality belonging to another province is not visible within that lookup.
4. The request fails closed as 404.

The security middleware is not weakened.

### 2. ProjectSeriesTevsTest

The successful Project Series / TEVS fixture now uses the canonical Albay PSGC code (`050500000`) and explicitly assigns the TC to Albay before submitting the official project.
This lets the test reach project validation/storage instead of being stopped by the P0 province-scope middleware.

### 3. SPRS official browser print

`resources/views/reports/print.blade.php` now renders the existing `sprs_print_matrix` prepared by `OfficialPeriodicReportService` instead of falling back to the generic four-column report table.

The print matrix includes:

- Province / Month
- Overall Total / Female
- Albay Total / Female
- Camarines Norte Total / Female
- Camarines Sur Total / Female
- Catanduanes Total / Female
- Masbate Total / Female
- Sorsogon Total / Female
- Date Accomplished
- Remarks
- January through December
- quarterly subtotal rows
- Grand Total
- future-month blanking based on the selected month cut-off

It also emits the existing regression data attributes such as:

- `data-sprs-row="september"`
- `data-sprs-included="0"`
- `data-sprs-cell="january-overall-total"`

The report data calculation was not weakened or duplicated in the Blade view; the view consumes the authoritative matrix already prepared by the report service.

## Physical / Financial report structure

The prior V2 behavior remains unchanged. There are only four Physical & Financial report views:

1. Overall
2. Semester
3. Quarter
4. Month

There are no separate Short-Term or Long-Term report views.

## Apply

Extract the overlay into the TUPAD project root and overwrite matching files.

Then run:

```bash
php artisan optimize:clear
```

No database migration is required.

## Focused tests

```bash
php artisan test --filter=DatabaseValidationHardeningTest
php artisan test --filter=ProjectSeriesTevsTest
php artisan test --filter=StatisticalPerformanceOfficialPrintMatrixLayoutTest
```

Also verify the periodic print integration:

```bash
php artisan test --filter=MajorRevisionPhase14FOfficialPrintHeaderLayoutTest
php artisan test --filter=MajorRevisionPhase14GFinalReportsReleaseVerificationTest
```

Then run the entire suite:

```bash
php artisan test
```
