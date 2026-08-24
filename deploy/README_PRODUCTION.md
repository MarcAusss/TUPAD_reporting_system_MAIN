# TUPAD Reporting System — Phase 13.5 Production Configuration

This overlay does **not** overwrite `.env`, application controllers, models, migrations, routes, or views. It adds deployment/release tooling and a production environment template.

## 1. Production prerequisites

Minimum practical requirements:

- PHP matching `composer.json` (`^8.3` in the inspected project)
- Required PHP extensions for Laravel/MySQL
- Composer 2
- MySQL 8.x
- Node.js/npm only if frontend assets are built on the production host
- Web server configured so the document root is the Laravel `public/` directory
- HTTPS enabled before production use
- A process/scheduler mechanism for Laravel scheduled commands

The application currently exposes Laravel's health route at `/up` and schedules `projects:sync-implementation-statuses` daily at `00:05`.

## 2. Environment file

Copy `.env.production.example` to `.env` on the production host and fill real values. Never commit the real `.env`.

Critical values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-domain
APP_KEY=<generated Laravel key>
```

Generate the key only for a brand-new production environment:

```bash
php artisan key:generate
```

Do not regenerate `APP_KEY` after encrypted production data/sessions are in use.

Use a dedicated MySQL account with access only to the TUPAD application database.

## 3. Database-backed framework tables

The inspected project uses database-backed sessions/cache/queue defaults. Ensure the required framework migrations are present and have run before switching these stores on.

Check with:

```bash
php artisan migrate:status
```

## 4. Deployment

Windows PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy\deploy-production.ps1
```

Linux/macOS shell:

```bash
chmod +x deploy/deploy-production.sh
./deploy/deploy-production.sh
```

Both scripts:

1. Validate production environment flags.
2. Put the application in maintenance mode.
3. Install optimized PHP dependencies.
4. Build frontend assets unless skipped.
5. Run migrations with `--force`.
6. Create the storage symlink if needed.
7. Clear stale caches and run Laravel optimization.
8. Restart queue workers if available.
9. Bring the application back online even when a deployment step fails.

## 5. Scheduler

Laravel must run `schedule:run` every minute so the existing daily project status synchronization can execute.

### Windows Task Scheduler

Create a task that runs every minute:

```text
Program/script: C:\path\to\php.exe
Arguments: artisan schedule:run
Start in: C:\path\to\tupad-reporting-system
```

Run whether the user is logged on or not. Use a service account that can read the application and write to `storage/` and `bootstrap/cache/`.

### Linux cron

```cron
* * * * * cd /var/www/tupad-reporting-system && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Queue worker

If `QUEUE_CONNECTION=database` is used for actual queued jobs, run a persistent worker under a service manager rather than a manually opened terminal.

Example command:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

If the application currently has no asynchronous queued work, the worker can be introduced when needed.

## 7. Writable directories

The web/PHP process must be able to write to:

```text
storage/
bootstrap/cache/
```

Do not make the entire project globally writable.

## 8. Post-document attachments

The current project stores protected project documents through Laravel rather than exposing arbitrary storage paths directly. Keep the private application storage outside direct public web access. Use `php artisan storage:link` only for files intentionally served through the public disk.

## 9. Backups

Before every production migration/deployment, make a verified database backup and preserve uploaded/private project documents. A backup is not complete until a restore has been tested in a non-production environment.

At minimum retain:

- MySQL database dump
- `storage/app/` project files
- production `.env` through a secure secrets/backup mechanism

Do not place database dumps or `.env` files inside the public web directory.

## 10. Logs

Production template uses:

```env
LOG_STACK=daily
LOG_LEVEL=warning
```

Monitor `storage/logs/` and establish rotation/retention appropriate for the server.

## 11. Final verification

Before release:

```powershell
.\deploy\release-verify.ps1 -Production
```

or:

```bash
./deploy/release-verify.sh --production
```

Then complete `deploy/RELEASE_CHECKLIST.md`.
