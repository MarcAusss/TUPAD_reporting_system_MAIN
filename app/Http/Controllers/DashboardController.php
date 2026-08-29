<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProvinceAccessService $provinceAccess): View
    {
        $user = $request->user();

        if ($user->isGip()) {
            return $this->gipDashboard($user->id);
        }

        return $this->officialDashboard($user, $provinceAccess);
    }

    private function gipDashboard(int $userId): View
    {
        $draftQuery = ProjectDraft::query()
            ->where('encoded_by', $userId);

        return view('dashboard.index', [
            'dashboardMode' => 'gip',

            'totalDrafts' =>
                (clone $draftQuery)->count(),

            'pendingDrafts' =>
                (clone $draftQuery)
                    ->where(
                        'status',
                        ProjectDraftStatus::PENDING_TC_REVIEW
                    )
                    ->count(),

            'returnedDrafts' =>
                (clone $draftQuery)
                    ->where(
                        'status',
                        ProjectDraftStatus::RETURNED_FOR_CORRECTION
                    )
                    ->count(),

            'confirmedDrafts' =>
                (clone $draftQuery)
                    ->where(
                        'status',
                        ProjectDraftStatus::CONFIRMED
                    )
                    ->count(),

            'recentDrafts' =>
                (clone $draftQuery)
                    ->latest('updated_at')
                    ->limit(6)
                    ->get(),

            'recentActivity' =>
                AuditLog::query()
                    ->where('user_id', $userId)
                    ->latest('performed_at')
                    ->limit(6)
                    ->get(),
        ]);
    }

    private function officialDashboard($user, ProvinceAccessService $provinceAccess): View
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
        | Workflow counts
        |--------------------------------------------------------------------------
        */

        $workflowCounts = [
            'tssd' => $this->projectQuery($user, $provinceAccess)
                ->whereIn('status', [
                    ProjectStatus::TSSD_EVALUATION,
                    ProjectStatus::FOR_COMPLIANCE,
                ])
                ->count(),

            'approval' => $this->projectQuery($user, $provinceAccess)
                ->where('status', ProjectStatus::FOR_APPROVAL)
                ->count(),

            'implementation' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                ->whereIn('status', [
                    ProjectStatus::APPROVED,
                    ProjectStatus::FOR_IMPLEMENTATION,
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                ])
                ->count(),

            'post_documents' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                ->where('status', ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
                ->count(),

            'payment' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                ->where('status', ProjectStatus::FOR_PAYMENT)
                ->count(),

            'acp_payment' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                ->where('status', ProjectStatus::FOR_PAYMENT)
                ->count(),

            'acp_check_release' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                ->where('status', ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT)
                ->count(),

            'acp_implementation' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                ->whereIn('status', [
                    ProjectStatus::FOR_IMPLEMENTATION,
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                ])
                ->count(),

            'acp_liquidation' => $this->projectQuery($user, $provinceAccess)
                ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                ->whereIn('status', [
                    ProjectStatus::FOR_LIQUIDATION,
                    ProjectStatus::PARTIALLY_LIQUIDATED,
                ])
                ->count(),
        ];

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

            'workflowCounts' =>
                $workflowCounts,

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
