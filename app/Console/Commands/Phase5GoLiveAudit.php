<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Phase5GoLiveAudit extends Command
{
    protected $signature = 'tupad:phase5-audit
        {--production : Enforce strict go-live release gates}
        {--report= : Optional JSON report path}';

    protected $description = 'Run the final Phase 5 go-live gate for the three-role TUPAD production deployment.';

    /** @var array<int,array{area:string,check:string,result:string,detail:string}> */
    private array $checks = [];
    private int $failures = 0;
    private int $warnings = 0;
    private bool $production = false;

    public function handle(): int
    {
        $this->production = app()->environment('production') || (bool) $this->option('production');
        $this->info('TUPAD Phase 5 go-live audit');
        $this->line('Mode: '.($this->production ? 'STRICT PRODUCTION' : 'PRE-DEPLOY / UAT'));
        $this->newLine();

        $phase4 = $this->call('tupad:phase4-audit', ['--production' => $this->production]);
        $phase4 === self::SUCCESS
            ? $this->pass('Foundation', 'Phase 4 gate', 'Phase 4 release-readiness checks passed.')
            : $this->recordFailure('Foundation', 'Phase 4 gate', 'Phase 4 release-readiness checks failed.');

        $this->verifyThreeRoleModel();
        $this->verifyReferenceData();
        $this->verifyWorkflowEntry();
        $this->verifySignatories();
        $this->verifyOperationsTooling();

        $this->newLine();
        $this->table(['Area', 'Check', 'Result', 'Detail'], $this->checks);
        $this->writeReport();
        $this->newLine();
        $this->line(sprintf('%d check(s), %d warning(s), %d blocking failure(s).', count($this->checks), $this->warnings, $this->failures));

        if ($this->failures > 0) {
            $this->error('Phase 5 go-live audit FAILED. Do not open production access.');
            return self::FAILURE;
        }

        $this->warnings > 0
            ? $this->warn('Phase 5 go-live audit PASSED with warnings.')
            : $this->info('Phase 5 go-live audit PASSED.');

        return self::SUCCESS;
    }

    private function verifyThreeRoleModel(): void
    {
        $assignable = array_map(static fn (UserRole $role): string => $role->value, UserRole::assignable());
        $assignable === ['admin', 'focal', 'tc']
            ? $this->pass('Access', 'Assignable roles', 'Administrator, Focal, and TC only.')
            : $this->recordFailure('Access', 'Assignable roles', 'Live assignable roles are not exactly admin, focal, tc.');

        if (Schema::hasTable('users')) {
            $legacy = User::query()->where('role', 'gip')->count();
            $legacy === 0
                ? $this->pass('Access', 'Retired role rows', 'No role=gip user remains.')
                : $this->recordFailure('Access', 'Retired role rows', "{$legacy} role=gip user row(s) remain.");

            $admins = User::query()->where('role', UserRole::ADMIN->value)->where('is_active', true)->count();
            if ($admins > 0) {
                $this->pass('Access', 'Administrator', "{$admins} active Administrator account(s).");
            } elseif ($this->production) {
                $this->recordFailure('Access', 'Administrator', 'At least one active Administrator is required.');
            } else {
                $this->warnCheck('Access', 'Administrator', 'No active Administrator exists in this UAT database.');
            }
        }

        foreach (['project-drafts.index', 'project-draft-reviews.index'] as $route) {
            Route::has($route)
                ? $this->recordFailure('Access', $route, 'Retired GIP workflow route still exists.')
                : $this->pass('Access', $route, 'Absent.');
        }
    }

    private function verifyReferenceData(): void
    {
        if (! Schema::hasTable('provinces') || ! Schema::hasTable('municipalities') || ! Schema::hasTable('barangays')) {
            $this->recordFailure('Reference Data', 'Region V geography', 'Required geography tables are missing.');
            return;
        }

        $requiredCodes = array_keys((array) config('tupad_mapping.provinces', []));
        $provinceCount = Province::query()->whereIn('code', $requiredCodes)->where('is_active', true)->count();
        $municipalityCount = Municipality::query()->where('is_active', true)->count();
        $barangayCount = Barangay::query()->where('is_active', true)->count();

        if ($provinceCount === 6 && $municipalityCount > 0 && $barangayCount > 0) {
            $this->pass('Reference Data', 'Region V geography', "6 provinces; {$municipalityCount} municipalities/cities; {$barangayCount} barangays.");
        } elseif ($this->production) {
            $this->recordFailure('Reference Data', 'Region V geography', 'Reviewed Region V reference data is incomplete. Run tupad:reference-data-sync.');
        } else {
            $this->warnCheck('Reference Data', 'Region V geography', 'Reference data is incomplete in this UAT database.');
        }
    }

    private function verifyWorkflowEntry(): void
    {
        ProjectStatus::ONGOING_PROFILING->label() === 'Ongoing Profiling'
            ? $this->pass('Workflow', 'Initial project status', 'Ongoing Profiling.')
            : $this->recordFailure('Workflow', 'Initial project status', 'Ongoing Profiling enum is unavailable.');

        Route::has('projects.evaluation.start')
            ? $this->pass('Workflow', 'First progression', 'Ongoing Profiling can submit to TSSD Evaluation.')
            : $this->recordFailure('Workflow', 'First progression', 'TSSD evaluation start route is missing.');
    }

    private function verifySignatories(): void
    {
        $signatories = (array) config('tupad_reports.signatories', []);
        $blank = collect(['prepared_by', 'reviewed_by', 'approved_by'])
            ->filter(static fn (string $key): bool => trim((string) data_get($signatories, $key.'.name')) === '')
            ->values();

        if ($blank->isEmpty()) {
            $this->pass('Reports', 'Official signatories', 'Prepared, reviewed, and approved signatory names are configured.');
        } elseif ($this->production) {
            $this->recordFailure('Reports', 'Official signatories', 'Configure signatory names before go-live: '.$blank->implode(', ').'.');
        } else {
            $this->warnCheck('Reports', 'Official signatories', 'Blank for UAT: '.$blank->implode(', ').'.');
        }
    }

    private function verifyOperationsTooling(): void
    {
        foreach ([
            'P5_PREDEPLOY_CHECK.cmd',
            'P5_DEPLOY_PRODUCTION.cmd',
            'P5_BACKUP_DATABASE.cmd',
            'P5_RESTORE_DATABASE.cmd',
            'P5_SMOKE_CHECK.cmd',
            'docs/go-live/P5_6_BACKUP_RECOVERY.md',
            'docs/go-live/P5_8_FINAL_TURNOVER.md',
        ] as $path) {
            File::exists(base_path($path))
                ? $this->pass('Operations', $path, 'Present.')
                : $this->recordFailure('Operations', $path, 'Missing.');
        }
    }

    private function writeReport(): void
    {
        $requested = trim((string) $this->option('report'));
        if ($requested === '') {
            return;
        }

        $path = preg_match('/^[A-Za-z]:[\\\\\/]/', $requested) === 1 || str_starts_with($requested, '/')
            ? $requested
            : base_path($requested);

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'system' => 'TUPAD Reporting System',
                'phase' => 'P5',
                'generated_at' => now()->toIso8601String(),
                'production_gate' => $this->production,
                'summary' => [
                    'checks' => count($this->checks),
                    'warnings' => $this->warnings,
                    'failures' => $this->failures,
                    'passed' => $this->failures === 0,
                ],
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->line('Phase 5 report: '.$path);
        } catch (Throwable $exception) {
            $this->warn('Unable to write Phase 5 report: '.$exception->getMessage());
        }
    }

    private function pass(string $area, string $check, string $detail): void
    {
        $this->checks[] = compact('area', 'check') + ['result' => 'PASS', 'detail' => $detail];
    }

    private function warnCheck(string $area, string $check, string $detail): void
    {
        $this->warnings++;
        $this->checks[] = compact('area', 'check') + ['result' => 'WARN', 'detail' => $detail];
    }

    private function recordFailure(string $area, string $check, string $detail): void
    {
        $this->failures++;
        $this->checks[] = compact('area', 'check') + ['result' => 'FAIL', 'detail' => $detail];
    }
}
