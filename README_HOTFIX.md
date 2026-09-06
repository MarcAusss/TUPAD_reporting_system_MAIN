# TUPAD Reformulated Target — ProjectDraft Boot Hotfix

This overlay corrects `app/Providers/AppServiceProvider.php` after the Reformulated Target overlay accidentally restored a retired `ProjectDraft` model reference.

## Fixes
- Removes `use App\Models\ProjectDraft;`
- Removes `ProjectDraft::class` from audit observer registration
- Restores the current ACP audit models:
  - `ProjectAcpPayment`
  - `ProjectAcpCheckRelease`
  - `ProjectAcpLiquidation`
- Keeps `ReformulatedTarget` registered with `AuditObserver`

## Install
Extract directly over the TUPAD project root and replace the existing file.

Then run:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
```

Do not run `migrate:fresh`.
