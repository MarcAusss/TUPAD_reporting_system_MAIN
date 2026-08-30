# TUPAD Fresh Baseline Corrective Fix

This corrective overlay fixes the migration collision where Laravel still runs default or historical migration files after the four clean baseline migrations have been copied into the project.

## Why the error happened

ZIP extraction copies/replaces files but does not delete unrelated files already in `database/migrations`. The old Laravel defaults such as:

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`

can therefore remain beside the four clean baseline migrations. The clean baseline already creates `users`, `cache`, jobs, and related tables, so the old defaults must be removed.

## Apply

Extract this ZIP directly into the Laravel project root and replace matching files.

Run from the project root:

```powershell
.\FIX_FRESH_BASELINE.cmd
```

The script:

1. Deletes every PHP migration except the four clean baseline migrations.
2. Verifies exactly four migrations remain.
3. Deletes every seeder except `DatabaseSeeder.php` and `Fy2025TupadProjectSeeder.php`.
4. Removes the retired beneficiary/payout backend files included in the cleanup plan.
5. Clears Laravel caches.
6. Regenerates Composer autoload.
7. Runs `php artisan migrate:fresh --seed`.

This is destructive by design.
