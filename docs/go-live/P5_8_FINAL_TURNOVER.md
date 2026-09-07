# P5.8 — Final Turnover Package

## System identity

**TUPAD Reporting System — DOLE Regional Office V**

Live account roles:

1. Administrator
2. Focal
3. TUPAD Coordinator (TC)

The former GIP user/draft workflow is retired. Fund realignment wording such as **TUPAD to GIP** is retained only where it represents the existing financial/program realignment concept and is not an account role.

## Core project workflow

New project:

`Ongoing Profiling -> TSSD Evaluation -> For Approval -> Approved`

`For Compliance` is an optional TSSD branch before For Approval.

After approval the workflow separates according to Direct Administration or Through ACP business rules already implemented in the system.

The project detail page provides a top/sticky quick workflow action and modal for early progression so staff do not need to scroll through the full project workspace to perform the next authorized action.

## Turnover artifacts

- Phase 4 release/UAT documentation in `docs/release/`.
- Phase 5 deployment/go-live documentation in `docs/go-live/`.
- `.env.production.example` production configuration template.
- `P5_PREDEPLOY_CHECK.cmd` release-machine gate.
- `P5_DEPLOY_PRODUCTION.cmd` controlled deployment procedure.
- `P5_BACKUP_DATABASE.cmd` and `P5_RESTORE_DATABASE.cmd` database operations.
- `P5_SMOKE_CHECK.cmd` post-deployment automated gate.
- `tupad:reference-data-sync` clean Region V production reference loader.
- `tupad:initial-admin` controlled first-Administrator bootstrap.
- `tupad:phase5-audit --production` final release gate.
- `tupad:production-health` operational health command.

## Final sign-off

| Approval item | Name/Role | Date | Signature/Remarks |
|---|---|---|---|
| Technical deployment verified | | | |
| Database backup verified | | | |
| Administrator access verified | | | |
| Focal access verified | | | |
| TC province access verified | | | |
| Workflow smoke test passed | | | |
| Official reports/signatories verified | | | |
| Agency/UAT acceptance | | | |
| Production go-live approved | | | |

Retain the signed copy according to the office's records-management policy.
