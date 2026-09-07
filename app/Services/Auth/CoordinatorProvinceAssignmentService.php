<?php

namespace App\Services\Auth;

use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Collection;

final class CoordinatorProvinceAssignmentService
{
    /**
     * Resolve the coordinator's active Bicol province assignment.
     *
     * When $repair is true, legacy/missing assignments are repaired only when
     * there is one authoritative province that can be inferred from existing
     * coordinator project relationships. The built-in local `tc` demo account
     * may fall back to Albay outside production so development access does not
     * break after older seed histories.
     */
    public function resolve(User $user, bool $repair = false): ?Province
    {
        if (! $user->isTc()) {
            return null;
        }

        $current = $this->validProvinceById($user->assigned_province_id);

        if ($current !== null) {
            return $current;
        }

        if (! $repair) {
            return null;
        }

        $inferred = $this->inferUniqueProvince($user);

        if ($inferred === null && ! app()->environment('production') && $user->username === 'tc') {
            $inferred = $this->validProvinceByCode('050500000');
        }

        if ($inferred === null) {
            return null;
        }

        if ((int) $user->assigned_province_id !== (int) $inferred->id) {
            $user->forceFill([
                'assigned_province_id' => (int) $inferred->id,
            ])->save();

            $user->setRelation('assignedProvince', $inferred);
        }

        return $inferred;
    }

    public function validProvinceById(int|string|null $provinceId): ?Province
    {
        if ($provinceId === null || $provinceId === '') {
            return null;
        }

        return Province::query()
            ->whereKey((int) $provinceId)
            ->where('is_active', true)
            ->whereIn('code', $this->bicolProvinceCodes())
            ->first();
    }

    public function validProvinceByCode(?string $provinceCode): ?Province
    {
        $provinceCode = trim((string) $provinceCode);

        if ($provinceCode === '' || ! in_array($provinceCode, $this->bicolProvinceCodes(), true)) {
            return null;
        }

        return Province::query()
            ->where('code', $provinceCode)
            ->where('is_active', true)
            ->first();
    }

    /** @return Collection<int,Province> */
    public function activeBicolProvinces(): Collection
    {
        return Province::query()
            ->where('is_active', true)
            ->whereIn('code', $this->bicolProvinceCodes())
            ->orderBy('name')
            ->get();
    }

    private function inferUniqueProvince(User $user): ?Province
    {
        $candidateIds = Project::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->where('created_by', $user->id)
                    ->orWhere('updated_by', $user->id);
            })
            ->whereNotNull('province_id')
            ->distinct()
            ->pluck('province_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($candidateIds->isEmpty()) {
            return null;
        }

        $valid = Province::query()
            ->whereIn('id', $candidateIds)
            ->where('is_active', true)
            ->whereIn('code', $this->bicolProvinceCodes())
            ->get();

        return $valid->count() === 1
            ? $valid->first()
            : null;
    }

    /** @return array<int,string> */
    private function bicolProvinceCodes(): array
    {
        return array_values(array_map(
            static fn (mixed $code): string => (string) $code,
            array_keys((array) config('tupad_mapping.provinces', [])),
        ));
    }
}
