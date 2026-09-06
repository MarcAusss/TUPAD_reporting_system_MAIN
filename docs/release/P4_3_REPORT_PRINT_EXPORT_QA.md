# P4.3 — Reports, Print & Export Final QA

## Report workspaces to inspect

- Physical & Financial Accomplishment
- Fund Status
- Monthly Reports
- Quarterly Reports
- Geographic Mapping
- Statistical Performance / SPRS outputs
- Orientation outputs
- CQPR outputs
- Beneficiary sector/intervention-focus reports
- Active Labor Market referrals

## Data consistency test

For the same filter selection, compare browser view against exports. Values must come from the same filtered cohort.

Check:

- project counts;
- beneficiary totals and female totals;
- financial target/cost values;
- obligation/disbursement values;
- allocation and balance values;
- province/municipality/barangay filters;
- implementation mode filter;
- fiscal/month/quarter period filter.

## Print/PDF QA

- Letter-size orientation is correct for the report family.
- DOLE Regional Office V letterhead renders without overlapping content.
- table header repeats where appropriate;
- table does not clip essential numeric columns;
- TOTAL/GRAND TOTAL values align with the screen data;
- document reference is present;
- document version/revision is present;
- generation timestamp and generating user are present;
- Prepared by / Reviewed by / Approved by blocks render;
- blank signatory configuration shows signature lines rather than fabricated names.

## CSV/XLSX QA

- file opens without repair warnings;
- headings match the selected report;
- numeric fields remain numeric where expected;
- no HTML is embedded in cells;
- document-control metadata is included;
- selected filters are reflected in output.

## Geographic QA

- Focal/Admin: Region V overview is visible.
- TC: only assigned province data is visible.
- province/municipality drill-down does not expose foreign-province records.
- map totals match the table below the map.

## Pass criteria

All report-generation tests pass and a human print preview is signed off for each report family used operationally.
