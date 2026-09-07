<?php

namespace App\Services\Projects;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProjectLocationCanonicalService
{
    public const COMPATIBILITY_FIELDS = [
        'province_id',
        'municipality_id',
        'barangay_id',
        'province',
        'district',
        'municipality',
        'barangay',
        'income_class',
    ];

    /**
     * project_locations + project_location_barangay are authoritative for official
     * project geography. The fields on projects are compatibility snapshots only.
     */
    public function compatibilitySnapshot(Project $project): ?array
    {
        $location = $project->projectLocations()
            ->with(['province', 'municipality'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $location) {
            return null;
        }

        $barangay = $location->barangays()
            ->orderBy('barangays.id')
            ->first();

        if (! $barangay) {
            throw new LogicException(
                "Project #{$project->id} canonical location #{$location->id} has no barangay."
            );
        }

        $this->assertHierarchy($location, $barangay);

        return [
            'province_id' => (int) $location->province_id,
            'municipality_id' => (int) $location->municipality_id,
            'barangay_id' => (int) $barangay->id,
            'province' => $location->province?->name,
            'district' => $location->district,
            'municipality' => $location->municipality?->name,
            'barangay' => $barangay->name,
            'income_class' => $location->municipality?->income_class,
        ];
    }

    public function synchronizeCompatibilitySnapshot(Project $project): void
    {
        $snapshot = $this->compatibilitySnapshot($project);

        if ($snapshot === null) {
            return;
        }

        DB::table($project->getTable())
            ->where($project->getKeyName(), $project->getKey())
            ->update($snapshot);

        $project->forceFill($snapshot);
        $project->syncOriginalAttributes(array_keys($snapshot));
        $project->unsetRelation('provinceReference');
        $project->unsetRelation('municipalityReference');
        $project->unsetRelation('barangayReference');
    }

    public function createSingleCanonicalLocation(
        Project $project,
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
    ): ProjectLocation {
        $this->assertReferenceHierarchy($province, $municipality, $barangay);

        if ($project->projectLocations()->exists()) {
            throw new LogicException(
                "Project #{$project->id} already has canonical project location records."
            );
        }

        return DB::transaction(function () use ($project, $province, $municipality, $barangay): ProjectLocation {
            $location = $project->projectLocations()->create([
                'province_id' => $province->id,
                'municipality_id' => $municipality->id,
                'district' => $municipality->district ?: ($project->district ?: 'Not Assigned'),
                'sort_order' => 1,
            ]);

            $location->barangays()->sync([
                $barangay->id => [
                    'beneficiaries_total' => (int) $project->beneficiaries_total,
                    'beneficiaries_female' => (int) $project->beneficiaries_female,
                ],
            ]);

            $this->synchronizeCompatibilitySnapshot($project);

            return $location->fresh(['province', 'municipality', 'barangays']);
        });
    }

    /** @return array{province:Province, municipality:Municipality, barangay:Barangay}|null */
    public function resolveLegacyLocation(Project $project): ?array
    {
        if ($project->province_id && $project->municipality_id && $project->barangay_id) {
            $province = Province::query()->find($project->province_id);
            $municipality = Municipality::query()->find($project->municipality_id);
            $barangay = Barangay::query()->find($project->barangay_id);

            if ($province && $municipality && $barangay) {
                try {
                    $this->assertReferenceHierarchy($province, $municipality, $barangay);

                    return compact('province', 'municipality', 'barangay');
                } catch (LogicException) {
                    // Fall through to the text snapshots. This lets the repair
                    // command recover records whose old reference IDs conflict.
                }
            }
        }

        $provinceName = trim((string) $project->province);
        $municipalityName = trim((string) $project->municipality);
        $barangayName = trim((string) $project->barangay);

        if ($provinceName === '' || $municipalityName === '' || $barangayName === '') {
            return null;
        }

        $province = Province::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($provinceName)])
            ->first();

        if (! $province) {
            return null;
        }

        $municipality = Municipality::query()
            ->where('province_id', $province->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($municipalityName)])
            ->first();

        if (! $municipality) {
            return null;
        }

        $barangay = Barangay::query()
            ->where('municipality_id', $municipality->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($barangayName)])
            ->first();

        if (! $barangay) {
            return null;
        }

        return compact('province', 'municipality', 'barangay');
    }

    public function assertProjectIntegrity(Project $project): void
    {
        $locations = $project->projectLocations()
            ->with(['province', 'municipality', 'barangays'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            throw new LogicException("Project #{$project->id} has no canonical project location.");
        }

        if ($locations->pluck('province_id')->unique()->count() !== 1) {
            throw new LogicException(
                "Project #{$project->id} contains canonical locations from more than one province."
            );
        }

        foreach ($locations as $location) {
            if ($location->barangays->isEmpty()) {
                throw new LogicException(
                    "Project #{$project->id} canonical location #{$location->id} has no barangay."
                );
            }

            foreach ($location->barangays as $barangay) {
                $this->assertHierarchy($location, $barangay);
            }
        }

        $snapshot = $this->compatibilitySnapshot($project);

        foreach ($snapshot ?? [] as $field => $canonicalValue) {
            $current = $project->getAttribute($field);

            if ((string) ($current ?? '') !== (string) ($canonicalValue ?? '')) {
                throw new LogicException(
                    "Project #{$project->id} compatibility field [{$field}] conflicts with its canonical project location."
                );
            }
        }
    }

    public function applyCanonicalSnapshotBeforeSave(Project $project): void
    {
        if (! $project->exists || ! $project->isDirty(self::COMPATIBILITY_FIELDS)) {
            return;
        }

        if (! $project->projectLocations()->exists()) {
            return;
        }

        $snapshot = $this->compatibilitySnapshot($project);

        if ($snapshot !== null) {
            $project->forceFill($snapshot);
        }
    }

    public function assertReferenceHierarchy(
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
    ): void {
        if ((int) $municipality->province_id !== (int) $province->id) {
            throw new LogicException(
                "Municipality #{$municipality->id} does not belong to province #{$province->id}."
            );
        }

        if ((int) $barangay->municipality_id !== (int) $municipality->id) {
            throw new LogicException(
                "Barangay #{$barangay->id} does not belong to municipality #{$municipality->id}."
            );
        }
    }

    private function assertHierarchy(ProjectLocation $location, Barangay $barangay): void
    {
        if (! $location->municipality || ! $location->province) {
            throw new LogicException(
                "Project location #{$location->id} references missing geographic records."
            );
        }

        $this->assertReferenceHierarchy(
            $location->province,
            $location->municipality,
            $barangay,
        );
    }
}
