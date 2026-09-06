<?php

namespace App\Services\Auth;

use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProvinceAccessService
{
    public function __construct(
        private readonly CoordinatorProvinceAssignmentService $coordinatorAssignments,
    ) {}

    public function isProvinceScoped(User $user): bool
    {
        return $user->isTc();
    }

    public function assignedProvinceId(User $user): ?int
    {
        if (! $this->isProvinceScoped($user)) {
            return null;
        }

        $province = $this->coordinatorAssignments->resolve(
            $user,
            repair: true,
        );

        return $province === null
            ? null
            : (int) $province->id;
    }

    public function canAccessProvince(User $user, Province|int|null $province): bool
    {
        if (! $this->isProvinceScoped($user)) {
            return true;
        }

        $assignedProvinceId = $this->assignedProvinceId($user);

        if ($assignedProvinceId === null || $province === null) {
            return false;
        }

        $provinceId = $province instanceof Province
            ? (int) $province->getKey()
            : (int) $province;

        return $provinceId === $assignedProvinceId;
    }

    public function canAccessProject(User $user, Project $project): bool
    {
        if (! $this->isProvinceScoped($user)) {
            return true;
        }

        $assignedProvinceId = $this->assignedProvinceId($user);

        if ($assignedProvinceId === null) {
            return false;
        }

        if ($project->projectLocations()->exists()) {
            return $project->projectLocations()
                ->where('province_id', '!=', $assignedProvinceId)
                ->doesntExist();
        }

        return $this->canAccessProvinceRecord(
            $user,
            $project->province_id,
            $project->province,
        );
    }


    /**
     * @param Builder<Project> $query
     * @return Builder<Project>
     */
    public function scopeProjects(Builder $query, User $user): Builder
    {
        if (! $this->isProvinceScoped($user)) {
            return $query;
        }

        $provinceId = $this->assignedProvinceId($user);

        if ($provinceId === null) {
            return $query->whereRaw('1 = 0');
        }

        $assignedProvinceName = $this->assignedProvinceName($user);

        return $query->where(function (Builder $projectQuery) use ($provinceId, $assignedProvinceName): void {
            $projectQuery
                ->whereHas(
                    'projectLocations',
                    fn (Builder $location): Builder => $location->where('province_id', $provinceId),
                )
                ->whereDoesntHave(
                    'projectLocations',
                    fn (Builder $location): Builder => $location->where('province_id', '!=', $provinceId),
                )
                ->orWhere(function (Builder $legacyProject) use ($provinceId, $assignedProvinceName): void {
                    $legacyProject
                        ->whereDoesntHave('projectLocations')
                        ->where(function (Builder $legacyProvince) use ($provinceId, $assignedProvinceName): void {
                            $legacyProvince->where('province_id', $provinceId);

                            if ($assignedProvinceName !== null) {
                                $legacyProvince->orWhere(function (Builder $legacyName) use ($assignedProvinceName): void {
                                    $legacyName
                                        ->whereNull('province_id')
                                        ->whereRaw('LOWER(TRIM(province)) = ?', [Str::lower($assignedProvinceName)]);
                                });
                            }
                        });
                });
        });
    }



    /**
     * @param Builder<AdlAllocation> $query
     * @return Builder<AdlAllocation>
     */
    public function scopeAdlAllocations(Builder $query, User $user): Builder
    {
        if (! $this->isProvinceScoped($user)) {
            return $query;
        }

        $provinceName = $this->assignedProvinceName($user);

        if ($provinceName === null) {
            return $query->whereRaw('1 = 0');
        }

        $normalizedProvince = Str::lower($provinceName);

        return $query->where(function (Builder $allocationQuery) use ($normalizedProvince): void {
            $allocationQuery
                ->whereRaw('LOWER(TRIM(province)) = ?', [$normalizedProvince])
                ->orWhere(function (Builder $legacyAllocation) use ($normalizedProvince): void {
                    $legacyAllocation
                        ->where(function (Builder $missingProvince): void {
                            $missingProvince
                                ->whereNull('province')
                                ->orWhere('province', '');
                        })
                        ->whereRaw('LOWER(location) LIKE ?', ['%'.$normalizedProvince.'%']);
                });
        });
    }

    /**
     * @param Builder<Municipality> $query
     * @return Builder<Municipality>
     */
    public function scopeMunicipalities(Builder $query, User $user): Builder
    {
        if (! $this->isProvinceScoped($user)) {
            return $query;
        }

        $provinceId = $this->assignedProvinceId($user);

        return $provinceId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('province_id', $provinceId);
    }

    /**
     * @param Builder<Barangay> $query
     * @return Builder<Barangay>
     */
    public function scopeBarangays(Builder $query, User $user): Builder
    {
        if (! $this->isProvinceScoped($user)) {
            return $query;
        }

        $provinceId = $this->assignedProvinceId($user);

        if ($provinceId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'municipality',
            fn (Builder $municipality): Builder =>
                $municipality->where('province_id', $provinceId)
        );
    }

    /**
     * @param Builder<Province> $query
     * @return Builder<Province>
     */
    public function scopeProvinces(Builder $query, User $user): Builder
    {
        if (! $this->isProvinceScoped($user)) {
            return $query;
        }

        $provinceId = $this->assignedProvinceId($user);

        if ($provinceId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($provinceId);
    }

    private function canAccessProvinceRecord(
        User $user,
        mixed $provinceId,
        mixed $legacyProvinceName,
    ): bool {
        if (! $this->isProvinceScoped($user)) {
            return true;
        }

        $assignedProvinceId = $this->assignedProvinceId($user);

        if ($assignedProvinceId === null) {
            return false;
        }

        if ($provinceId !== null) {
            return (int) $provinceId === $assignedProvinceId;
        }

        $assignedProvinceName = $this->assignedProvinceName($user);
        $legacyProvinceName = trim((string) $legacyProvinceName);

        return $assignedProvinceName !== null
            && $legacyProvinceName !== ''
            && Str::lower($legacyProvinceName) === Str::lower($assignedProvinceName);
    }


    private function assignedProvinceName(User $user): ?string
    {
        $province = $this->coordinatorAssignments->resolve(
            $user,
            repair: true,
        );

        $name = trim((string) $province?->name);

        return $name === '' ? null : $name;
    }
}
