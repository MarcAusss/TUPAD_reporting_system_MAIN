<?php

namespace App\Services\Projects;

use App\Enums\PpeType;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\ProjectPpeItem;
use Illuminate\Support\Collection;

/**
 * Read-only per-barangay cost and hazardous/non-hazardous PPE breakdown for
 * a project, built from the project's Beneficiary Mapping Source addresses
 * and (when encoded) the barangay PPE distribution. Never writes anything.
 */
class BarangayCostBreakdownService
{
    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project): array
    {
        $project->loadMissing([
            'beneficiaryAddresses.municipality',
            'beneficiaryAddresses.barangay',
            'beneficiaryAddresses.province',
            'ppeItems',
            'barangayPpeProfiles',
            'barangayPpeItemCounts',
        ]);

        $addresses = $project->beneficiaryAddresses;
        $ppeItems = $project->ppeItems;

        $workingDays = (int) $project->number_of_days;
        $wageRateCents = $this->toCents($project->wage_rate);
        $insuranceRateCents = $this->toCents($project->insurance_rate);
        $totalBeneficiaries = (int) $project->beneficiaries_total;
        $insuredBeneficiaries = $project->insurance_beneficiaries !== null
            ? (int) $project->insurance_beneficiaries
            : $totalBeneficiaries;

        $status = [
            'no_addresses' => $addresses->isEmpty(),
            'addresses_out_of_date' => $this->addressesOutOfDate($project, $addresses),
            'distribution_invalid' => false,
        ];

        if ($addresses->isEmpty()) {
            return [
                'basis' => 'exact',
                'status' => $status,
                'rates' => $this->rateStrip($workingDays, $wageRateCents, $insuranceRateCents, $ppeItems),
                'rows' => [],
                'totals' => $this->emptyTotals(),
                'reconciliation' => $this->reconciliation($project, 0, 0, 0),
            ];
        }

        $profiles = $project->barangayPpeProfiles->keyBy('barangay_id');
        $itemCounts = $project->barangayPpeItemCounts->groupBy('barangay_id');

        $isEncoded = $profiles->isNotEmpty()
            && $addresses->every(fn (ProjectBeneficiaryAddress $address): bool => $profiles->has($address->barangay_id));

        $basis = $ppeItems->isEmpty() || $isEncoded ? 'exact' : 'estimated';

        $hazardousItems = $ppeItems->filter(
            fn (ProjectPpeItem $item): bool => $item->ppe_type === PpeType::HAZARDOUS
        );

        // Informational only: whether an item was originally declared for
        // 100% of project beneficiaries. No longer used to auto-derive
        // recipients — every item's per-barangay count is explicitly
        // declared by the coordinator (see ProjectBarangayPpeDistributionService).
        $givenToEveryone = $ppeItems->filter(
            fn (ProjectPpeItem $item): bool => (int) $item->beneficiary_count === $totalBeneficiaries
        )->keyBy('id');

        $weights = $addresses->mapWithKeys(
            fn (ProjectBeneficiaryAddress $address): array => [$address->barangay_id => (int) $address->beneficiaries_total]
        )->all();

        $insuredByBarangay = $this->apportion($insuredBeneficiaries, $weights);

        /**
         * When the distribution has not been encoded, every item's
         * beneficiary_count is apportioned across barangays by weight so
         * the card can still show a plausible (clearly labelled
         * "Estimated") breakdown instead of an empty one.
         */
        $estimatedRecipients = [];

        if ($basis === 'estimated') {
            foreach ($ppeItems as $item) {
                $estimatedRecipients[$item->id] = $this->apportion((int) $item->beneficiary_count, $weights);
            }
        }

        $recipientsFor = function (ProjectPpeItem $item, int $barangayId) use (
            $basis,
            $itemCounts,
            $estimatedRecipients,
        ): int {
            if ($basis === 'exact') {
                $stored = $itemCounts->get($barangayId, collect())
                    ->firstWhere('project_ppe_item_id', $item->id);

                return $stored ? (int) $stored->recipients : 0;
            }

            return $estimatedRecipients[$item->id][$barangayId] ?? 0;
        };

        $distributionInvalid = false;
        $rows = [];

        foreach ($addresses as $address) {
            $barangayId = $address->barangay_id;
            $beneficiaries = (int) $address->beneficiaries_total;
            $female = (int) $address->beneficiaries_female;

            $personDays = $beneficiaries * $workingDays;
            $wagesCents = $beneficiaries * $wageRateCents * $workingDays;

            $insured = $insuredByBarangay[$barangayId] ?? 0;
            $insuranceCents = $insured * $insuranceRateCents;

            $ppeItemRows = [];
            $ppeCents = 0;
            $ppeHazardousCents = 0;
            $ppeNonHazardousCents = 0;

            foreach ($ppeItems as $item) {
                $recipients = $recipientsFor($item, $barangayId);

                if ($recipients > $beneficiaries) {
                    $distributionInvalid = true;
                }

                $unitAmountCents = $this->toCents($item->unit_amount);
                $quantity = max(1, (int) $item->quantity);
                $amountCents = $recipients * $unitAmountCents * $quantity;

                $ppeCents += $amountCents;

                if ($item->ppe_type === PpeType::HAZARDOUS) {
                    $ppeHazardousCents += $amountCents;
                } else {
                    $ppeNonHazardousCents += $amountCents;
                }

                $ppeItemRows[] = [
                    'item_id' => $item->id,
                    'product' => $item->product,
                    'type' => $item->ppe_type,
                    'type_label' => $item->ppe_type->label(),
                    'given_to_everyone' => $givenToEveryone->has($item->id),
                    'recipients' => $recipients,
                    'unit_amount_cents' => $unitAmountCents,
                    'quantity' => $quantity,
                    'amount_cents' => $amountCents,
                ];
            }

            if ($basis === 'exact') {
                $profile = $profiles->get($barangayId);
                $hazardousWorkers = $profile ? (int) $profile->hazardous_workers : 0;
                $completeSetWorkers = $profile?->complete_set_workers !== null
                    ? (int) $profile->complete_set_workers
                    : null;
            } else {
                $hazardousRecipients = $hazardousItems->map(
                    fn (ProjectPpeItem $item): int => $recipientsFor($item, $barangayId)
                );
                $hazardousWorkers = $hazardousItems->isEmpty() ? 0 : (int) $hazardousRecipients->max();
                $completeSetWorkers = null;
            }

            if ($hazardousWorkers > $beneficiaries) {
                $distributionInvalid = true;
            }

            $totalCents = $wagesCents + $insuranceCents + $ppeCents;
            $averagePerBeneficiaryCents = $beneficiaries > 0
                ? (int) round($totalCents / $beneficiaries)
                : 0;
            $femaleAmountCents = $beneficiaries > 0
                ? (int) round($totalCents * $female / $beneficiaries)
                : 0;

            $rows[] = [
                'barangay_id' => $barangayId,
                'municipality' => $address->municipality?->name ?? '—',
                'district' => $address->municipality?->district ?: '—',
                'province' => $address->province?->name ?? '—',
                'barangay' => $address->barangay?->name ?? '—',
                'beneficiaries' => $beneficiaries,
                'female' => $female,
                'female_amount_cents' => $femaleAmountCents,
                'person_days' => $personDays,
                'wages_cents' => $wagesCents,
                'insured' => $insured,
                'insurance_cents' => $insuranceCents,
                'ppe_cents' => $ppeCents,
                'ppe_hazardous_cents' => $ppeHazardousCents,
                'ppe_non_hazardous_cents' => $ppeNonHazardousCents,
                'ppe_items' => $ppeItemRows,
                'hazardous' => $hazardousWorkers,
                'non_hazardous' => max(0, $beneficiaries - $hazardousWorkers),
                'complete_set' => $completeSetWorkers,
                'total_cents' => $totalCents,
                'average_per_beneficiary_cents' => $averagePerBeneficiaryCents,
                'estimated' => $basis === 'estimated',
            ];
        }

        // Rule (a)-equivalent read-only check: stored recipients must still
        // sum to each item's current beneficiary_count.
        if ($basis === 'exact') {
            foreach ($ppeItems as $item) {
                $sum = collect($rows)->sum(
                    fn (array $row): int => collect($row['ppe_items'])->firstWhere('item_id', $item->id)['recipients'] ?? 0
                );

                if ($sum !== (int) $item->beneficiary_count) {
                    $distributionInvalid = true;
                }
            }
        }

        $status['distribution_invalid'] = $distributionInvalid;

        $totals = $this->totalsFrom($rows);

        return [
            'basis' => $basis,
            'status' => $status,
            'rates' => $this->rateStrip($workingDays, $wageRateCents, $insuranceRateCents, $ppeItems),
            'rows' => $rows,
            'totals' => $totals,
            'reconciliation' => $this->reconciliation(
                $project,
                $totals['wages_cents'],
                $totals['insurance_cents'],
                $totals['ppe_cents'],
            ),
        ];
    }

    /**
     * @param  Collection<int, ProjectPpeItem>  $ppeItems
     * @return array<string, mixed>
     */
    private function rateStrip(
        int $workingDays,
        int $wageRateCents,
        int $insuranceRateCents,
        Collection $ppeItems,
    ): array {
        return [
            'working_days' => $workingDays,
            'wage_rate_cents' => $wageRateCents,
            'wage_per_beneficiary_cents' => $wageRateCents * $workingDays,
            'insurance_per_beneficiary_cents' => $insuranceRateCents,
            'ppe_unit_costs' => $ppeItems->map(fn (ProjectPpeItem $item): array => [
                'item_id' => $item->id,
                'product' => $item->product,
                'type' => $item->ppe_type,
                'type_label' => $item->ppe_type->label(),
                'unit_amount_cents' => $this->toCents($item->unit_amount),
                'quantity' => max(1, (int) $item->quantity),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totalsFrom(array $rows): array
    {
        $totals = $this->emptyTotals();

        foreach ($rows as $row) {
            $totals['beneficiaries'] += $row['beneficiaries'];
            $totals['female'] += $row['female'];
            $totals['female_amount_cents'] += $row['female_amount_cents'];
            $totals['person_days'] += $row['person_days'];
            $totals['wages_cents'] += $row['wages_cents'];
            $totals['insured'] += $row['insured'];
            $totals['insurance_cents'] += $row['insurance_cents'];
            $totals['ppe_cents'] += $row['ppe_cents'];
            $totals['ppe_hazardous_cents'] += $row['ppe_hazardous_cents'];
            $totals['ppe_non_hazardous_cents'] += $row['ppe_non_hazardous_cents'];
            $totals['hazardous'] += $row['hazardous'];
            $totals['non_hazardous'] += $row['non_hazardous'];
            $totals['complete_set'] += $row['complete_set'] ?? 0;
            $totals['total_cents'] += $row['total_cents'];
        }

        $totals['average_per_beneficiary_cents'] = $totals['beneficiaries'] > 0
            ? (int) round($totals['total_cents'] / $totals['beneficiaries'])
            : 0;

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTotals(): array
    {
        return [
            'beneficiaries' => 0,
            'female' => 0,
            'female_amount_cents' => 0,
            'person_days' => 0,
            'wages_cents' => 0,
            'insured' => 0,
            'insurance_cents' => 0,
            'ppe_cents' => 0,
            'ppe_hazardous_cents' => 0,
            'ppe_non_hazardous_cents' => 0,
            'hazardous' => 0,
            'non_hazardous' => 0,
            'complete_set' => 0,
            'total_cents' => 0,
            'average_per_beneficiary_cents' => 0,
        ];
    }

    /**
     * @return array{wages: array{computed_cents:int,stored_cents:int,difference_cents:int}, insurance: array{computed_cents:int,stored_cents:int,difference_cents:int}, ppe: array{computed_cents:int,stored_cents:int,difference_cents:int}, total: array{computed_cents:int,stored_cents:int,difference_cents:int}}
     */
    private function reconciliation(Project $project, int $wagesCents, int $insuranceCents, int $ppeCents): array
    {
        $storedWagesCents = $this->toCents($project->wages_total);
        $storedInsuranceCents = $this->toCents($project->insurance_total);
        $storedPpeCents = $this->toCents($project->ppe_total);
        $storedTotalCents = $this->toCents($project->total_project_cost);

        $totalCents = $wagesCents + $insuranceCents + $ppeCents;

        return [
            'wages' => [
                'computed_cents' => $wagesCents,
                'stored_cents' => $storedWagesCents,
                'difference_cents' => $wagesCents - $storedWagesCents,
            ],
            'insurance' => [
                'computed_cents' => $insuranceCents,
                'stored_cents' => $storedInsuranceCents,
                'difference_cents' => $insuranceCents - $storedInsuranceCents,
            ],
            'ppe' => [
                'computed_cents' => $ppeCents,
                'stored_cents' => $storedPpeCents,
                'difference_cents' => $ppeCents - $storedPpeCents,
            ],
            'total' => [
                'computed_cents' => $totalCents,
                'stored_cents' => $storedTotalCents,
                'difference_cents' => $totalCents - $storedTotalCents,
            ],
        ];
    }

    private function addressesOutOfDate(Project $project, Collection $addresses): bool
    {
        if ($addresses->isEmpty()) {
            return false;
        }

        $allocatedTotal = (int) $addresses->sum('beneficiaries_total');
        $allocatedFemale = (int) $addresses->sum('beneficiaries_female');

        return $allocatedTotal !== (int) $project->beneficiaries_total
            || $allocatedFemale !== (int) $project->beneficiaries_female;
    }

    private function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Largest-remainder apportionment: distributes an integer $total across
     * the keys of $weights in proportion to their weight, using only
     * integer arithmetic. Every key receives floor(total * weight / sum),
     * then the leftover units go one-by-one to the keys with the largest
     * remainder, ties broken by larger weight and then by original order.
     * Falls back to equal weights when every weight is 0.
     *
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    private function apportion(int $total, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        if ($total <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            $weights = array_fill_keys(array_keys($weights), 1);
            $weightSum = count($weights);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $product = $total * $weight;
            $shares[$key] = intdiv($product, $weightSum);
            $remainders[$key] = $product % $weightSum;
        }

        $leftover = $total - array_sum($shares);

        $order = [];
        $index = 0;

        foreach ($weights as $key => $weight) {
            $order[] = [
                'key' => $key,
                'remainder' => $remainders[$key],
                'weight' => $weight,
                'index' => $index++,
            ];
        }

        usort($order, function (array $a, array $b): int {
            return $b['remainder'] <=> $a['remainder']
                ?: $b['weight'] <=> $a['weight']
                ?: $a['index'] <=> $b['index'];
        });

        for ($i = 0; $i < $leftover; $i++) {
            $shares[$order[$i]['key']]++;
        }

        return $shares;
    }
}
