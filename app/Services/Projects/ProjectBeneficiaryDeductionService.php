<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\ProjectBeneficiaryDeduction;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actual Beneficiary Mapping.
 *
 * When the Focal completes the obligation tranches with fewer beneficiaries
 * than the project declared, the TUPAD Coordinator records which barangays
 * the excluded beneficiaries came from:
 *
 *   Actual Beneficiary Mapping = Beneficiary Mapping − TC deductions per barangay
 *
 * The Beneficiary Mapping is the project's Beneficiary Mapping Source
 * (project_beneficiary_addresses). Projects that never encoded it fall back
 * to their Project Location barangay allocations from the create form; those
 * allocations are copied into the Beneficiary Mapping Source when the
 * deductions are saved so reports can use them.
 */
class ProjectBeneficiaryDeductionService
{
    public const SOURCE_ADDRESSES = 'beneficiary_addresses';
    public const SOURCE_PROJECT_LOCATIONS = 'project_locations';

    /**
     * Beneficiaries (and female beneficiaries) declared on the project but
     * not covered by the completed obligation tranches. Null until the
     * Focal completes the tranches.
     *
     * @return array{total:int,female:int}|null
     */
    public function shortfall(Project $project): ?array
    {
        if (
            $project->implementation_mode !== ImplementationMode::DIRECT_ADMINISTRATION
            || $project->obligations_completed_at === null
        ) {
            return null;
        }

        $project->loadMissing('obligations');

        return [
            'total' => max(0, (int) $project->beneficiaries_total - (int) $project->obligations->sum('beneficiaries_total')),
            'female' => max(0, (int) $project->beneficiaries_female - (int) $project->obligations->sum('beneficiaries_female')),
        ];
    }

    public function needsDeduction(Project $project): bool
    {
        $shortfall = $this->shortfall($project);

        return $shortfall !== null && ($shortfall['total'] > 0 || $shortfall['female'] > 0);
    }

    public function isRecorded(Project $project): bool
    {
        return $project->beneficiary_deductions_recorded_at !== null;
    }

    /** Where the barangay list comes from for this project. */
    public function source(Project $project): string
    {
        $project->loadMissing('beneficiaryAddresses');

        return $project->beneficiaryAddresses->isNotEmpty()
            ? self::SOURCE_ADDRESSES
            : self::SOURCE_PROJECT_LOCATIONS;
    }

    /**
     * One row per barangay: mapped, deducted, and actual counts. Keyed by
     * barangay id (also the key used by the deduction form).
     *
     * @return array<int, array{barangay_id:int,municipality_id:int,province_id:?int,label:string,mapped_total:int,mapped_female:int,deducted_total:int,deducted_female:int,actual_total:int,actual_female:int,address:?ProjectBeneficiaryAddress}>
     */
    public function rows(Project $project): array
    {
        return $this->source($project) === self::SOURCE_ADDRESSES
            ? $this->addressRows($project)
            : $this->projectLocationRows($project);
    }

