# P5.6 — Backup and Recovery

## Database backup

Use:

```powershell
.\P5_BACKUP_DATABASE.cmd
```

or:

```bash
php artisan tupad:backup-database
```

The command uses MySQL `mysqldump`, streams the SQL file to the configured backup directory, and removes SQL backups older than `TUPAD_BACKUP_RETENTION_DAYS` (default 14 days). The database password is passed to the child process through `MYSQL_PWD` instead of being printed in the command line.

A production backup strategy should also copy backup files to a protected secondary location that is not on the same disk as the live application.

## Restore

A restore is destructive. Before restoring:

1. Identify the exact approved backup.
2. Put the application in maintenance mode: `php artisan down`.
3. Create a separate current-state backup if the database is readable.
4. Confirm the target database and environment.
5. Run `P5_RESTORE_DATABASE.cmd path\to\backup.sql`.
6. Run `php artisan optimize:clear`.
7. Run `php artisan tupad:phase5-audit --production`.
8. Run the post-restore smoke checklist.
9. Only then run `php artisan up`.

The restore command requires the literal `--confirm=RESTORE` authorization and is never called by the normal deployment script.

## Minimum operational policy

- Daily database backup.
- Retain at least 14 days unless agency policy requires longer.
- Keep at least one off-server/off-disk copy.
- Test a restore periodically in a controlled non-production environment.
- Back up `.env` securely outside the web root, but never place it in source control.
