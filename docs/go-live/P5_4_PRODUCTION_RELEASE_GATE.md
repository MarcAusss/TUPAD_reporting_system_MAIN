# P5.4 — Strict Production Release Gate

Before opening production access run:

```bash
php artisan tupad:phase5-audit --production --report=storage/app/release-audits/p5-production.json
```

This includes the Phase 4 security/data-integrity gate and adds Phase 5 checks for:

- exact live assignable roles: Administrator, Focal, TC;
- absence of the retired GIP role and project-draft routes;
- an active Administrator;
- complete reviewed Region V geographic reference data;
- initial project workflow identity: Ongoing Profiling -> TSSD Evaluation;
- configured official report signatories;
- deployment, backup, restore, smoke-test, and handover assets;
- production HTTPS/debug/session/database/build controls inherited from Phase 4.

A blocking failure means **do not open production access**. Warnings must be reviewed and documented in the final sign-off.
