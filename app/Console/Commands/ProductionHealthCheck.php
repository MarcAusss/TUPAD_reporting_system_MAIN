<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ProductionHealthCheck extends Command
{
    protected $signature = 'tupad:production-health
        {--report= : Optional JSON report path}';

    protected $description = 'Run a concise non-destructive operational health check for the deployed TUPAD system.';

    /** @var array<int,array{check:string,result:string,detail:string}> */
    private array $checks = [];

    private int $failures = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $this->checkDatabase();
        $this->checkAccounts();
        $this->checkRuntime();
        $this->checkFrontend();
        $this->checkQueue();

        $this->table(['Check', 'Result', 'Detail'], $this->checks);
        $this->writeReport();
        $this->line(sprintf(
            'Health summary: %d failure(s), %d warning(s).',
            $this->failures,
            $this->warnings,
        ));

        if ($this->failures > 0) {
            $this->error('Production health check FAILED.');

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn('Production health check passed with warnings.');
        } else {
            $this->info('Production health check PASSED.');
        }

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $this->pass('Database connection', 'Available.');
        } catch (Throwable $exception) {
            $this->recordFailure('Database connection', $exception->getMessage());

            return;
        }

        if (! Schema::hasTable('migrations')) {
            $this->recordFailure('Migration repository', 'migrations table is missing.');
            return;
        }

        $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME));
        $ran = DB::table('migrations')->pluck('migration')->map(static fn ($value): string => (string) $value);
        $pending = $migrationFiles->diff($ran);

        $pending->isEmpty()
            ? $this->pass('Pending migrations', 'None.')
            : $this->recordFailure('Pending migrations', $pending->implode(', '));
    }

    private function checkAccounts(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $activeAdmins = User::query()->where('role', UserRole::ADMIN->value)->where('is_active', true)->count();
        $activeAdmins > 0
            ? $this->pass('Active Administrator', "{$activeAdmins} active account(s).")
            : $this->recordFailure('Active Administrator', 'No active Administrator account exists.');

        $legacy = User::query()->where('role', 'gip')->count();
        $legacy === 0
            ? $this->pass('Retired GIP role', 'No user uses role=gip.')
            : $this->recordFailure('Retired GIP role', "{$legacy} legacy account(s) still use role=gip.");

        $invalidTcs = User::query()
            ->where('role', UserRole::TC->value)
            ->where('is_active', true)
            ->whereNull('assigned_province_id')
            ->count();
        $invalidTcs === 0
            ? $this->pass('TC province scope', 'Every active TC is province-assigned.')
            : $this->recordFailure('TC province scope', "{$invalidTcs} active TC account(s) have no province.");
    }

    private function checkRuntime(): void
    {
        foreach ([storage_path(), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (is_dir($path) && is_writable($path)) {
                $this->pass('Writable '.Str::after($path, base_path().DIRECTORY_SEPARATOR), 'Writable.');
            } else {
                $this->recordFailure('Writable '.Str::after($path, base_path().DIRECTORY_SEPARATOR), 'Missing or not writable.');
            }
        }

        $freeBytes = @disk_free_space(base_path());
        if (is_numeric($freeBytes)) {
            $freeMb = (int) floor(((float) $freeBytes) / 1024 / 1024);
            $minimumMb = max(128, (int) config('tupad_operations.health.minimum_free_mb', 1024));
            if ($freeMb >= $minimumMb) {
                $this->pass('Free disk space', number_format($freeMb).' MB available.');
            } else {
                $this->warnCheck('Free disk space', number_format($freeMb)." MB available; target is {$minimumMb} MB or more.");
            }
        }
    }

    private function checkFrontend(): void
    {
        is_file(public_path('build/manifest.json'))
            ? $this->pass('Vite build', 'public/build/manifest.json is present.')
            : $this->recordFailure('Vite build', 'Production frontend build is missing.');
    }

    private function checkQueue(): void
    {
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $failed === 0
                ? $this->pass('Failed jobs', 'None.')
                : $this->warnCheck('Failed jobs', "{$failed} failed job(s) require review.");
        }

        if (Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
            $pending === 0
                ? $this->pass('Pending jobs', 'None.')
                : $this->warnCheck('Pending jobs', "{$pending} queued job(s) are waiting.");
        }
    }

    private function writeReport(): void
    {
        $requested = trim((string) $this->option('report'));
        if ($requested === '') {
            $requested = trim((string) config('tupad_operations.health.report_path'));
        }
        if ($requested === '') {
            return;
        }

        $path = $this->absolutePath($requested);
        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'system' => 'TUPAD Reporting System',
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'summary' => [
                    'failures' => $this->failures,
                    'warnings' => $this->warnings,
                    'healthy' => $this->failures === 0,
                ],
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->line('Health report: '.$path);
        } catch (Throwable $exception) {
            $this->warn('Unable to write health report: '.$exception->getMessage());
        }
    }

    private function pass(string $check, string $detail): void
    {
        $this->checks[] = ['check' => $check, 'result' => 'PASS', 'detail' => $detail];
    }

    private function warnCheck(string $check, string $detail): void
    {
        $this->warnings++;
        $this->checks[] = ['check' => $check, 'result' => 'WARN', 'detail' => $detail];
    }

    private function recordFailure(string $check, string $detail): void
    {
        $this->failures++;
        $this->checks[] = ['check' => $check, 'result' => 'FAIL', 'detail' => $detail];
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || Str::startsWith($path, ['/', '\\'])) {
            return $path;
        }

        return base_path($path);
    }
}
