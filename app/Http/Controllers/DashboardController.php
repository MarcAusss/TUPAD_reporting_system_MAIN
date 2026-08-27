<?php

namespace App\Http\Controllers;

use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isGip()) {
            return $this->gipDashboard($user->id);
        }

        return $this->officialDashboard($user);
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

    private function officialDashboard($user): View
    {
        $projects = Project::query();

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

        $adls =
            Adl::query()->get();

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
            'tssd' =>
                Project::query()
                    ->whereIn(
                        'status',
                        [
                            ProjectStatus::TSSD_EVALUATION,
                            ProjectStatus::FOR_COMPLIANCE,
                        ]
                    )
                    ->count(),

            'approval' =>
                Project::query()
                    ->where(
                        'status',
                        ProjectStatus::FOR_APPROVAL
                    )
                    ->count(),

            'implementation' =>
                Project::query()
                    ->whereIn(
                        'status',
                        [
                            ProjectStatus::APPROVED,
                            ProjectStatus::FOR_IMPLEMENTATION,
                            ProjectStatus::ONGOING_IMPLEMENTATION,
                        ]
                    )
                    ->count(),

            'post_documents' =>
                Project::query()
                    ->where(
                        'status',
                        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
                    )
                    ->count(),

            'payment' =>
                Project::query()
                    ->where(
                        'status',
                        ProjectStatus::FOR_PAYMENT
                    )
                    ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Recent projects
        |--------------------------------------------------------------------------
        */

        $recentProjects =
            Project::query()
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
                        (float) Project::query()
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
                $adls->count(),

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
}
