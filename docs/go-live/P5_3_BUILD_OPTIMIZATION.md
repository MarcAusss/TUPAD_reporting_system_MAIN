# P5.3 — Production Build and Laravel Optimization

## Release machine

Before deployment, run:

```powershell
.\P5_PREDEPLOY_CHECK.cmd
```

This clears stale caches, runs the full PHPUnit suite with stop-on-failure, performs a clean npm install/build, and writes a pre-deployment Phase 5 audit report.

## Production server

The bundled `P5_DEPLOY_PRODUCTION.cmd` performs the controlled deployment sequence. The production optimization commands are:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

The production server should not need PHPUnit or development packages after deployment. Run the full test suite on the release/UAT machine before copying the approved build.

If environment values are changed after `config:cache`, run `php artisan optimize:clear` and regenerate the caches.
