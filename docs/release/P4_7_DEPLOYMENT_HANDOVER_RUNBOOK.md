# P4.7 — Deployment & Technical Handover Runbook

## Server baseline

- PHP 8.3+ compatible with the project (current development baseline uses PHP 8.5.x)
- required PHP extensions for Laravel/MySQL/file handling
- Composer 2.x
- Node.js/npm on the build machine
- MySQL 8
- HTTPS-capable web server

## Deployment sequence

1. Back up the current application files and MySQL database.
2. Put the application into maintenance mode when required by the deployment window.
3. Deploy the reviewed release code.
4. Install PHP dependencies:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

5. Configure `.env` from `.env.production.example`. Never overwrite an existing production `APP_KEY`.
6. Build assets on the release/build machine:

```bash
npm ci
npm run build
```

7. Apply database migrations:

```bash
php artisan migrate --force
```

8. Clear and rebuild Laravel caches:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

9. Run production release gates:

```bash
php artisan tupad:release-verify --production
php artisan tupad:phase4-audit --production --report=storage/app/release-audits/production.json
```

10. Confirm `/up` health endpoint and login page.
11. Complete smoke tests using Administrator, Focal, and province-assigned TC accounts; confirm the retired GIP role/draft routes are absent.
12. Exit maintenance mode if it was enabled.

## Required environment values before handover

- production URL and database credentials;
- secure session configuration;
- current report version/revision;
- approved report signatory names/positions/offices;
- dashboard aging thresholds if the default 7/14 operational indicators are changed;
- mail transport if system email delivery is used.

## File permissions

The web/PHP process requires write permission to:

- `storage/`
- `bootstrap/cache/`

Do not make the entire repository world-writable.

## Rollback principle

If release verification fails after deployment:

1. stop user traffic/enable maintenance mode;
2. preserve logs and the Phase 4 JSON report;
3. restore the pre-release application/database backup according to the approved change procedure;
4. do not improvise a destructive `migrate:fresh` repair against production;
5. investigate in a cloned QA environment.

## Handover evidence

Deliver:

- release ZIP/source revision;
- database backup reference;
- `storage/app/release-audits/production.json`;
- full test result;
- completed P4.6 UAT checklist;
- configured role/account list excluding passwords;
- infrastructure owner/contact information;
- backup/restore owner and schedule.
