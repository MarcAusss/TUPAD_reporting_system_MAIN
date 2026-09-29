<?php

namespace App\Services\Projects;

use App\Enums\PpeType;
use App\Models\Barangay;
use App\Models\ProjectBarangayPpeItemCount;
use App\Models\ProjectBarangayPpeProfile;
use App\Models\ProjectPpeItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validates and persists the per-barangay hazardous-worker and PPE-recipient
 * breakdown that rides along with a project's beneficiary address allocation.
 *
 * Called by ProjectBeneficiaryAddressService::sync() from inside its own
 * DB::transaction(), so this class never opens a transaction of its own —
 * the address rows and the PPE distribution rows commit or roll back
 * together.
 */
class ProjectBarangayPpeDistributionService
{
    /**
     * Whether any barangay row in the submitted allocation carries PPE
     * distribution input. Rows are built by
     * ProjectBeneficiaryAddressService::sync(), where each row optionally
     * carries a 'ppe' key (null when the submission has no PPE fields at
     * all, e.g. the Beneficiary Replacement flow's address step).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function hasDistributionInput(Collection $rows): bool
    {
        return $rows->contains(fn (array $row): bool => $row['ppe'] !== null);
    }

    /**
     * @param  Collection<int, array{municipality_id:int, barangay_id:int, beneficiaries_total:int, beneficiaries_female:int, ppe: array{hazardous_workers: mixed, complete_set_workers: mixed, ppe_items: array<int|string, mixed>}|null}>  $rows
     */
    public function sync(Project $project, User $user, Collection $rows): void
    {
        if (! $this->hasDistributionInput($rows)) {
            $this->pruneRemovedBarangays($project, $rows->pluck('barangay_id')->all());

            return;
        }

        $ppeItems = $project->ppeItems;

        if ($ppeItems->isEmpty()) {
            // No PPE declared for this project at all: nothing to distribute.
            // Treat any submitted PPE fields as noise rather than an error,
            // since the Beneficiary Mapping card would not have shown the
            // distribution inputs in the first place.
            $this->pruneRemovedBarangays($project, $rows->pluck('barangay_id')->all());

            return;
        }

        foreach ($rows as $row) {
            if ($row['ppe'] === null) {
                $barangayName = Barangay::query()->find($row['barangay_id'])?->name ?? "barangay #{$row['barangay_id']}";

                throw ValidationException::withMessages([
                    'beneficiary_addresses' => "PPE distribution is required for {$barangayName} once any barangay includes it. Complete the distribution for every allocated barangay.",
                ]);
            }
        }

        $hazardousItems = $ppeItems->filter(
            fn (ProjectPpeItem $item): bool => $item->ppe_type === PpeType::HAZARDOUS
        );

        $barangayTotals = $rows->keyBy('barangay_id')->map(
            fn (array $row): int => (int) $row['beneficiaries_total']
        );

        /**
         * recipients(itemId, barangayId): every PPE item, including one
         * declared for 100% of project beneficiaries, is explicitly
         * declared per barangay by the coordinator — nothing is derived
         * automatically, so the hazardous/non-hazardous split is always
         * exactly what was typed in.
         */
        $recipientsFor = function (int $itemId, int $barangayId) use ($rows): int {
            $row = $rows->firstWhere('barangay_id', $barangayId);
            $submitted = $row['ppe']['ppe_items'][$itemId] ?? $row['ppe']['ppe_items'][(string) $itemId] ?? 0;

            return (int) $submitted;
        };

        // Rule (e): every submitted PPE item id must belong to this project.
        $ppeItemIds = $ppeItems->pluck('id')->all();

        foreach ($rows as $row) {
            foreach (array_keys($row['ppe']['ppe_items'] ?? []) as $submittedItemId) {
                if (! in_array((int) $submittedItemId, $ppeItemIds, true)) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "PPE item #{$submittedItemId} does not belong to this project.",
                    ]);
                }
            }
        }

        // Rule (b): recipients must not exceed the barangay's own total.
        foreach ($rows as $row) {
            $barangayId = $row['barangay_id'];
            $barangayName = Barangay::query()->find($barangayId)?->name ?? "barangay #{$barangayId}";
            $total = $barangayTotals->get($barangayId, 0);

            foreach ($ppeItems as $item) {
                $recipients = $recipientsFor($item->id, $barangayId);

                if ($recipients > $total) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "{$item->product} recipients in {$barangayName} ({$recipients}) cannot exceed that barangay's total beneficiaries ({$total}).",
                    ]);
                }

                if ($recipients < 0) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "{$item->product} recipients in {$barangayName} cannot be negative.",
                    ]);
                }
            }
        }

        // Rule (a): every item's recipients must sum to exactly its
        // declared beneficiary_count across all barangays.
        foreach ($ppeItems as $item) {
            $sum = $rows->sum(fn (array $row): int => $recipientsFor($item->id, $row['barangay_id']));

            if ($sum !== (int) $item->beneficiary_count) {
                throw ValidationException::withMessages([
                    'beneficiary_addresses' => sprintf(
                        '%s recipients must total %d across all barangays (currently %d).',
                        $item->product,
                        (int) $item->beneficiary_count,
                        $sum,
                    ),
                ]);
            }
        }

        // Rules (c) and (d): hazardous_workers / complete_set_workers bounds.
        foreach ($rows as $row) {
            $barangayId = $row['barangay_id'];
            $barangayName = Barangay::query()->find($barangayId)?->name ?? "barangay #{$barangayId}";
            $total = $barangayTotals->get($barangayId, 0);

            $hazardousWorkers = (int) ($row['ppe']['hazardous_workers'] ?? 0);
            $completeSetWorkers = $row['ppe']['complete_set_workers'] ?? null;
            $completeSetWorkers = $completeSetWorkers === null || $completeSetWorkers === ''
                ? null
                : (int) $completeSetWorkers;

            if ($hazardousItems->isEmpty()) {
                if ($hazardousWorkers !== 0) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "Hazardous workers for {$barangayName} must be 0; this project has no hazardous PPE items.",
                    ]);
                }

                if ($completeSetWorkers !== null && $completeSetWorkers !== 0) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "Complete set workers for {$barangayName} must be 0; this project has no hazardous PPE items.",
                    ]);
                }

                continue;
            }

            $hazardousRecipients = $hazardousItems->map(
                fn (ProjectPpeItem $item): int => $recipientsFor($item->id, $barangayId)
            );

            $maxHazardousRecipients = (int) $hazardousRecipients->max();
            $sumHazardousRecipients = (int) $hazardousRecipients->sum();
            $minHazardousRecipients = (int) $hazardousRecipients->min();
            $hazardousCeiling = min($sumHazardousRecipients, $total);

            if ($hazardousWorkers > $total) {
                throw ValidationException::withMessages([
                    'beneficiary_addresses' => "Hazardous workers for {$barangayName} ({$hazardousWorkers}) cannot exceed that barangay's total beneficiaries ({$total}).",
                ]);
            }

            if ($hazardousWorkers < $maxHazardousRecipients) {
                throw ValidationException::withMessages([
                    'beneficiary_addresses' => "Hazardous workers for {$barangayName} must be at least {$maxHazardousRecipients}, the largest single hazardous-item recipient count in that barangay.",
                ]);
            }

            if ($hazardousWorkers > $hazardousCeiling) {
                throw ValidationException::withMessages([
                    'beneficiary_addresses' => "Hazardous workers for {$barangayName} cannot exceed {$hazardousCeiling}, the combined recipients of all hazardous items in that barangay.",
                ]);
            }

            if ($completeSetWorkers !== null) {
                if ($completeSetWorkers > $minHazardousRecipients) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "Complete set workers for {$barangayName} cannot exceed {$minHazardousRecipients}, the smallest hazardous-item recipient count in that barangay.",
                    ]);
                }

                if ($completeSetWorkers > $hazardousWorkers) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "Complete set workers for {$barangayName} cannot exceed the barangay's hazardous worker count ({$hazardousWorkers}).",
                    ]);
                }

                if ($completeSetWorkers < 0) {
                    throw ValidationException::withMessages([
                        'beneficiary_addresses' => "Complete set workers for {$barangayName} cannot be negative.",
                    ]);
                }
            }
        }

        // Everything checks out — replace the stored rows for this project.
        ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->delete();
        ProjectBarangayPpeItemCount::query()->where('project_id', $project->id)->delete();

        foreach ($rows as $row) {
            $barangayId = $row['barangay_id'];
            $hazardousWorkers = (int) ($row['ppe']['hazardous_workers'] ?? 0);
            $completeSetWorkers = $row['ppe']['complete_set_workers'] ?? null;
            $completeSetWorkers = $completeSetWorkers === null || $completeSetWorkers === ''
                ? null
                : (int) $completeSetWorkers;

            ProjectBarangayPpeProfile::query()->create([
                'project_id' => $project->id,
                'barangay_id' => $barangayId,
                'hazardous_workers' => $hazardousWorkers,
                'complete_set_workers' => $completeSetWorkers,
                'encoded_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($ppeItems as $item) {
                ProjectBarangayPpeItemCount::query()->create([
                    'project_id' => $project->id,
                    'barangay_id' => $barangayId,
                    'project_ppe_item_id' => $item->id,
                    'recipients' => $recipientsFor($item->id, $barangayId),
                ]);
            }
        }
    }

    /**
     * No PPE fields were submitted at all (e.g. the Beneficiary Replacement
     * flow's optional address step) — leave every still-allocated
     * barangay's stored distribution untouched, and drop rows only for
     * barangays that are no longer part of the allocation.
     *
     * @param  array<int, int>  $keptBarangayIds
     */
    private function pruneRemovedBarangays(Project $project, array $keptBarangayIds): void
    {
        ProjectBarangayPpeProfile::query()
            ->where('project_id', $project->id)
            ->whereNotIn('barangay_id', $keptBarangayIds)
            ->delete();

        ProjectBarangayPpeItemCount::query()
            ->where('project_id', $project->id)
            ->whereNotIn('barangay_id', $keptBarangayIds)
            ->delete();
    }
}
