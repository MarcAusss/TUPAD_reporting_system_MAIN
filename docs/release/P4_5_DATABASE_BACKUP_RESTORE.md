# P4.5 — Database, Migration, Seed, Backup & Restore Finalization

## Migration policy

Production upgrades use:

```bash
php artisan migrate --force
```

Do **not** use any of the following against production data:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

## Fresh-install verification

On a disposable QA database only:

```bash
php artisan migrate:fresh --seed
php artisan test --filter=FreshDatabaseBaselineTest
php artisan projects:canonicalize-locations --dry-run
php artisan tupad:release-verify
```

This proves the application can still be installed from migrations and seed/reference data. It is not a production-upgrade procedure.

## Production pre-deployment backup

Example MySQL command:

```bash
mysqldump --single-transaction --routines --triggers --events -u YOUR_USER -p tupad_reporting > tupad_reporting_pre_release.sql
```

Verify the dump is non-empty and copy it to the approved backup location before applying migrations.

## Restore rehearsal

Restore into a separate QA database, never directly over the live database during a rehearsal:

```bash
mysql -u YOUR_USER -p -e "CREATE DATABASE tupad_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u YOUR_USER -p tupad_restore_test < tupad_reporting_pre_release.sql
```

Point a QA `.env` at `tupad_restore_test`, clear caches, and run:

```bash
php artisan optimize:clear
php artisan tupad:release-verify
```

## Data-integrity checks

- canonical project locations are complete;
- municipality/province hierarchy is valid;
- barangay allocations equal project beneficiary totals where encoded;
- female counts never exceed totals;
- ADL/allocation/project financial totals reconcile;
- workflow status history exists for official transitions;
- ACP payments/check releases/liquidations remain mode-specific;
- audit records exist for auditable mutations.

## Seeding policy

Production must use only approved reference/bootstrap seed data. Demo/test users or sample project records must not be introduced into a live database unless explicitly authorized for training in an isolated environment.
