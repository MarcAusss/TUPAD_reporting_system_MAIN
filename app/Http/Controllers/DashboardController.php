<?php

namespace App\Http\Controllers;

use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectDraft;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | GIP Dashboard
        |--------------------------------------------------------------------------
        */

        if ($user->isGip()) {
            $draftQuery = ProjectDraft::query()
                ->where(
                    'encoded_by',
                    $user->id
                );

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

                'recentActivity' =>
                    AuditLog::query()
                        ->with('user')
                        ->where('user_id', $user->id)
                        ->latest('performed_at')
                        ->limit(8)
                        ->get(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Official System Dashboard
        |--------------------------------------------------------------------------
        */

        $totalAdjustedGrants = Adl::query()
            ->get()
            ->sum(
                fn(Adl $adl) =>
                $adl->adjusted_grants
            );

        $totalAllocated = Adl::query()
            ->get()
            ->sum(
                fn(Adl $adl) =>
                $adl->total_allocated
            );

        $remainingGrants =
            $totalAdjustedGrants
            - $totalAllocated;

        $utilizationPercent =
            $totalAdjustedGrants > 0
            ? min(
                100,
                ($totalAllocated / $totalAdjustedGrants) * 100
            )
            : 0;

        $statusCounts = Project::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck(
                'total',
                'status'
            );

        return view('dashboard.index', [
            'dashboardMode' => 'official',

            'totalAdls' =>
                Adl::count(),

            'totalProjects' =>
                Project::count(),

            'totalAdjustedGrants' =>
                $totalAdjustedGrants,

            'totalAllocated' =>
                $totalAllocated,

            'remainingGrants' =>
                $remainingGrants,

            'utilizationPercent' =>
                $utilizationPercent,

            'ongoingProjects' =>
                Project::where(
                    'status',
                    ProjectStatus::ONGOING_IMPLEMENTATION
                )->count(),

            'completedProjects' =>
                Project::where(
                    'status',
                    ProjectStatus::COMPLETED
                )->count(),

            'statusCounts' =>
                $statusCounts,

            'recentActivity' =>
                AuditLog::query()
                    ->with('user')
                    ->latest('performed_at')
                    ->limit(10)
                    ->get(),
        ]);
    }
}