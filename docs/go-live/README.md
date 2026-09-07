# TUPAD Phase 5 — Production Deployment and Go-Live

This folder is the final operational handover set for the TUPAD Reporting System after the GIP account/draft workflow was retired.

Live roles are **Administrator**, **Focal**, and **TUPAD Coordinator (TC)**. New official projects begin in **Ongoing Profiling** and the first workflow transition is **TSSD Evaluation**.

## Release sequence

1. `P5_1_PRODUCTION_ENVIRONMENT.md`
2. `P5_2_CLEAN_DATABASE_DEPLOYMENT.md`
3. `P5_3_BUILD_OPTIMIZATION.md`
4. `P5_4_PRODUCTION_RELEASE_GATE.md`
5. `P5_5_GO_LIVE_SMOKE_TEST.md`
6. `P5_6_BACKUP_RECOVERY.md`
7. `P5_7_OPERATIONS_MONITORING.md`
8. `P5_8_FINAL_TURNOVER.md`

Do not use `migrate:fresh`, `migrate:refresh`, `db:wipe`, or the FY2025 development seeder on a production database.
