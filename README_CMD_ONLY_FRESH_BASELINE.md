# TUPAD Fresh Baseline - CMD Only Fix

This corrective package avoids PowerShell completely.

## Why the previous attempt failed

`APPLY_FRESH_BASELINE.ps1` was blocked by the Windows PowerShell execution policy. Because that script never ran, old migration files such as `0001_01_01_000000_create_users_table.php` remained in `database/migrations`. Running `php artisan migrate:fresh --seed` directly then caused a duplicate `users` table collision.

## Apply

Extract this overlay into the Laravel project root, replacing matching files.

Then run from Command Prompt or PowerShell:

```bat
.\RESET_FRESH_BASELINE.cmd
```

The `.cmd` file does not invoke PowerShell and is not affected by PowerShell execution policies.

It will:

1. Delete every migration except the four clean baseline migrations.
2. Verify exactly four migrations remain.
3. Delete every seeder except `DatabaseSeeder.php` and `Fy2025TupadProjectSeeder.php`.
4. Remove proven-retired beneficiary/payout backend leftovers.
5. Run `php artisan optimize:clear`.
6. Run `composer dump-autoload -o` when Composer is available.
7. Run `php artisan migrate:fresh --seed`.

## Expected migration directory

- `0001_01_01_000000_create_platform_geography_and_users.php`
- `0001_01_01_000100_create_funding_and_project_core.php`
- `0001_01_01_000200_create_project_workflow_and_payments.php`
- `0001_01_01_000300_create_classification_acp_and_audit.php`

Do not run the old `APPLY_FRESH_BASELINE.ps1` for this reset.
