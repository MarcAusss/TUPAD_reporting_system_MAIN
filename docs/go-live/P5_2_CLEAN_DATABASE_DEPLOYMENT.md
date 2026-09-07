# P5.2 — Clean Production Database Deployment

The production database must not be initialized with the development FY2025 project seeder.

## New database

Create the empty MySQL database and production DB account, then from the application root run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan tupad:reference-data-sync
php artisan tupad:initial-admin
```

`tupad:reference-data-sync` is idempotent and loads only the reviewed Region V province, municipality/city, and barangay reference rows from the application's local reviewed GeoJSON data. It does not create ADLs, projects, payments, or demo accounts.

`tupad:initial-admin` is also guarded. If an active Administrator already exists it makes no change. On a clean database it reads `TUPAD_INITIAL_ADMIN_*`, creates one Administrator with a generated temporary password, and forces password change on first login.

## Existing database upgrade

Before any migration:

```bash
php artisan tupad:backup-database
php artisan down
php artisan migrate --force
php artisan tupad:reference-data-sync
php artisan tupad:phase5-audit --production
```

Do not run the old GIP draft workflow or restore retired GIP tables. The Phase 5 gate treats those as release failures.
