<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\Project;
use App\Models\ProjectDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        /** @var Collection<int, Project> $projects */
        $projects = collect();

        /** @var Collection<int, Adl> $adls */
        $adls = collect();

        /** @var Collection<int, ProjectDraft> $drafts */
        $drafts = collect();

        if (mb_strlen($query) >= 2) {
            if ($user->isAdmin() || $user->isTc() || $user->isFocal()) {
                $projectsQuery = Project::query()
                    ->with([
                        'allocation.adl',
                        'approval',
                    ]);

                // Focal users may only open projects in the payment/completed stages.
                if ($user->isFocal()) {
                    $projectsQuery->whereIn('status', [
                        ProjectStatus::FOR_PAYMENT->value,
                        ProjectStatus::COMPLETED->value,
                    ]);
                }

                $projects = $projectsQuery
                    ->where(function ($builder) use ($query) {
                        $builder
                            ->where('project_title', 'like', "%{$query}%")
                            ->orWhere('province', 'like', "%{$query}%")
                            ->orWhere('municipality', 'like', "%{$query}%")
                            ->orWhere('barangay', 'like', "%{$query}%")
                            ->orWhereHas('approval', function ($approval) use ($query) {
                                $approval->where('project_code', 'like', "%{$query}%");
                            })
                            ->orWhereHas('allocation.adl', function ($adl) use ($query) {
                                $adl->where('adl_number', 'like', "%{$query}%");
                            });
                    })
                    ->latest('updated_at')
                    ->limit(20)
                    ->get();
            }

            if ($user->isAdmin() || $user->isFocal()) {
                $adls = Adl::query()
                    ->where('adl_number', 'like', "%{$query}%")
                    ->latest('updated_at')
                    ->limit(20)
                    ->get();
            }

            if ($user->isGip()) {
                $drafts = ProjectDraft::query()
                    ->where('encoded_by', $user->id)
                    ->where(function ($builder) use ($query) {
                        $builder
                            ->where('project_title', 'like', "%{$query}%")
                            ->orWhere('province', 'like', "%{$query}%")
                            ->orWhere('municipality', 'like', "%{$query}%")
                            ->orWhere('barangay', 'like', "%{$query}%");
                    })
                    ->latest('updated_at')
                    ->limit(20)
                    ->get();
            }
        }

        return view('search.index', compact(
            'query',
            'projects',
            'adls',
            'drafts'
        ));
    }
}
