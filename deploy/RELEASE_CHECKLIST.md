# TUPAD Reporting System — Phase 13.6 Final Release Verification

Do not mark the release complete until every applicable item below is verified.

## A. Automated regression

- [ ] `php artisan test` passes completely.
- [ ] Current expected regression baseline is at least **39 tests / 72 assertions** from the completed Phase 13.1–13.3 work.
- [ ] `npm run build` succeeds.
- [ ] `composer validate --no-check-publish` succeeds.
- [ ] `php artisan migrate:status` contains no unexpected pending migration.
- [ ] `php artisan optimize` succeeds.
- [ ] `php artisan route:list` succeeds.
- [ ] `php artisan schedule:list` shows the project implementation-status synchronization command.

## B. Production environment

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` is the real HTTPS production URL.
- [ ] `APP_KEY` is set and securely retained.
- [ ] Production `.env` is not committed to Git.
- [ ] MySQL uses a dedicated application account rather than an unrestricted administrative account.
- [ ] Database credentials are not present in source-controlled files.
- [ ] Session cookies are secure when HTTPS is enabled.
- [ ] Production log level is not `debug`.

## C. Server/runtime

- [ ] Web server document root points to `public/` only.
- [ ] HTTPS certificate is valid.
- [ ] `storage/` is writable by PHP/web process.
- [ ] `bootstrap/cache/` is writable by PHP/web process.
- [ ] `vendor/` dependencies were installed with `--no-dev --optimize-autoloader`.
- [ ] Frontend production build exists under `public/build/`.
- [ ] Laravel `/up` health endpoint returns a successful response.

## D. Scheduler/queue

- [ ] `php artisan schedule:list` is reviewed.
- [ ] Server executes `php artisan schedule:run` every minute.
- [ ] `projects:sync-implementation-statuses` is visible in the schedule.
- [ ] Queue worker is configured if asynchronous jobs are used.
- [ ] Queue worker can be restarted after deployments.

## E. Database/data integrity

- [ ] Latest production database backup completed before migration.
- [ ] Backup restoration was previously tested outside production.
- [ ] All application migrations are `Ran` after deployment.
- [ ] Geographic reference data exists.
- [ ] Existing project location references are backfilled.
- [ ] ADL/allocation totals reviewed for obvious inconsistencies.
- [ ] Approved project codes remain unique.
- [ ] Project beneficiary totals and registry behavior were spot-checked.

## F. Role/access smoke test

Test with representative accounts:

### Administrator
- [ ] Can reach Dashboard.
- [ ] Can access Administration/User Management.
- [ ] Can access official project management.
- [ ] Can access reports.

### TUPAD Coordinator (TC)
- [ ] Can create/manage official projects.
- [ ] Can manage beneficiary registry during profiling.
- [ ] Can review GIP drafts.
- [ ] Can process evaluation/approval/implementation/post-docs/payout as designed.
- [ ] Cannot access Focal-only ADL management.

### GIP
- [ ] Can create/edit own draft records.
- [ ] Can submit drafts to assigned TC.
- [ ] Cannot directly create official projects.
- [ ] Cannot access official reports.
- [ ] Cannot download official project documents.

### Focal
- [ ] Can manage ADL/allocation/re-alignment.
- [ ] Can access payment queue.
- [ ] Cannot create official projects.
- [ ] Can view/download project records only when workflow allows.

### Inactive account
- [ ] Cannot authenticate even with correct password.

## G. End-to-end business workflow smoke test

Use test/non-sensitive data where possible:

- [ ] ADL created.
- [ ] Allocation created without exceeding available adjusted grants.
- [ ] Optional re-alignment calculation is correct when used.
- [ ] Official project created by TC.
- [ ] Province → Municipality → Barangay selection works.
- [ ] District/Income Class behavior is correct for available reference data.
- [ ] Wage calculation is correct.
- [ ] PPE calculation is correct.
- [ ] Insurance calculation is correct.
- [ ] Total Project Cost = Wages + PPE + Insurance.
- [ ] Beneficiary registry cannot exceed declared total.
- [ ] Incomplete beneficiary registry blocks TSSD submission.
- [ ] Complete registry allows TSSD submission.
- [ ] TSSD Evaluation → For Approval works.
- [ ] Approval generates/accepts a unique Project Code.
- [ ] Implementation preparation records can be completed.
- [ ] Implementation period transitions are correct.
- [ ] Post-document record/attachment workflow works.
- [ ] Focal obligation/payment workflow works.
- [ ] TC/Admin payout workflow works.
- [ ] Completed project displays correctly.
- [ ] Status history records transitions.
- [ ] Audit logging records applicable changes.

## H. GIP delegated workflow smoke test

- [ ] GIP creates draft.
- [ ] Draft location dropdown hierarchy works.
- [ ] GIP submits to TC.
- [ ] TC can return draft for correction.
- [ ] GIP can edit/resubmit returned draft.
- [ ] TC confirmation creates the official project.
- [ ] Confirmed project preserves normalized location IDs.
- [ ] Unconfirmed drafts remain excluded from official totals/reports.

## I. Reports/export

- [ ] Reports page loads for Admin/TC/Focal.
- [ ] Province filter works.
- [ ] Municipality filter works.
- [ ] Barangay filter works.
- [ ] Status/date filters work.
- [ ] Beneficiary totals are correct.
- [ ] Registered beneficiary count is correct.
- [ ] Financial summary is correct.
- [ ] CSV export respects filters.
- [ ] Printable report respects filters.
- [ ] GIP cannot access official reports.

## J. Error and recovery UI

- [ ] 403 page renders correctly.
- [ ] 404 page renders correctly while signed in.
- [ ] 404 page renders correctly while signed out.
- [ ] 419/session-expired page renders correctly.
- [ ] 500 page renders with `APP_DEBUG=false`.
- [ ] Validation errors preserve form input where appropriate.
- [ ] Success/error/warning alerts are readable and consistent.

## K. Documents/security

- [ ] Cross-project beneficiary URLs cannot access another project's beneficiary.
- [ ] Cross-project document URLs cannot download another project's file.
- [ ] Private attachment paths are not directly exposed by the web server.
- [ ] No real `.env`, database dump, credentials, or API keys exist inside `public/`.
- [ ] Production directory listing is disabled by the web server.

## L. Release record

Record the following outside this checklist or in your deployment/change log:

- Release version/tag:
- Git commit:
- Deployment date/time:
- Deployed by:
- Database backup reference:
- Migration result:
- Automated test result:
- Smoke-test reviewer:
- Rollback point/reference:
- Known deferred items:

### Known intentionally deferred items from the project specification

- Official Municipality-to-Income-Class dataset, until supplied.
- Final Project Code numbering convention, until officially defined.
- Final PPE Inventory System integration method, until the external system is reviewed/integration is resumed.
- Any later business-rule refinements explicitly deferred by the project owner.
