<?php

namespace App\Services\Gsis;

use App\Models\GpaiCashAdvance;
use App\Models\GpaiEnrollment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top-bar search for the GSIS Enrollment Officer. Only returns records the
 * officer can act on:
 *  - projects already enrolled (opens their GPAI transmittal),
 *  - projects awaiting enrollment (opens New Enrollment with the project ticked),
 *  - GPAI transmittals, and cash advances.
 */
class GsisSearchService
{
    public function __construct(
        private readonly GpaiEnrollmentService $gpai,
    ) {}

    /**
     * @return array{projects: array<int, array>, enrollments: array<int, array>, cash_advances: array<int, array>}
     */
    public function search(string $query, int $limit = 8): array
    {
        if (mb_strlen($query) < 2) {
            return ['projects' => [], 'enrollments' => [], 'cash_advances' => []];
        }

        $like = '%'.addcslashes($query, '%_\\').'%';

        return [
            'projects' => $this->projects($like, $limit),
            'enrollments' => $this->enrollments($like, max(4, intdiv($limit, 2))),
            'cash_advances' => $this->cashAdvances($like, 4),
        ];
    }

    private function projects(string $like, int $limit): array
    {
        $eligibleIds = $this->gpai->eligibleProjectsQuery()->select('projects.id');

        return Project::query()
            ->where(fn (Builder $matches) => $this->matchProject($matches, $like))
            ->where(fn (Builder $actionable) => $actionable
                ->whereHas('gpaiEnrollmentItem')
                ->orWhereIn('projects.id', $eligibleIds))
            ->with(['approval', 'allocation.adl', 'gpaiEnrollmentItem.enrollment'])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Project $project): array {
                $enrollment = $project->gpaiEnrollmentItem?->enrollment;

                return [
                    'title' => $project->project_title,
                    'code' => $project->approval?->project_code,
                    'adl' => $project->allocation?->adl?->adl_number,
                    'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
                    'status' => $enrollment ? 'Enrolled · '.$enrollment->gpai_number : 'Awaiting enrollment',
                    'status_classes' => $enrollment
                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                        : 'bg-amber-50 text-amber-800 ring-amber-200',
                    'days' => null,
                    'url' => $enrollment
                        ? route('gsis.enrollments.edit', $enrollment)
                        : route('gsis.enrollments.create', ['project' => $project->id]),
                ];
            })
            ->all();
    }

    private function enrollments(string $like, int $limit): array
    {
        return GpaiEnrollment::query()
            ->where(fn (Builder $query) => $query
                ->where('gpai_number', 'like', $like)
                ->orWhereHas('items', fn (Builder $item) => $item
                    ->where('particulars', 'like', $like)
                    ->orWhere('adl_number', 'like', $like)
                    ->orWhereHas('project', fn (Builder $project) => $this->matchProject($project, $like))))
            ->withCount('items')
            ->latest('transmittal_date')
            ->limit($limit)
            ->get()
            ->map(fn (GpaiEnrollment $enrollment): array => [
                'number' => $enrollment->gpai_number,
                'meta' => collect([
                    GpaiEnrollment::modeLabel($enrollment->payment_mode),
                    'FY'.$enrollment->fiscal_year,
                    $enrollment->items_count.' '.($enrollment->items_count === 1 ? 'project' : 'projects'),
                    $enrollment->transmittal_date?->format('M j, Y'),
                ])->filter()->implode(' · '),
                'stage' => $enrollment->stageLabel(),
                'url' => route('gsis.enrollments.edit', $enrollment),
            ])
            ->all();
    }

    private function cashAdvances(string $like, int $limit): array
    {
        return GpaiCashAdvance::query()
            ->where(fn (Builder $query) => $query
                ->where('reference', 'like', $like)
                ->orWhere('remarks', 'like', $like))
            ->latest('cash_advance_date')
            ->limit($limit)
            ->get()
            ->map(fn (GpaiCashAdvance $advance): array => [
                'label' => $advance->label(),
                'meta' => collect(['₱'.number_format($advance->amountCents() / 100, 2), $advance->reference])->filter()->implode(' · '),
                'url' => route('gsis.cash-advances', ['fiscal_year' => $advance->fiscal_year]),
            ])
            ->all();
    }

    private function matchProject(Builder $query, string $like): void
    {
        $query
            ->where('project_title', 'like', $like)
            ->orWhere('province', 'like', $like)
            ->orWhere('municipality', 'like', $like)
            ->orWhere('barangay', 'like', $like)
            ->orWhereHas('approval', fn (Builder $approval) => $approval->where('project_code', 'like', $like))
            ->orWhereHas('allocation.adl', fn (Builder $adl) => $adl->where('adl_number', 'like', $like));
    }
}
