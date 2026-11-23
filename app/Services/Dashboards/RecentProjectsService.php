<?php

namespace App\Services\Dashboards;

use App\Models\Project;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Database\Eloquent\Collection;

/** Most recently updated official projects visible to a user. */
final class RecentProjectsService
{
    public function __construct(private readonly ProvinceAccessService $provinceAccess) {}

    /** @return Collection<int, Project> */
    public function for(User $user, int $limit = 6): Collection
    {
        return $this->provinceAccess->scopeProjects(Project::query(), $user)
            ->withStatusEnteredAt()
            ->with(['approval', 'allocation.adl'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }
}