    /**
     * Save the per-barangay deductions. They must add up exactly to the
     * shortfall (total and female) and cannot exceed any barangay.
     *
     * @param  array<int|string, array{total?:mixed,female?:mixed}>  $input  keyed by barangay id
     * @return list<array{field:string,old:string,new:string}> changes for the edit log
     */
    public function save(Project $project, array $input, User $user): array
    {
        $shortfall = $this->shortfall($project);

        if ($shortfall === null || ! $this->needsDeduction($project)) {
            throw ValidationException::withMessages([
                'deductions' => 'This project has no beneficiary shortfall to deduct.',
            ]);
        }

        $rows = $this->rows($project);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'deductions' => 'This project has no barangay allocations. Encode the Beneficiary Mapping Source (Beneficiaries tab) first.',
            ]);
        }

        $errors = [];
        $values = [];

        foreach ($rows as $barangayId => $row) {
            $total = (int) ($input[$barangayId]['total'] ?? 0);
            $female = (int) ($input[$barangayId]['female'] ?? 0);

            if ($total < 0 || $female < 0) {
                $errors["deductions.{$barangayId}.total"] = "{$row['label']}: deductions cannot be negative.";
            } elseif ($total > $row['mapped_total']) {
                $errors["deductions.{$barangayId}.total"] = "{$row['label']}: cannot deduct more than the {$row['mapped_total']} beneficiaries mapped in this barangay.";
            } elseif ($female > $row['mapped_female']) {
                $errors["deductions.{$barangayId}.female"] = "{$row['label']}: cannot deduct more than the {$row['mapped_female']} female beneficiaries mapped in this barangay.";
            } elseif ($female > $total) {
                $errors["deductions.{$barangayId}.female"] = "{$row['label']}: female deducted cannot exceed the total deducted.";
            }

            $values[$barangayId] = ['total' => $total, 'female' => $female];
        }

        $sumTotal = array_sum(array_column($values, 'total'));
        $sumFemale = array_sum(array_column($values, 'female'));

        if ($errors === [] && $sumTotal !== $shortfall['total']) {
            $errors['deductions'] = sprintf(
                'The deductions total %d, but %d beneficiaries were not included in the obligation. They must match exactly.',
                $sumTotal,
                $shortfall['total'],
            );
        } elseif ($errors === [] && $sumFemale !== $shortfall['female']) {
            $errors['deductions'] = sprintf(
                'The female deductions total %d, but %d female beneficiaries were not included in the obligation. They must match exactly.',
                $sumFemale,
                $shortfall['female'],
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($project, $rows, $values, $user): array {
            $changes = [];

            foreach ($rows as $barangayId => $row) {
                $address = $row['address'] ?? $this->createAddressFromLocation($project, $row, $user);
                $new = $values[$barangayId];
                $old = ['total' => $row['deducted_total'], 'female' => $row['deducted_female']];

                if ($new === $old) {
                    continue;
                }

                $changes[] = [
                    'field' => $row['label'].' deducted',
                    'old' => $old['total'].' ('.$old['female'].' female)',
                    'new' => $new['total'].' ('.$new['female'].' female)',
                ];

                if ($new['total'] === 0 && $new['female'] === 0) {
                    ProjectBeneficiaryDeduction::query()->where('project_beneficiary_address_id', $address->id)->delete();

                    continue;
                }

                ProjectBeneficiaryDeduction::query()->updateOrCreate(
                    ['project_beneficiary_address_id' => $address->id],
                    [
                        'project_id' => $project->id,
                        'beneficiaries_deducted' => $new['total'],
                        'female_deducted' => $new['female'],
                        'recorded_by' => $user->id,
                    ],
                );
            }

            $project->forceFill([
                'beneficiary_deductions_recorded_at' => now(),
                'beneficiary_deductions_recorded_by' => $user->id,
            ])->save();

            $project->unsetRelation('beneficiaryAddresses');

            return $changes;
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function addressRows(Project $project): array
    {
        $project->loadMissing(['beneficiaryAddresses.municipality', 'beneficiaryAddresses.barangay', 'beneficiaryAddresses.deduction']);

        $rows = [];

        foreach ($project->beneficiaryAddresses as $address) {
            $rows[(int) $address->barangay_id] = [
                'barangay_id' => (int) $address->barangay_id,
                'municipality_id' => (int) $address->municipality_id,
                'province_id' => $address->province_id ? (int) $address->province_id : null,
                'label' => $this->label($address->barangay?->name, $address->municipality?->name, $address->id),
                'mapped_total' => (int) $address->beneficiaries_total,
                'mapped_female' => (int) $address->beneficiaries_female,
                'deducted_total' => (int) ($address->deduction?->beneficiaries_deducted ?? 0),
                'deducted_female' => (int) ($address->deduction?->female_deducted ?? 0),
                'actual_total' => $address->actualTotal(),
                'actual_female' => $address->actualFemale(),
                'address' => $address,
            ];
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function projectLocationRows(Project $project): array
    {
        $project->loadMissing(['projectLocations.municipality', 'projectLocations.barangays']);

        $rows = [];

        foreach ($project->projectLocations as $location) {
            /** @var ProjectLocation $location */
            foreach ($location->barangays as $barangay) {
                $total = (int) ($barangay->pivot->beneficiaries_total ?? 0);
                $female = (int) ($barangay->pivot->beneficiaries_female ?? 0);
                $existing = $rows[(int) $barangay->id] ?? null;

                $rows[(int) $barangay->id] = [
                    'barangay_id' => (int) $barangay->id,
                    'municipality_id' => (int) $location->municipality_id,
                    'province_id' => $location->province_id ? (int) $location->province_id : ($project->province_id ? (int) $project->province_id : null),
                    'label' => $this->label($barangay->name, $location->municipality?->name, $barangay->id),
                    'mapped_total' => $total + (int) ($existing['mapped_total'] ?? 0),
                    'mapped_female' => $female + (int) ($existing['mapped_female'] ?? 0),
                    'deducted_total' => 0,
                    'deducted_female' => 0,
                    'actual_total' => $total + (int) ($existing['actual_total'] ?? 0),
                    'actual_female' => $female + (int) ($existing['actual_female'] ?? 0),
                    'address' => null,
                ];
            }
        }

        return $rows;
    }

    /** Copy a Project Location barangay allocation into the Beneficiary Mapping Source. */
    private function createAddressFromLocation(Project $project, array $row, User $user): ProjectBeneficiaryAddress
    {
        return ProjectBeneficiaryAddress::query()->create([
            'project_id' => $project->id,
            'province_id' => $row['province_id'],
            'municipality_id' => $row['municipality_id'],
            'barangay_id' => $row['barangay_id'],
            'beneficiaries_total' => $row['mapped_total'],
            'beneficiaries_female' => $row['mapped_female'],
            'encoded_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function label(?string $barangay, ?string $municipality, int $fallbackId): string
    {
        return collect([$barangay, $municipality])->filter()->implode(', ') ?: 'Barangay #'.$fallbackId;
    }
}
