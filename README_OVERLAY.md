# TUPAD Reporting System — Physical & Financial Table-First Overlay

This overlay implements the requested first report-page revision using the supplied `TUPAD Reporting Templates.xlsx` Physical & Financial layouts.

## What changes

- Physical & Financial Accomplishment is now **table-first** instead of dashboard/card-heavy.
- Keeps a compact summary header above the official data table.
- Removes the separate **Short-Term Accomplishment** and **Long-Term Accomplishment** report views.
- Adds **Accomplishment per Semester**.
- Uses these four report views only:
  1. Overall Accomplishment
  2. Accomplishment per Semester
  3. Accomplishment per Quarter
  4. Accomplishment per Month
- Screen tables follow the supplied spreadsheet structure:
  - Overall: Province + Reformulated Target P/F + Accomplishment P/F + Balance P/F
  - Semester: Province + Reformulated Target P/F + 1st Semester P/F + 2nd Semester P/F + Balance P/F
  - Quarter: Province + Reformulated Target P/F + Q1–Q4 P/F + Balance P/F
  - Month: Province + January–December P/F
- The standard Bicol province rows are shown in the official order, with a TOTAL row.
- Print output for this report is **Letter / Portrait**.
- Semester, quarter, and month printing uses **one reporting period per portrait page** so the official table remains readable after removing Short-Term/Long-Term subdivisions.
- PDF output also uses Letter portrait pages for this report.
- Geographic Mapping and all other report pages are not modified by this overlay.
- No database migration is required.

## Files included

- `app/Enums/ReportDimension.php`
- `app/Enums/ReportType.php`
- `app/Http/Controllers/PhysicalFinancialAccomplishmentController.php`
- `app/Reports/ReportWorkspaceCatalog.php`
- `app/Services/Exports/PdfTableWriter.php`
- `app/Services/Reports/PhysicalFinancialMatrixService.php` **(new)**
- `app/Services/Reports/ReportGenerationService.php`
- `resources/views/reports/physical-financial/index.blade.php`
- `resources/views/reports/print.blade.php`
- `tests/Feature/PhysicalFinancialTableFirstReportTest.php` **(new)**

## Apply

1. Back up your project or commit your current changes.
2. Extract this ZIP directly into the Laravel project root.
3. Allow the files above to overwrite the existing copies.
4. Run:

```powershell
php artisan optimize:clear
php artisan view:clear
npm run build
```

5. Optional targeted verification:

```powershell
php artisan test tests/Feature/PhysicalFinancialTableFirstReportTest.php
```

6. Start your normal development command:

```powershell
composer run dev
```

## Verify in the UI

Open:

`Reports → Physical & Financial`

Confirm that the top selector contains only Overall, Per Semester, Per Quarter, and Per Month. Confirm that Short-Term and Long-Term report cards are gone. Open Print/PDF and verify Letter portrait output.

## Reporting basis used

- Physical target: encoded project beneficiaries.
- Physical accomplishment: beneficiaries on projects currently marked Completed.
- Financial target: encoded total project cost.
- Financial accomplishment: recorded disbursements.
- Balance: target less accomplishment.
- Semester/quarter/month grouping basis: `projects.date_received`.
