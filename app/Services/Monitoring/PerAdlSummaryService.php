<?php

namespace App\Services\Monitoring;

use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use Illuminate\Support\Collection;

class PerAdlSummaryService
{
    public function rowsForAdl(Adl $adl): Collection
    {
        $adl->loadMissing([
            'realignments',
            'allocations.projects.obligation',
            'allocations.projects.approval',
        ]);

        return $adl->allocations->values()->map(fn (AdlAllocation $allocation, int $index) => $this->row($adl, $allocation, $index === 0));
    }

    public function row(Adl $adl, AdlAllocation $allocation, bool $includeAdlRealignment = true): array
    {
        $projects = $allocation->projects;
        $grantAllocation = (float) ($allocation->grant_amount ?? $allocation->amount);
        $adminCost = (float) ($allocation->admin_cost_amount ?? 0);
        $totalAllocation = (float) ($allocation->total_amount ?? ($grantAllocation + $adminCost));

        $targetGrants = $grantAllocation;
        $divisor = max(1, (float) config('tupad.target_cost_per_beneficiary', 4356));
        $targetBeneficiaries = (int) floor($targetGrants / $divisor);

        $obligatedProjects = $projects->filter(fn (Project $project) => $project->obligation !== null);
        $obligatedGrants = (float) $obligatedProjects->sum(fn (Project $p) => (float) $p->obligation->amount);
        $wages = (float) $obligatedProjects->sum('wages_total');
        $ppe = (float) $obligatedProjects->sum('ppe_total');
        $insurance = (float) $obligatedProjects->sum('insurance_total');
        $beneficiaries = (int) $obligatedProjects->sum('beneficiaries_total');
        $female = (int) $obligatedProjects->sum('beneficiaries_female');

        $bucket = fn (array $statuses): float => (float) $projects
            ->filter(fn (Project $project) => in_array($project->status, $statuses, true))
            ->sum('total_project_cost');

        $forPayment = $bucket([ProjectStatus::FOR_PAYMENT]);
        $postDocs = $bucket([ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS]);
        $ongoingImplementation = $bucket([ProjectStatus::ONGOING_IMPLEMENTATION]);
        $forImplementation = $bucket([ProjectStatus::FOR_IMPLEMENTATION]);
        $approved = $bucket([ProjectStatus::APPROVED]);
        $forApproval = $bucket([ProjectStatus::FOR_APPROVAL]);
        $underEvaluation = $bucket([ProjectStatus::TSSD_EVALUATION, ProjectStatus::FOR_COMPLIANCE]);
        $onProcess = $forPayment + $postDocs + $ongoingImplementation + $forImplementation + $approved + $forApproval + $underEvaluation;

        $unutilized = $targetGrants - $obligatedGrants;
        $availableBalance = $unutilized - $onProcess;
        $projectCommitments = (float) $projects->sum('total_project_cost');
        $remaining = $totalAllocation - $projectCommitments;

        $realignmentAmount = $includeAdlRealignment ? (float) $adl->realignments->sum('amount') : 0.0;
        $latestMaf = $includeAdlRealignment ? $adl->realignments->sortByDesc('maf_date')->first() : null;

        return [
            'adl_id' => $adl->id,
            'adl_number' => $adl->adl_number,
            'fund_sponsor' => $allocation->fund_sponsor,
            'lce_partylist' => $allocation->local_chief_executive_partylist ?: $allocation->partner,
            'province' => $allocation->province ?: $projects->first()?->province,
            'district' => $allocation->district ?: $projects->first()?->district,
            'municipality' => $allocation->municipality ?: $projects->first()?->municipality ?: $allocation->location,
            'allocation_grants' => $grantAllocation,
            'allocation_admin_cost' => $adminCost,
            'allocation_total' => $totalAllocation,
            'realignment_grants' => $realignmentAmount,
            'maf' => $latestMaf ? trim(collect([$latestMaf->maf_date?->format('m/d/Y'), $latestMaf->maf_number])->filter()->implode(' / ')) : null,
            'target_grants' => $targetGrants,
            'target_beneficiaries' => $targetBeneficiaries,
            'obligated_grants' => $obligatedGrants,
            'utilization' => $targetGrants > 0 ? ($obligatedGrants / $targetGrants) * 100 : 0,
            'wages' => $wages,
            'ppe' => $ppe,
            'insurance' => $insurance,
            'beneficiaries' => $beneficiaries,
            'female' => $female,
            'unutilized' => $unutilized,
            'for_payment' => $forPayment,
            'post_docs' => $postDocs,
            'ongoing_implementation' => $ongoingImplementation,
            'for_implementation' => $forImplementation,
            'approved' => $approved,
            'for_approval' => $forApproval,
            'under_evaluation' => $underEvaluation,
            'available_balance' => $availableBalance,
            'remaining' => $remaining,
            'unused' => max(0, $remaining),
            'remarks' => $allocation->remarks,
        ];
    }

    public function allRows(): Collection
    {
        return Adl::query()
            ->with(['realignments', 'allocations.projects.obligation'])
            ->orderBy('adl_number')
            ->get()
            ->flatMap(fn (Adl $adl) => $this->rowsForAdl($adl));
    }
}
