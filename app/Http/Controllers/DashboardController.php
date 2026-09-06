<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Dashboards\DashboardActionQueueService;
use App\Services\Dashboards\DashboardGeographicAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        ProvinceAccessService $provinceAccess,
        DashboardActionQueueService $actionQueues,
        DashboardGeographicAnalyticsService $geographicAnalytics,
    ): View
    {
        $user = $request->user();
        return $this->officialDashboard($user, $provinceAccess, $actionQueues, $geographicAnalytics);
    }

    private function officialDashboard(
        $user,
        ProvinceAccessService $provinceAccess,
        DashboardActionQueueService $actionQueues,
        DashboardGeographicAnalyticsService $geographicAnalytics,
    ): View
    {
        $projects = $this->projectQuery($user, $provinceAccess);

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

        $recentProjects =
            $this->projectQuery($user, $provinceAccess)
                ->with([
                    'approval',
                    'allocation.adl',
                ])
                ->latest('updated_at')
                ->limit(6)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Monthly project-cost trend
        |--------------------------------------------------------------------------
        */

        $currentYear =
            (int) now()->format('Y');

        $monthlyTrend =
            collect(range(1, 12))
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

            'recentActivity' =>
                AuditLog::query()
                    ->when(
                        $user->isTc(),
                        fn ($query) => $query->where('user_id', $user->id),
                    )
                    ->latest('performed_at')
                    ->limit(6)
                    ->get(),

            'geographicAnalytics' =>
                $user->isFocal()
                    ? $geographicAnalytics->build($user, ['family' => DashboardGeographicAnalyticsService::PROJECTS])
                    : null,

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
