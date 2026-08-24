# Phase 14 — FY2025 Workbook Alignment

## Role ownership

**Focal / Admin**
- ADL, NFA, NTA
- Allocation and LCE/Party-list
- Re-alignment / MAF
- PER ADL (Current)
- SUMMARY
- SUMMARY (Current)
- PER PROVINCE (Current)
- Payment / obligation remains in the existing workflow

**TC / Admin**
- Official Projects
- Provincial project monitoring sheets (ALBAY-style shared implementation for all six Region V provinces)
- Beneficiary registry and demographics
- TSSD evaluation, approval, implementation, post-docs and payout

**GIP**
- Existing draft-only workflow remains unchanged.

## Spreadsheet architecture

The workbook sheets are represented as views over normalized records rather than copied into independent tables:

`ADL -> Allocations -> Projects -> Beneficiaries / Workflow -> PER ADL -> PER PROVINCE -> SUMMARY`

This avoids entering the same total in multiple places.

## Status bucket mapping

- IMSD / Payment = `for_payment`
- Implemented / For Submission of Post-Docs = `for_submission_of_post_docs`
- Ongoing Implementation = `ongoing_implementation`
- With NTP / For Implementation = `for_implementation`
- Approved = `approved`
- For Approval = `for_approval`
- Under Evaluation = `tssd_evaluation` + `for_compliance`

## Beneficiary approach

Individual beneficiary data is retained. Youth (15–30) and Senior Citizen (60+) are derived from birth date. PWD and Rebel Returnee are explicit flags. Optional beneficiary `grant_amount` supports the workbook's demographic amount columns.
