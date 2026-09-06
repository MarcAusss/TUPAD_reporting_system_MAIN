<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class Phase4ReleaseAudit extends Command
{
    protected $signature = 'tupad:phase4-audit
        {--production : Enforce production-only release gates}
        {--report= : Optional JSON report path, relative to the project root or absolute}';

    protected $description =
        'Run the Phase 4 non-destructive release-readiness audit for deployment, UAT, and handover.';

    /** @var array<int, array{area:string,check:string,result:string,detail:string}> */
    private array $checks = [];

    private int $failures = 0;

    private int $warnings = 0;

    private bool $production = false;

    public function handle(): int
    {
        $this->production = app()->environment('production') || (bool) $this->option('production');

        $this->info('TUPAD Phase 4 release-readiness audit');
        $this->line('Mode: '.($this->production ? 'PRODUCTION GATE' : 'UAT / PRE-RELEASE'));
        $this->newLine();

        $this->runCoreReleaseVerifier();
        $this->verifyDatabaseAndMigrations();
        $this->verifyRuntimeDirectories();
        $this->verifyCriticalRoutes();
        $this->verifyOperationalAccounts();
        $this->verifyReportingAndDashboardConfiguration();
        $this->verifyProductionConfiguration();
        $this->verifyRepositoryCleanliness();
        $this->verifyBuildAssets();

        $this->newLine();
        $this->table(
            ['Area', 'Check', 'Result', 'Detail'],
            array_map(
                static fn (array $check): array => [
                    $check['area'],
                    $check['check'],
                    $check['result'],
                    $check['detail'],
                ],
                $this->checks,
            ),
        );

        $this->writeJsonReportIfRequested();

        $this->newLine();
        $this->line(sprintf(
            'Summary: %d check(s), %d warning(s), %d blocking failure(s).',
            count($this->checks),
            $this->warnings,
            $this->failures,
        ));

        if ($this->failures > 0) {
            $this->error('Phase 4 release-readiness audit FAILED. Resolve all blocking failures before deployment.');

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn('Phase 4 release-readiness audit PASSED with warnings. Review them before production deployment.');
        } else {
            $this->info('Phase 4 release-readiness audit PASSED with no warnings.');
        }

        return self::SUCCESS;
    }

    private function runCoreReleaseVerifier(): void
    {
        $exitCode = $this->call('tupad:release-verify', [
            '--production' => $this->production,
        ]);

        if ($exitCode === self::SUCCESS) {
            $this->pass('Core', 'Existing release verifier', 'Schema, security, financial, beneficiary, location, workflow, and audit checks passed.');

            return;
        }

        $this->recordFailure('Core', 'Existing release verifier', 'The existing tupad:release-verify command reported one or more blocking issues.');
    }

    private function verifyDatabaseAndMigrations(): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $this->pass('Database', 'Connection', 'Database connection is available.');
        } catch (Throwable $exception) {
            $this->recordFailure('Database', 'Connection', 'Database connection failed: '.$exception->getMessage());

            return;
        }

        if (! Schema::hasTable('migrations')) {
            $this->recordFailure('Database', 'Migration repository', 'The migrations table does not exist.');

            return;
        }

        $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->values();

        $ranMigrations = DB::table('migrations')
            ->pluck('migration')
            ->map(static fn ($migration): string => (string) $migration);

        $pending = $migrationFiles->diff($ranMigrations)->values();

        if ($pending->isEmpty()) {
            $this->pass('Database', 'Pending migrations', 'All migration files are recorded as applied.');
        } else {
            $this->recordFailure(
                'Database',
                'Pending migrations',
                'Pending: '.$pending->take(8)->implode(', ').($pending->count() > 8 ? ' …' : ''),
            );
        }

        foreach ([
            'users',
            'adls',
            'adl_allocations',
            'projects',
            'project_locations',
            'project_status_histories',
            'audit_logs',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $this->pass('Database', "Required table: {$table}", 'Present.');
            } else {
                $this->recordFailure('Database', "Required table: {$table}", 'Missing.');
            }
        }

        foreach (['project_drafts', 'project_draft_ppe_items'] as $retiredTable) {
            if (Schema::hasTable($retiredTable)) {
                $this->recordFailure('Database', "Retired table: {$retiredTable}", 'Still present. Apply the GIP retirement migration.');
            } else {
                $this->pass('Database', "Retired table: {$retiredTable}", 'Absent as expected.');
            }
        }

        if (Schema::hasColumn('users', 'supervisor_tc_id')) {
            $this->recordFailure('Database', 'Retired users.supervisor_tc_id', 'Still present. Apply the GIP retirement migration.');
        } else {
            $this->pass('Database', 'Retired users.supervisor_tc_id', 'Absent as expected.');
        }
    }

    private function verifyRuntimeDirectories(): void
    {
        foreach ([
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $path) {
            $label = Str::after($path, base_path().DIRECTORY_SEPARATOR);

            if (! is_dir($path)) {
                $this->recordFailure('Runtime', $label, 'Required writable directory is missing.');
                continue;
            }

            if (! is_writable($path)) {
                $this->recordFailure('Runtime', $label, 'Directory exists but is not writable by the current process.');
                continue;
            }

            $this->pass('Runtime', $label, 'Writable.');
        }
    }

    private function verifyCriticalRoutes(): void
    {
        $matrix = [
            'dashboard' => ['auth', 'password.changed', 'province.scope'],
            'notifications.index' => ['auth', 'password.changed', 'province.scope'],
            'audit.index' => ['auth', 'password.changed', 'province.scope', 'role:admin'],
            'users.index' => ['auth', 'password.changed', 'province.scope', 'role:admin,focal'],
            'projects.index' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc,focal'],
            'project-workflow.index' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc'],
            'payments.index' => ['auth', 'password.changed', 'province.scope', 'role:admin,focal'],
            'acp-workflow.payment' => ['auth', 'password.changed', 'province.scope', 'role:admin,focal'],
            'reports.index' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc,focal'],
            'reports.export.pdf' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc,focal'],
            'reports.export.excel' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc,focal'],
            'reports.export.csv' => ['auth', 'password.changed', 'province.scope', 'role:admin,tc,focal'],
        ];

        foreach ($matrix as $routeName => $requiredMiddleware) {
            $route = Route::getRoutes()->getByName($routeName);

            if ($route === null) {
                $this->recordFailure('Routes', $routeName, 'Required route is missing.');
                continue;
            }

            $actualMiddleware = $route->gatherMiddleware();
            $missingMiddleware = array_values(array_filter(
                $requiredMiddleware,
                static fn (string $middleware): bool => ! in_array($middleware, $actualMiddleware, true),
            ));

            if ($missingMiddleware !== []) {
                $this->recordFailure(
                    'Routes',
                    $routeName,
                    'Missing middleware: '.implode(', ', $missingMiddleware),
                );
                continue;
            }

            $this->pass('Routes', $routeName, 'Route and expected access middleware are present.');
        }
    }

    private function verifyOperationalAccounts(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $activeAdmins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('is_active', true)
            ->count();

        if ($activeAdmins > 0) {
            $this->pass('Accounts', 'Active Administrator', "{$activeAdmins} active Administrator account(s) found.");
        } elseif ($this->production) {
            $this->recordFailure('Accounts', 'Active Administrator', 'Production requires at least one active Administrator account.');
        } else {
            $this->warnCheck('Accounts', 'Active Administrator', 'No active Administrator account is present in this UAT database.');
        }

        $invalidTcs = User::query()
            ->where('role', UserRole::TC->value)
            ->where('is_active', true)
            ->whereNull('assigned_province_id')
            ->count();

        if ($invalidTcs === 0) {
            $this->pass('Accounts', 'TC province assignments', 'Every active TC has an assigned province.');
        } else {
            $this->recordFailure('Accounts', 'TC province assignments', "{$invalidTcs} active TC account(s) have no assigned province.");
        }


        $legacyGipAccounts = User::query()->where('role', 'gip')->count();

        if ($legacyGipAccounts === 0) {
            $this->pass('Accounts', 'Retired GIP role value', 'No user row uses the retired gip role value.');
        } else {
            $this->recordFailure('Accounts', 'Retired GIP role value', "{$legacyGipAccounts} user row(s) still use role=gip. Apply the retirement migration.");
        }

        $activeRetiredAccounts = User::query()
            ->where('role', UserRole::RETIRED->value)
            ->where('is_active', true)
            ->count();

        if ($activeRetiredAccounts === 0) {
            $this->pass('Accounts', 'Retired account activation', 'Historical retired accounts are inactive.');
        } else {
            $this->recordFailure('Accounts', 'Retired account activation', "{$activeRetiredAccounts} retired account(s) are active.");
        }
    }

    private function verifyReportingAndDashboardConfiguration(): void
    {
        $attentionDays = (int) config('tupad_dashboard.attention_after_days', 0);
        $criticalDays = (int) config('tupad_dashboard.critical_after_days', 0);

        if ($attentionDays > 0 && $criticalDays > $attentionDays) {
            $this->pass(
                'Dashboard',
                'Aging thresholds',
                "Attention {$attentionDays}+ days; critical {$criticalDays}+ days.",
            );
        } else {
            $this->recordFailure('Dashboard', 'Aging thresholds', 'Critical aging must be greater than the positive attention threshold.');
        }

        $documentVersion = trim((string) config('tupad_reports.document.version'));
        $revision = trim((string) config('tupad_reports.document.revision'));
        $referencePrefix = trim((string) config('tupad_reports.document.reference_prefix'));
        $timezone = trim((string) config('tupad_reports.document.timezone'));

        if ($documentVersion !== '' && $revision !== '' && $referencePrefix !== '') {
            $this->pass(
                'Reports',
                'Document control',
                "Version {$documentVersion}, revision {$revision}, prefix {$referencePrefix}.",
            );
        } else {
            $this->recordFailure('Reports', 'Document control', 'Version, revision, and reference prefix must be configured.');
        }

        if ($timezone === 'Asia/Manila') {
            $this->pass('Reports', 'Report timezone', 'Asia/Manila.');
        } else {
            $this->warnCheck('Reports', 'Report timezone', "Configured as [{$timezone}]; verify this is intentional for DOLE Regional Office V.");
        }

        $blankSignatories = collect((array) config('tupad_reports.signatories', []))
            ->filter(static fn (array $signatory): bool => blank($signatory['name'] ?? null))
            ->keys()
            ->values();

        if ($blankSignatories->isEmpty()) {
            $this->pass('Reports', 'Official signatories', 'Prepared, reviewed, and approved signatories are configured.');
        } else {
            $this->warnCheck(
                'Reports',
                'Official signatories',
                'Blank signatory name(s): '.$blankSignatories->implode(', ').'. Reports will render signature lines until configured.',
            );
        }
    }

    private function verifyProductionConfiguration(): void
    {
        if (! $this->production) {
            $this->warnCheck('Production', 'Strict production gate', 'Not enforced. Re-run with --production on the deployment environment.');

            return;
        }

        if (! app()->environment('production')) {
            $this->recordFailure('Production', 'APP_ENV', 'APP_ENV must be production.');
        } else {
            $this->pass('Production', 'APP_ENV', 'production');
        }

        if ((bool) config('app.debug')) {
            $this->recordFailure('Production', 'APP_DEBUG', 'APP_DEBUG must be false.');
        } else {
            $this->pass('Production', 'APP_DEBUG', 'false');
        }

        $appUrl = (string) config('app.url');

        if (Str::startsWith(Str::lower($appUrl), 'https://')) {
            $this->pass('Production', 'APP_URL', 'HTTPS URL configured.');
        } else {
            $this->recordFailure('Production', 'APP_URL', "Expected HTTPS production URL; current value is [{$appUrl}].");
        }

        if ((bool) config('session.secure')) {
            $this->pass('Production', 'Secure session cookie', 'Enabled.');
        } else {
            $this->recordFailure('Production', 'Secure session cookie', 'SESSION_SECURE_COOKIE must be true.');
        }

        if ((bool) config('session.encrypt')) {
            $this->pass('Production', 'Encrypted sessions', 'Enabled.');
        } else {
            $this->recordFailure('Production', 'Encrypted sessions', 'SESSION_ENCRYPT must be true.');
        }

        if (config('database.default') === 'mysql') {
            $this->pass('Production', 'Database driver', 'mysql');
        } else {
            $this->recordFailure('Production', 'Database driver', 'Production deployment must use mysql.');
        }

        if (config('mail.default') === 'log') {
            $this->warnCheck('Production', 'Mail transport', 'MAIL_MAILER=log. Configure a real transport if production email delivery is required.');
        } else {
            $this->pass('Production', 'Mail transport', (string) config('mail.default'));
        }
    }

    private function verifyRepositoryCleanliness(): void
    {
        foreach ([
            'app/Http/Controllers/ProjectBeneficiaryController.php',
            'app/Http/Controllers/ProjectPayoutController.php',
            'resources/views/projects/beneficiaries',
            'resources/views/projects/gip-placeholder.blade.php',
            'resources/views/welcome.blade.php',
            'FIX_FRESH_BASELINE.cmd',
            'FIX_FRESH_BASELINE.ps1',
            'verify-r14-2.ps1',
            'README_OVERLAY.md',
        ] as $relativePath) {
            if (File::exists(base_path($relativePath))) {
                $this->recordFailure('Repository', $relativePath, 'Retired/development artifact still exists.');
            } else {
                $this->pass('Repository', $relativePath, 'Absent as expected.');
            }
        }
    }

    private function verifyBuildAssets(): void
    {
        $manifest = public_path('build/manifest.json');

        if (is_file($manifest)) {
            $this->pass('Frontend', 'Vite production build', 'public/build/manifest.json exists.');

            return;
        }

        if ($this->production) {
            $this->recordFailure('Frontend', 'Vite production build', 'Missing public/build/manifest.json. Run npm ci && npm run build before deployment.');
        } else {
            $this->warnCheck('Frontend', 'Vite production build', 'Production manifest is not present in this working copy. Run npm run build before release.');
        }
    }

    private function writeJsonReportIfRequested(): void
    {
        $requestedPath = trim((string) $this->option('report'));

        if ($requestedPath === '') {
            return;
        }

        $path = $this->absolutePath($requestedPath);

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'system' => 'TUPAD Reporting System',
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'production_gate' => $this->production,
                'summary' => [
                    'checks' => count($this->checks),
                    'warnings' => $this->warnings,
                    'failures' => $this->failures,
                    'passed' => $this->failures === 0,
                ],
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

            $this->info('JSON audit report written to: '.$path);
        } catch (Throwable $exception) {
            $this->warn('Could not write the optional JSON audit report: '.$exception->getMessage());
        }
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || Str::startsWith($path, ['/','\\'])) {
            return $path;
        }

        return base_path($path);
    }

    private function pass(string $area, string $check, string $detail): void
    {
        $this->record($area, $check, 'PASS', $detail);
    }

    private function warnCheck(string $area, string $check, string $detail): void
    {
        $this->warnings++;
        $this->record($area, $check, 'WARN', $detail);
    }

    private function recordFailure(string $area, string $check, string $detail): void
    {
        $this->failures++;
        $this->record($area, $check, 'FAIL', $detail);
    }

    private function record(string $area, string $check, string $result, string $detail): void
    {
        $this->checks[] = compact('area', 'check', 'result', 'detail');
    }
}
