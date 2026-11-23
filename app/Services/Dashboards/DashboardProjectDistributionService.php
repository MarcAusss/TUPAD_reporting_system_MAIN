<?php

namespace App\Services\Dashboards;

use App\Models\Municipality;
use App\Models\Project;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Support\Collection;

/**
 * Project distribution for the dashboards, drawn exactly like the
 * beneficiary distribution: province pie by district → municipality bars →
 * barangay bars, counting projects instead of beneficiaries.
 *
 * Every project the user can see is counted (the whole pipeline, like the
 * beneficiary distribution). A project implemented in several
 * municipalities / barangays counts once in each of them.
 */
final class DashboardProjectDistributionService
{
    public function __construct(
        private readonly DashboardBeneficiaryDistributionService $distribution,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, ?int $provinceId = null): array
    {
        $projects = $this->projects($user);
        $counts = $this->tally($projects, fn (Project $project): array => $this->municipalityIds($project));

        $data = $this->distribution->buildFromCounts(
            $counts,
            $provinceId,
            route('dashboard.project-distribution.barangays'),
        );

        return [
            ...$data,
            'summary' => [
                'total' => $projects->count(),
                'female' => (int) $projects->sum('beneficiaries_total'),
                'municipalities_served' => $data['summary']['municipalities_served'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function barangays(User $user, Municipality $municipality): array
    {
        $projects = $this->projects($user);
        $counts = $this->tally($projects, fn (Project $project): array => $this->barangayIds($project, (int) $municipality->id));

        return $this->distribution->barangaysFromCounts($municipality, $counts);
    }

    /** @return Collection<int, Project> */
    private function projects(User $user): Collection
    {
        return $this->provinceAccess
            ->scopeProjects(Project::query(), $user)
            ->with('projectLocations.barangays')
            ->get(['id', 'municipality_id', 'barangay_id', 'beneficiaries_total']);
    }

    /**
     * Counts keyed by place id: "beneficiaries_total" holds the number of
     * projects and "beneficiaries_female" their beneficiaries (the chart's
     * secondary figure).
     *
     * @param  Collection<int, Project>  $projects
     * @param  callable(Project): list<int>  $placesOf
     * @return Collection<string, array{beneficiaries_total: int, beneficiaries_female: int}>
     */
    private function tally(Collection $projects, callable $placesOf): Collection
    {
        $counts = [];

        foreach ($projects as $project) {
            foreach (array_unique($placesOf($project)) as $placeId) {
                $counts[$placeId]['beneficiaries_total'] = ($counts[$placeId]['beneficiaries_total'] ?? 0) + 1;
                $counts[$placeId]['beneficiaries_female'] = ($counts[$placeId]['beneficiaries_female'] ?? 0) + (int) $project->beneficiaries_total;
            }
        }

        return collect($counts)->mapWithKeys(fn (array $row, $placeId): array => [(string) $placeId => $row]);
    }

    /** @return list<int> */
    private function municipalityIds(Project $project): array
    {
        $ids = $project->projectLocations->pluck('municipality_id')->filter()->map(fn ($id): int => (int) $id)->all();

        return $ids !== [] ? $ids : array_filter([(int) $project->municipality_id]);
    }

    /** @return list<int> */
    private function barangayIds(Project $project, int $municipalityId): array
    {
        if ($project->projectLocations->isNotEmpty()) {
            return $project->projectLocations
                ->where('municipality_id', $municipalityId)
                ->flatMap(fn ($location) => $location->barangays->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return (int) $project->municipality_id === $municipalityId
            ? array_filter([(int) $project->barangay_id])
            : [];
    }
}
