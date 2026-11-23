<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Dashboards\AdlUtilizationService;
use App\Services\Dashboards\DashboardActionQueueService;
use App\Services\Dashboards\DashboardBeneficiaryDistributionService;
use App\Services\Dashboards\DashboardProjectDistributionService;
use App\Services\Dashboards\DashboardFilters;
use App\Services\Dashboards\DashboardReportChartsService;
use App\Services\Dashboards\FocalDashboardOverviewService;
use App\Services\Dashboards\RecentProjectsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        ProvinceAccessService $provinceAccess,
        DashboardActionQueueService $actionQueues,
        AdlUtilizationService $adlUtilization,
        DashboardBeneficiaryDistributionService $beneficiaryDistribution,
    ): View
    {
        $user = $request->user();

        // The GSIS Enrollment Officer has its own GPAI enrollment dashboard.
        if ($user->isGsis()) {
            return app(GsisEnrollmentController::class)->dashboard($request);
        }

        return $this->officialDashboard($request, $user, $provinceAccess, $actionQueues, $adlUtilization, $beneficiaryDistribution);
    }

    private function officialDashboard(
        Request $request,
        $user,
        ProvinceAccessService $provinceAccess,
        DashboardActionQueueService $actionQueues,
        AdlUtilizationService $adlUtilization,
        DashboardBeneficiaryDistributionService $beneficiaryDistribution,
    ): View
    {
        $isFocal = $user->isFocal();
        $projects = $this->projectQuery($user, $provinceAccess);

        // Focal dashboard filters (year, quarter, month, province, municipality,
        // sponsor, partner, ADL, status); each one defaults to "All".
        // TUPAD Coordinators get the same filters with their province locked.
        $focalFilters = match (true) {
            $isFocal => DashboardFilters::fromRequest($request),
            $user->isTc() => DashboardFilters::fromRequest($request, $provinceAccess->assignedProvinceId($user) ?? 0),
            default => null,
        };

        $totalProjects =
            (clone $projects)->count();

        $completedProjects =
            (clone $projects)
                ->where(
                    'status',
                    ProjectStatus::COMPLETED
                )
                ->count();

        $activeProjects =
            max(
                0,
                $totalProjects
                - $completedProjects
            );

        /*
        |--------------------------------------------------------------------------
        | Aggregate beneficiary data only
        |--------------------------------------------------------------------------
        |
        | Revision R3 removed individual beneficiary encoding.
        |
        */

        $totalBeneficiaries =
            (int) (clone $projects)
                ->sum('beneficiaries_total');

        $femaleBeneficiaries =
            (int) (clone $projects)
                ->sum('beneficiaries_female');

        /*
        |--------------------------------------------------------------------------
        | Fund position
        |--------------------------------------------------------------------------
        */

        $adls = Adl::query()->get();

        if ($user->isTc()) {
            $allocationQuery = $provinceAccess->scopeAdlAllocations(
                AdlAllocation::query(),
                $user,
            );

            $totalBudget = (float) (clone $allocationQuery)->sum('amount');
            $totalAllocated = (float) (clone $projects)->sum('total_project_cost');
            $visibleAdlCount = (clone $allocationQuery)->distinct()->count('adl_id');
        } else {
            $totalBudget =
                (float) $adls->sum(
                    fn (Adl $adl) =>
                        (float) (
                            $adl->adjusted_total_grants
                            ?? $adl->adjusted_grants
                            ?? $adl->grants
                            ?? 0
                        )
                );

            $totalAllocated =
                (float) AdlAllocation::query()
                    ->sum('amount');
            $visibleAdlCount = $adls->count();
        }

        $remainingBudget =
            max(
                0,
                $totalBudget
                - $totalAllocated
            );

        $utilizationPercent =
            $totalBudget > 0
                ? min(
                    100,
                    ($totalAllocated / $totalBudget) * 100
                )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Role-aware action queues and aging
        |--------------------------------------------------------------------------
        */

        $actionQueueData = $actionQueues->build($user);
        /*
        |--------------------------------------------------------------------------
        | Recent projects
        |--------------------------------------------------------------------------
        */

        $recentProjects = $isFocal
            ? collect()
            : app(RecentProjectsService::class)->for($user);

        /*
        |--------------------------------------------------------------------------
        | Monthly project-cost trend
        |--------------------------------------------------------------------------
        */

        $currentYear =
            (int) now()->format('Y');

        // The Focal dashboard no longer shows the Project Cost Trend chart.
        $monthlyTrend = $isFocal
            ? collect(array_fill(0, 12, 0.0))
            : collect(range(1, 12))
                ->map(
                    fn (int $month) =>
                        (float) $this->projectQuery($user, $provinceAccess)
                            ->whereYear(
                                'date_received',
                                $currentYear
                            )
                            ->whereMonth(
                                'date_received',
                                $month
                            )
                            ->sum(
                                'total_project_cost'
                            )
                );

        $running = 0;

        $cumulativeTrend =
            $monthlyTrend
                ->map(
                    function ($value) use (&$running) {
                        $running += $value;

                        return $running;
                    }
                );

        return view('dashboard.index', [
            'dashboardMode' => 'official',

            'totalAdls' =>
                $visibleAdlCount,

            'totalProjects' =>
                $totalProjects,

            'activeProjects' =>
                $activeProjects,

            'completedProjects' =>
                $completedProjects,

            'totalBeneficiaries' =>
                $totalBeneficiaries,

            'femaleBeneficiaries' =>
                $femaleBeneficiaries,

            'totalBudget' =>
                $totalBudget,

            'totalAllocated' =>
                $totalAllocated,

            'remainingBudget' =>
                $remainingBudget,

            'utilizationPercent' =>
                $utilizationPercent,

            'actionQueueData' =>
                $actionQueueData,

            'recentProjects' =>
                $recentProjects,

            'currentYear' =>
                $currentYear,

            'cumulativeTrend' =>
                $cumulativeTrend,

            'adlUtilization' =>
                $user->isAdmin() ? $adlUtilization->rows($adls) : collect(),

            'recentActivity' =>
                $isFocal ? collect() : AuditLog::query()
                    ->when(
                        $user->isTc(),
                        fn ($query) => $query->where('user_id', $user->id),
                    )
                    ->latest('performed_at')
                    ->limit(6)
                    ->get(),

            // Focal and TUPAD Coordinators share one dashboard layout; a TUPAD
            // Coordinator's charts cover only their assigned province.
            'beneficiaryDistribution' => match (true) {
                $isFocal => $beneficiaryDistribution->build($focalFilters->provinceId),
                $user->isTc() => $beneficiaryDistribution->build($provinceAccess->assignedProvinceId($user) ?? 0),
                default => null,
            },

            'projectDistribution' =>
                $user->isTc()
                    ? app(DashboardProjectDistributionService::class)->build($user, $provinceAccess->assignedProvinceId($user) ?? 0)
                    : null,

            // Beneficiary targets are region-wide, so their balance counts every
            // project of the year; a TUPAD Coordinator sees only their own activity.
            'focalOverview' =>
                $focalFilters
                    ? app(FocalDashboardOverviewService::class)->build(
                        $focalFilters->applyToProjects($this->projectQuery($user, $provinceAccess)),
                        $focalFilters->fundPosition(),
                        targetYear: $focalFilters->year ?? $currentYear,
                        targetProjects: Project::query()
                            ->whereYear('projects.date_received', $focalFilters->year ?? $currentYear),
                        activityUserId: $isFocal ? null : $user->id,
                    )
                    : null,

            'dashboardFilters' => $focalFilters,

            'dashboardFilterOptions' => match (true) {
                $isFocal => $focalFilters->options(),
                $focalFilters !== null => $focalFilters->options($this->projectQuery($user, $provinceAccess)),
                default => null,
            },

            'reportCharts' =>
                $isFocal || $user->isTc()
                    ? app(DashboardReportChartsService::class)->build($user, $focalFilters?->year ?? $currentYear)
                    : null,

            'assignedProvinceName' =>
                $user->isTc() ? $provinceAccess->assignedProvinceName($user) : null,

            'roleMode' =>
                match (true) {
                    $user->isFocal() => 'focal',
                    $user->isTc() => 'tc',
                    $user->isAdmin() => 'admin',
                    default => 'official',
                },
        ]);
    }

    private function projectQuery($user, ProvinceAccessService $provinceAccess): Builder
    {
        return $provinceAccess->scopeProjects(Project::query(), $user);
    }
}
