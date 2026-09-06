# P5.1 — Production Environment Preparation

## Required runtime

- PHP compatible with `composer.json` (`^8.3`)
- Composer 2
- MySQL 8+
- Node.js/npm for the production Vite build
- Web server configured to serve Laravel's `public/` directory
- HTTPS certificate before public or agency-network go-live
- MySQL client tools `mysqldump` and `mysql` in PATH for the bundled backup/restore commands

## Environment procedure

1. Copy `.env.production.example` to `.env` on the deployment server.
2. Generate and retain a unique application key: `php artisan key:generate`.
3. Set the actual HTTPS `APP_URL`.
4. Configure a least-privilege MySQL user and a dedicated production database.
5. Keep `APP_ENV=production` and `APP_DEBUG=false`.
6. Keep `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` when HTTPS is enabled.
7. Fill all official report signatory names/positions/offices before go-live.
8. For a new installation only, fill `TUPAD_INITIAL_ADMIN_*`. The values can be removed after the initial Administrator is created.
9. Confirm the backup directory has enough protected disk space.
10. Never commit the production `.env` or database credentials.

## Live access model

Only three account roles are assignable:

- Administrator — regional/system administration and complete authorized workflow access.
- Focal — regional financial/reporting and TC-account management functions.
- TUPAD Coordinator — province-scoped operational project workflow.

The former GIP system role is retired. Historical retired account rows may exist only for referential/audit preservation and cannot sign in.
