# Phase 4 Release Readiness Package

This directory is the final UAT, deployment, and handover package for the TUPAD Reporting System.

## Phase 4 scope

- **P4.1** Full system regression and route/access audit
- **P4.2** Forms and validation UAT
- **P4.3** Reports, print, and export final QA
- **P4.4** Security and production configuration audit
- **P4.5** Database, migration, seed, backup, and restore finalization
- **P4.6** Role-based User Acceptance Test checklist
- **P4.7** Deployment and technical handover runbook
- **P4.8** Final system functions and user manual

## Automated commands

Development/UAT gate:

```bash
php artisan optimize:clear
php artisan tupad:phase4-audit --report=storage/app/release-audits/latest.json
php artisan test
npm run build
```

Production gate after deployment environment variables are configured:

```bash
php artisan optimize:clear
php artisan tupad:release-verify --production
php artisan tupad:phase4-audit --production --report=storage/app/release-audits/production.json
```

On Windows, `P4_RELEASE_CHECK.cmd` runs the UAT gate. Run `P4_RELEASE_CHECK.cmd production` on a deployment environment that has already been configured for production.

The audit commands are non-destructive. They do not run `migrate:fresh`, reseed the database, delete records, or change workflow state.
