<?php

namespace App\Console\Commands;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class VerifyReleaseReadiness extends Command
{
    protected $signature = 'tupad:release-verify
        {--production : Enforce production configuration checks even outside APP_ENV=production}';

    protected $description =
        'Run non-destructive schema, security, financial, beneficiary, and audit integrity checks before release.';

    /** @var array<int, string> */
    private array $failures = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->info('TUPAD release verification');

        $this->verifySchema();

        if ($this->failures === []) {
            $this->verifyApplicationConfiguration();
            $this->verifyFinancialIntegrity();
            $this->verifyBeneficiaryIntegrity();
            $this->verifyWorkflowAndAuditIntegrity();
        }

        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $this->error($failure);
            }

            $this->newLine();
            $this->error(sprintf(
                'Release verification FAILED with %d blocking issue(s). No data was modified.',
                count($this->failures),
            ));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Release verification PASSED. No blocking integrity issue was detected and no data was modified.');

        return self::SUCCESS;
    }

    private function verifySchema(): void
    {
        $required = [
            'users' => ['username', 'role', 'is_active'],
            'adls' => ['grants', 'admin_cost', 'total'],
            'adl_realignments' => ['adl_id', 'amount'],
            'adl_allocations' => ['adl_id', 'amount', 'grant_amount', 'admin_cost_amount', 'total_amount'],
            'projects' => [
                'adl_allocation_id',
                'status',
                'beneficiaries_total',
                'beneficiaries_female',
                'wages_total',
                'total_project_cost',
                'intervention_focus',
            ],
            'project_locations' => ['project_id', 'province_id', 'municipality_id', 'district'],
            'project_location_barangay' => [
                'project_location_id',
                'barangay_id',
                'beneficiaries_total',
                'beneficiaries_female',
            ],
            'project_status_histories' => ['project_id', 'to_status', 'changed_at'],
            'project_approvals' => ['project_id', 'project_code'],
            'audit_logs' => ['user_id', 'action', 'module', 'auditable_type', 'auditable_id', 'performed_at'],
            'project_obligations' => ['project_id', 'tranche_number', 'amount'],
            'project_disbursements' => ['project_obligation_id', 'amount', 'date_disbursed'],
            'project_beneficiary_sectors' => [
                'project_id',
                'sector_group',
                'sector_key',
                'beneficiaries_total',
                'beneficiaries_female',
            ],
            'project_labor_market_referrals' => [
                'project_id',
                'reporting_month',
                'program',
                'interested_referred_total',
                'interested_referred_female',
                'provided_intervention_total',
                'provided_intervention_female',
                'amount_released',
            ],
            'project_implementations' => [
                'project_id',
                'start_date',
                'end_date',
                'recorded_by',
            ],
            'project_acp_payments' => [
                'project_id',
                'amount',
                'payment_date',
                'payee',
                'recorded_by',
            ],
            'project_acp_check_releases' => [
                'project_id',
                'check_number',
                'check_date',
                'amount',
                'released_date',
                'released_to',
                'recorded_by',
            ],
            'project_acp_check_release_attachments' => [
                'project_acp_check_release_id',
                'original_name',
                'attachment_path',
            ],
            'project_acp_liquidations' => [
                'project_id',
                'liquidation_date',
                'amount',
                'recorded_by',
            ],
            'project_acp_liquidation_attachments' => [
                'project_acp_liquidation_id',
                'original_name',
                'attachment_path',
            ],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $this->failures[] = "Missing required table [{$table}]. Run pending migrations without using migrate:fresh.";
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->failures[] = "Missing required column [{$table}.{$column}]. Run pending migrations without using migrate:fresh.";
                }
            }
        }
    }

    private function verifyApplicationConfiguration(): void
    {
        $production = app()->environment('production') || (bool) $this->option('production');

        if (! $production) {
            $this->warnings[] = 'Production configuration checks were not enforced. Run this command with --production before deployment.';
            return;
        }

        if ((bool) config('app.debug')) {
            $this->failures[] = 'APP_DEBUG must be false for production release.';
        }

        if (blank(config('app.key'))) {
            $this->failures[] = 'APP_KEY is missing. Configure a persistent application key before release.';
        }

        if (config('database.default') !== 'mysql') {
            $this->failures[] = sprintf(
                'Production database connection must be mysql for this deployment; current connection is [%s].',
                (string) config('database.default'),
            );
        }

        $insecureUsers = User::query()
            ->where('is_active', true)
            ->get(['username', 'password'])
            ->filter(
                static fn (User $user): bool =>
                    Hash::check('password', (string) $user->password)
            )
            ->pluck('username')
            ->values();

        if ($insecureUsers->isNotEmpty()) {
            $this->failures[] = 'Active account(s) still use the development password "password": '.$insecureUsers->implode(', ').'.';
        }
    }

    private function verifyFinancialIntegrity(): void
    {
        $adls = DB::table('adls as a')
            ->leftJoinSub(
                DB::table('adl_allocations')
                    ->select('adl_id', DB::raw('SUM(amount) as allocated'))
                    ->groupBy('adl_id'),
                'alloc',
                'alloc.adl_id',
                '=',
                'a.id',
            )
            ->leftJoinSub(
                DB::table('adl_realignments')
                    ->select('adl_id', DB::raw('SUM(amount) as realigned'))
                    ->groupBy('adl_id'),
                'realign',
                'realign.adl_id',
                '=',
                'a.id',
            )
            ->select([
                'a.id',
                'a.adl_number',
                'a.grants',
                'a.total',
                DB::raw('COALESCE(alloc.allocated, 0) as allocated'),
                DB::raw('COALESCE(realign.realigned, 0) as realigned'),
            ])
            ->get();

        foreach ($adls as $adl) {
            if ($this->moneyToCents($adl->total) !== $this->moneyToCents($adl->grants)) {
                $this->failures[] = "ADL [{$adl->adl_number}] has total different from grants. Current system rule requires ADL total = grants; administrative cost is tracked separately.";
            }

            $available = $this->moneyToCents($adl->grants) + $this->moneyToCents($adl->realigned);
            $allocated = $this->moneyToCents($adl->allocated);

            if ($allocated > $available) {
                $this->failures[] = "ADL [{$adl->adl_number}] is over-allocated: allocated {$this->formatCents($allocated)} versus adjusted grants {$this->formatCents($available)}.";
            }
        }

        $allocations = DB::table('adl_allocations')
            ->select(['id', 'partner', 'amount', 'grant_amount', 'admin_cost_amount', 'total_amount'])
            ->get();

        foreach ($allocations as $allocation) {
            $amount = $this->moneyToCents($allocation->amount);
            $grant = $this->moneyToCents($allocation->grant_amount ?? $allocation->amount);
            $admin = $this->moneyToCents($allocation->admin_cost_amount ?? 0);
            $total = $allocation->total_amount === null
                ? $grant + $admin
                : $this->moneyToCents($allocation->total_amount);

            if ($amount !== $grant) {
                $this->failures[] = "ADL allocation #{$allocation->id} has legacy amount different from grant_amount. Project budget logic requires amount to remain the grant amount.";
            }

            if ($total !== ($grant + $admin)) {
                $this->failures[] = "ADL allocation #{$allocation->id} has total_amount inconsistent with grant_amount + admin_cost_amount.";
            }
        }

        $projectCosts = DB::table('adl_allocations as aa')
            ->leftJoinSub(
                DB::table('projects')
                    ->select('adl_allocation_id', DB::raw('SUM(total_project_cost) as project_cost'))
                    ->groupBy('adl_allocation_id'),
                'p',
                'p.adl_allocation_id',
                '=',
                'aa.id',
            )
            ->select(['aa.id', 'aa.amount', DB::raw('COALESCE(p.project_cost, 0) as project_cost')])
            ->get();

        foreach ($projectCosts as $allocation) {
            if ($this->moneyToCents($allocation->project_cost) > $this->moneyToCents($allocation->amount)) {
                $this->failures[] = "ADL allocation #{$allocation->id} has project costs exceeding its grant budget.";
            }
        }

        $obligationTotals = DB::table('project_obligations')
            ->select('project_id', DB::raw('SUM(amount) as obligated'))
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $projectWages = DB::table('projects')
            ->whereIn('id', $obligationTotals->keys())
            ->pluck('wages_total', 'id');

        foreach ($obligationTotals as $projectId => $row) {
            if ($this->moneyToCents($row->obligated) > $this->moneyToCents($projectWages[$projectId] ?? 0)) {
                $this->failures[] = "Project #{$projectId} has obligations exceeding payable wages.";
            }
        }

        $disbursementTotals = DB::table('project_disbursements')
            ->select('project_obligation_id', DB::raw('SUM(amount) as disbursed'))
            ->groupBy('project_obligation_id')
            ->get()
            ->keyBy('project_obligation_id');

        $obligations = DB::table('project_obligations')
            ->whereIn('id', $disbursementTotals->keys())
            ->pluck('amount', 'id');

        foreach ($disbursementTotals as $obligationId => $row) {
            if ($this->moneyToCents($row->disbursed) > $this->moneyToCents($obligations[$obligationId] ?? 0)) {
                $this->failures[] = "Obligation #{$obligationId} has disbursements exceeding the obligation amount.";
            }
        }

        $this->verifyAcpFinancialIntegrity();
    }

    private function verifyAcpFinancialIntegrity(): void
    {
        $payments = DB::table('project_acp_payments as payment')
            ->join('projects as project', 'project.id', '=', 'payment.project_id')
            ->select([
                'payment.id',
                'payment.project_id',
                'payment.amount',
                'payment.payment_date',
                'project.implementation_mode',
                'project.total_project_cost',
            ])
            ->get();

        foreach ($payments as $payment) {
            if ($payment->implementation_mode !== ImplementationMode::THROUGH_ACP->value) {
                $this->failures[] = "ACP payment #{$payment->id} belongs to a project that is not Through ACP.";
            }

            if ($this->moneyToCents($payment->amount) !== $this->moneyToCents($payment->total_project_cost)) {
                $this->failures[] = "ACP payment #{$payment->id} does not match the project's approved total project cost.";
            }
        }

        $checkReleases = DB::table('project_acp_check_releases as release')
            ->join('projects as project', 'project.id', '=', 'release.project_id')
            ->leftJoin('project_acp_payments as payment', 'payment.project_id', '=', 'release.project_id')
            ->select([
                'release.id',
                'release.project_id',
                'release.amount',
                'release.check_date',
                'release.released_date',
                'project.implementation_mode',
                'payment.id as payment_id',
                'payment.amount as payment_amount',
                'payment.payment_date',
            ])
            ->get();

        foreach ($checkReleases as $release) {
            if ($release->implementation_mode !== ImplementationMode::THROUGH_ACP->value) {
                $this->failures[] = "ACP check release #{$release->id} belongs to a project that is not Through ACP.";
            }

            if ($release->payment_id === null) {
                $this->failures[] = "ACP check release #{$release->id} has no corresponding ACP payment record.";
                continue;
            }

            if ($this->moneyToCents($release->amount) !== $this->moneyToCents($release->payment_amount)) {
                $this->failures[] = "ACP check release #{$release->id} amount does not match the official ACP payment amount.";
            }

            if ((string) $release->check_date < (string) $release->payment_date) {
                $this->failures[] = "ACP check release #{$release->id} has a check date earlier than its ACP payment date.";
            }

            if ((string) $release->released_date < (string) $release->check_date) {
                $this->failures[] = "ACP check release #{$release->id} has a release date earlier than its check date.";
            }
        }

        $acpImplementations = DB::table('project_implementations as implementation')
            ->join('projects as project', 'project.id', '=', 'implementation.project_id')
            ->where('project.implementation_mode', ImplementationMode::THROUGH_ACP->value)
            ->leftJoin('project_acp_check_releases as release', 'release.project_id', '=', 'implementation.project_id')
            ->select([
                'implementation.id',
                'implementation.project_id',
                'implementation.start_date',
                'implementation.end_date',
                'release.id as release_id',
                'release.released_date',
            ])
            ->get();

        foreach ($acpImplementations as $implementation) {
            if ($implementation->release_id === null) {
                $this->failures[] = "Through ACP implementation #{$implementation->id} has no check-release record.";
                continue;
            }

            if ((string) $implementation->start_date < (string) $implementation->released_date) {
                $this->failures[] = "Through ACP implementation #{$implementation->id} starts before the check-release date.";
            }

            if ((string) $implementation->end_date < (string) $implementation->start_date) {
                $this->failures[] = "Through ACP implementation #{$implementation->id} ends before its start date.";
            }
        }

        $liquidations = DB::table('project_acp_liquidations as liquidation')
            ->join('projects as project', 'project.id', '=', 'liquidation.project_id')
            ->leftJoin('project_acp_check_releases as release', 'release.project_id', '=', 'liquidation.project_id')
            ->leftJoin('project_implementations as implementation', 'implementation.project_id', '=', 'liquidation.project_id')
            ->select([
                'liquidation.id',
                'liquidation.project_id',
                'liquidation.amount',
                'liquidation.liquidation_date',
                'project.implementation_mode',
                'release.id as release_id',
                'release.amount as released_amount',
                'implementation.id as implementation_id',
                'implementation.end_date',
            ])
            ->get();

        foreach ($liquidations as $liquidation) {
            if ($liquidation->implementation_mode !== ImplementationMode::THROUGH_ACP->value) {
                $this->failures[] = "ACP liquidation #{$liquidation->id} belongs to a project that is not Through ACP.";
            }

            if ($this->moneyToCents($liquidation->amount) <= 0) {
                $this->failures[] = "ACP liquidation #{$liquidation->id} has a non-positive amount.";
            }

            if ($liquidation->release_id === null) {
                $this->failures[] = "ACP liquidation #{$liquidation->id} has no check-release record.";
            }

            if ($liquidation->implementation_id === null) {
                $this->failures[] = "ACP liquidation #{$liquidation->id} has no implementation-period record.";
            } elseif ((string) $liquidation->liquidation_date < (string) $liquidation->end_date) {
                $this->failures[] = "ACP liquidation #{$liquidation->id} is dated before the implementation end date.";
            }
        }

        $liquidationTotals = DB::table('project_acp_liquidations')
            ->select('project_id', DB::raw('SUM(amount) as liquidated'))
            ->groupBy('project_id')
            ->get();

        foreach ($liquidationTotals as $total) {
            $released = DB::table('project_acp_check_releases')
                ->where('project_id', $total->project_id)
                ->value('amount');

            if ($released !== null && $this->moneyToCents($total->liquidated) > $this->moneyToCents($released)) {
                $this->failures[] = "Through ACP project #{$total->project_id} has liquidation records exceeding the released check amount.";
            }
        }
    }

    private function verifyBeneficiaryIntegrity(): void
    {
        $invalidProjectCounts = DB::table('projects')
            ->whereColumn('beneficiaries_female', '>', 'beneficiaries_total')
            ->count();

        if ($invalidProjectCounts > 0) {
            $this->failures[] = "{$invalidProjectCounts} project(s) have female beneficiaries greater than total beneficiaries.";
        }

        $invalidPivotCounts = DB::table('project_location_barangay')
            ->whereNotNull('beneficiaries_total')
            ->whereNotNull('beneficiaries_female')
            ->whereColumn('beneficiaries_female', '>', 'beneficiaries_total')
            ->count();

        if ($invalidPivotCounts > 0) {
            $this->failures[] = "{$invalidPivotCounts} exact barangay allocation row(s) have female beneficiaries greater than total beneficiaries.";
        }

        $locationAggregates = DB::table('project_locations as pl')
            ->join('project_location_barangay as plb', 'plb.project_location_id', '=', 'pl.id')
            ->select([
                'pl.project_id',
                DB::raw('COUNT(*) as row_count'),
                DB::raw('SUM(CASE WHEN plb.beneficiaries_total IS NULL OR plb.beneficiaries_female IS NULL THEN 1 ELSE 0 END) as incomplete_rows'),
                DB::raw('SUM(COALESCE(plb.beneficiaries_total, 0)) as allocated_total'),
                DB::raw('SUM(COALESCE(plb.beneficiaries_female, 0)) as allocated_female'),
            ])
            ->groupBy('pl.project_id')
            ->get()
            ->keyBy('project_id');

        $projects = DB::table('projects')
            ->select(['id', 'beneficiaries_total', 'beneficiaries_female'])
            ->get();

        foreach ($projects as $project) {
            $aggregate = $locationAggregates->get($project->id);

            if (! $aggregate) {
                $this->failures[] = "Project #{$project->id} has no exact project_location_barangay allocation.";
                continue;
            }

            if ((int) $aggregate->incomplete_rows > 0) {
                $this->failures[] = "Project #{$project->id} has incomplete exact barangay beneficiary allocation rows.";
                continue;
            }

            if (
                (int) $aggregate->allocated_total !== (int) $project->beneficiaries_total
                || (int) $aggregate->allocated_female !== (int) $project->beneficiaries_female
            ) {
                $this->failures[] = "Project #{$project->id} exact geographic beneficiary allocations do not reconcile with project totals.";
            }
        }

        $invalidSectorCounts = DB::table('project_beneficiary_sectors')
            ->whereColumn('beneficiaries_female', '>', 'beneficiaries_total')
            ->count();

        if ($invalidSectorCounts > 0) {
            $this->failures[] = "{$invalidSectorCounts} beneficiary sector row(s) have female counts greater than total counts.";
        }

        $invalidLaborCounts = DB::table('project_labor_market_referrals')
            ->where(function ($query): void {
                $query
                    ->whereColumn('interested_referred_female', '>', 'interested_referred_total')
                    ->orWhereColumn('provided_intervention_female', '>', 'provided_intervention_total')
                    ->orWhere('amount_released', '<', 0);
            })
            ->count();

        if ($invalidLaborCounts > 0) {
            $this->failures[] = "{$invalidLaborCounts} labor-market referral row(s) contain invalid beneficiary or amount values.";
        }
    }

    private function verifyWorkflowAndAuditIntegrity(): void
    {
        $validStatuses = array_map(
            static fn (ProjectStatus $status): string => $status->value,
            ProjectStatus::cases(),
        );

        $invalidStatuses = DB::table('projects')
            ->whereNotIn('status', $validStatuses)
            ->count();

        if ($invalidStatuses > 0) {
            $this->failures[] = "{$invalidStatuses} project(s) contain a status outside the consolidated ProjectStatus enum.";
        }

        $directAdminUsingAcpOnlyStatus = DB::table('projects')
            ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
            ->whereIn('status', [
                ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT->value,
                ProjectStatus::FOR_LIQUIDATION->value,
                ProjectStatus::PARTIALLY_LIQUIDATED->value,
            ])
            ->count();

        if ($directAdminUsingAcpOnlyStatus > 0) {
            $this->failures[] = "{$directAdminUsingAcpOnlyStatus} Direct Administration project(s) use a Through ACP-only workflow status.";
        }

        $acpUsingDirectAdminOnlyStatus = DB::table('projects')
            ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
            ->where('status', ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value)
            ->count();

        if ($acpUsingDirectAdminOnlyStatus > 0) {
            $this->failures[] = "{$acpUsingDirectAdminOnlyStatus} Through ACP project(s) use the Direct Administration-only post-documentary status.";
        }

        $missingHistory = DB::table('projects as p')
            ->leftJoin('project_status_histories as h', 'h.project_id', '=', 'p.id')
            ->whereNull('h.id')
            ->distinct()
            ->count('p.id');

        if ($missingHistory > 0) {
            $this->failures[] = "{$missingHistory} project(s) have no project status history. Run ProjectStatusHistorySeeder only after reviewing/backing up production data.";
        }

        $duplicateCodes = DB::table('project_approvals')
            ->whereNotNull('project_code')
            ->select('project_code', DB::raw('COUNT(*) as code_count'))
            ->groupBy('project_code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateCodes > 0) {
            $this->failures[] = "{$duplicateCodes} duplicate official project code group(s) exist.";
        }
    }

    private function moneyToCents(mixed $amount): int
    {
        $normalized = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        return sprintf('₱%s', number_format($cents / 100, 2));
    }
}
