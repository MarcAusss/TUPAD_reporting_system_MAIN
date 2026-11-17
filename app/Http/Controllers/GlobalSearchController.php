<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\Project;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function index(Request $request, ProvinceAccessService $provinceAccess): View
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        /** @var Collection<int, Project> $projects */
        $projects = collect();
        /** @var Collection<int, Adl> $adls */
        $adls = collect();

        if (mb_strlen($query) >= 2) {
            $projects = $this->projectQuery($query, $user, $provinceAccess)
                ->with(['allocation.adl', 'approval'])
                ->limit(20)
                ->get();

            $adls = $this->adlQuery($query, $user)?->limit(20)->get() ?? collect();
        }

        return view('search.index', compact('query', 'projects', 'adls'));
    }

    /**
     * Live suggestions for the top-bar search box.
     */
    public function suggest(Request $request, ProvinceAccessService $provinceAccess): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        if (mb_strlen($query) < 2) {
            return response()->json(['projects' => [], 'adls' => []]);
        }

        $projects = $this->projectQuery($query, $user, $provinceAccess)
            ->withStatusEnteredAt()
            ->with(['approval', 'allocation.adl', 'projectLocations.municipality', 'projectLocations.province'])
            ->limit(8)
            ->get()
            ->map(fn (Project $project): array => [
                'title' => $project->project_title,
                'code' => $project->approval?->project_code,
                'adl' => $project->allocation?->adl?->adl_number,
                'location' => $project->projectLocations->pluck('municipality.name')->filter()->unique()->take(2)->implode(', ')
                    ?: collect([$project->municipality, $project->province])->filter()->implode(', '),
                'status' => $project->status->label(),
                'status_classes' => $project->status->pillClasses(),
                'days' => $project->status === ProjectStatus::COMPLETED ? null : $project->daysInCurrentStatus(),
                'url' => route('projects.show', $project),
            ]);

        $adls = ($this->adlQuery($query, $user)?->limit(4)->get() ?? collect())
            ->map(fn (Adl $adl): array => [
                'adl_number' => $adl->adl_number,
                'url' => route('adl.show', $adl),
            ]);

        return response()->json([
            'projects' => $projects,
            'adls' => $adls,
            'more_url' => route('search.index', ['q' => $query]),
        ]);
    }

    private function projectQuery(string $query, User $user, ProvinceAccessService $provinceAccess): Builder
    {
        $like = '%'.addcslashes($query, '%_\\').'%';

        $projectsQuery = $provinceAccess->scopeProjects(Project::query(), $user);

        // Focal can only open projects that reached its stages (see ProjectController::show).
        if ($user->isFocal()) {
            $projectsQuery->where(function (Builder $builder): void {
                $builder
                    ->where(function (Builder $direct): void {
                        $direct->where('implementation_mode', '!=', ImplementationMode::THROUGH_ACP->value)
                            ->whereIn('status', [ProjectStatus::FOR_PAYMENT->value, ProjectStatus::COMPLETED->value]);
                    })
                    ->orWhere(function (Builder $acp): void {
                        $acp->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                            ->whereIn('status', [
                                ProjectStatus::FOR_PAYMENT->value,
                                ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT->value,
                                ProjectStatus::FOR_LIQUIDATION->value,
                                ProjectStatus::PARTIALLY_LIQUIDATED->value,
                                ProjectStatus::COMPLETED->value,
                            ]);
                    });
            });
        }

        return $projectsQuery
            ->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('project_title', 'like', $like)
                    ->orWhere('province', 'like', $like)
                    ->orWhere('municipality', 'like', $like)
                    ->orWhere('barangay', 'like', $like)
                    ->orWhereHas('approval', fn (Builder $approval) => $approval->where('project_code', 'like', $like))
                    ->orWhereHas('allocation.adl', fn (Builder $adl) => $adl->where('adl_number', 'like', $like))
                    ->orWhereHas('projectLocations', function (Builder $location) use ($like): void {
                        $location
                            ->whereHas('municipality', fn (Builder $municipality) => $municipality->where('name', 'like', $like))
                            ->orWhereHas('province', fn (Builder $province) => $province->where('name', 'like', $like))
                            ->orWhereHas('barangays', fn (Builder $barangay) => $barangay->where('name', 'like', $like));
                    });
            })
            ->latest('updated_at');
    }

    private function adlQuery(string $query, User $user): ?Builder
    {
        if (! $user->isAdmin() && ! $user->isFocal()) {
            return null;
        }

        return Adl::query()
            ->where('adl_number', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->latest('updated_at');
    }
}
