# P5.7 — Operational Monitoring

The application includes:

```bash
php artisan tupad:production-health
```

It checks database connectivity, pending migrations, active Administrator availability, retired-role integrity, TC province assignments, writable runtime directories, free disk space, Vite build presence, and pending/failed database queue jobs. A JSON snapshot is written to the configured health report path.

A daily scheduled health check is registered for 06:15 Asia/Manila. The existing project-status synchronization remains scheduled at 00:05 Asia/Manila.

The server must execute Laravel's scheduler every minute. On Linux this is typically a cron entry running `php artisan schedule:run`. On Windows Server use Task Scheduler to execute the same command every minute from the project directory.

## Daily review

- `storage/logs` for application exceptions.
- `storage/app/health/latest.json` for the automated health result.
- `storage/app/release-audits` after releases.
- Failed jobs, if any queue-backed features are enabled.
- Available disk space and database backup completion.
- Audit Trail for sensitive account/workflow changes.

## Incident rule

For database corruption, failed migration, authorization bypass, or repeated 500 errors, place the application in maintenance mode, preserve logs, take a backup if safe, and investigate before reopening access.
