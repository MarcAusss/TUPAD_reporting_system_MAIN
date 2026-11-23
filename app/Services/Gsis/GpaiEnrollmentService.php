<?php

namespace App\Services\Gsis;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\GpaiCashAdvance;
use App\Models\GpaiEnrollment;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectStatusEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GSIS GPAI enrollment of TUPAD projects.
 *
 * The TUPAD Coordinator budgets the insurance in Project Create (insurance
 * beneficiaries x rate). The GSIS Enrollment Officer then enrolls the
 * projects in bulk, from TSSD Evaluation onward (before approval): one transmittal (GPAI V26-0001 / GPAI CA26-001) per
 * batch. Enrolling also records each project's "GSIS Enrollment (Insurance)"
 * implementation requirement, so the TC no longer encodes it by hand and the
 * project status moves on as usual.
 */
final class GpaiEnrollmentService
{
    public function __construct(
        private readonly ProjectStatusEngine $statusEngine,
    ) {}

    /**
     * Statuses in which a project can be enrolled: from TSSD Evaluation (so
     * the GSIS Enrollment Officer can enroll ahead of approval) up to the
     * start of the work. Through ACP also passes payment and check release
     * before implementation.
     */
    private const DIRECT_ENROLLABLE_STATUSES = [
        ProjectStatus::TSSD_EVALUATION,
        ProjectStatus::FOR_COMPLIANCE,
        ProjectStatus::FOR_APPROVAL,
        ProjectStatus::APPROVED,
        ProjectStatus::FOR_IMPLEMENTATION,
    ];

    private const ACP_ENROLLABLE_STATUSES = [
        ProjectStatus::TSSD_EVALUATION,
        ProjectStatus::FOR_COMPLIANCE,
        ProjectStatus::FOR_APPROVAL,
        ProjectStatus::APPROVED,
        ProjectStatus::FOR_PAYMENT,
        ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
        ProjectStatus::FOR_IMPLEMENTATION,
    ];

    /**
     * Projects ready for GPAI enrollment: insurance is budgeted, the project
     * has not started work yet (it shows from TSSD Evaluation, before
     * approval), and it is not enrolled yet.
     */
    public function eligibleProjectsQuery(): Builder
    {
        $values = fn (array $statuses): array => array_map(fn (ProjectStatus $status): string => $status->value, $statuses);

        return Project::query()
            ->where('insurance_total', '>', 0)
            ->where('insurance_beneficiaries', '>', 0)
            ->whereDoesntHave('gpaiEnrollmentItem')
            ->whereDoesntHave('insuranceEnrollment')
            ->where(function (Builder $stage) use ($values): void {
                $stage
                    ->where(fn (Builder $direct) => $direct
                        ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                        ->whereIn('status', $values(self::DIRECT_ENROLLABLE_STATUSES)))
                    ->orWhere(fn (Builder $acp) => $acp
                        ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
                        ->whereIn('status', $values(self::ACP_ENROLLABLE_STATUSES)));
            });
    }

    /** "Request GPAI Enrollment": at most this many presses per project... */
    public const REQUEST_LIMIT = 3;

    /** ...within any window of this many hours. */
    public const REQUEST_WINDOW_HOURS = 3;

    /**
     * Eligible projects, the ones a TUPAD Coordinator requested first (most
     * recent request on top), then by location.
     *
     * @return Collection<int, Project>
     */
    public function eligibleProjects(): Collection
    {
        return $this->eligibleProjectsQuery()
            ->with(['allocation.adl', 'latestGpaiEnrollmentRequest.requester'])
            ->withCount('gpaiEnrollmentRequests')
            ->orderBy('province')
            ->orderBy('municipality')
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (Project $project): int => $project->latestGpaiEnrollmentRequest?->created_at?->getTimestamp() ?? 0)
            ->values();
    }

    public function isEligible(Project $project): bool
    {
        return $this->eligibleProjectsQuery()->whereKey($project->id)->exists();
    }

    /**
     * Request allowance of a project: presses used in the current 3-hour
     * window, presses left, when the next one frees up, and the last request.
     *
     * @return array{used: int, remaining: int, resets_at: ?\Illuminate\Support\Carbon, total: int, last: ?\App\Models\GpaiEnrollmentRequest}
     */
    public function requestAllowance(Project $project): array
    {
        $recent = $project->gpaiEnrollmentRequests()
            ->where('created_at', '>', now()->subHours(self::REQUEST_WINDOW_HOURS))
            ->reorder('created_at')
            ->get();
        $used = $recent->count();

        return [
            'used' => $used,
            'remaining' => max(0, self::REQUEST_LIMIT - $used),
            // The oldest press in the window frees up first.
            'resets_at' => $used > 0 ? $recent->first()->created_at->copy()->addHours(self::REQUEST_WINDOW_HOURS) : null,
            'total' => $project->gpaiEnrollmentRequests()->count(),
            'last' => $project->gpaiEnrollmentRequests()->with('requester')->first(),
        ];
    }

    public function premiumFor(Project $project): float
    {
        return round((float) $project->insurance_total, 2);
    }

    /** "DOLE TUPAD Program for 51 beneficiaries in Oas, Albay". */
    public function particularsFor(Project $project): string
    {
        $place = collect([$project->municipality, $project->province])->filter()->implode(', ');

        return sprintf(
            'DOLE TUPAD Program for %s beneficiaries in %s',
            number_format((int) $project->insurance_beneficiaries),
            $place !== '' ? $place : 'Region V',
        );
    }

    public function nextSequence(string $mode, int $fiscalYear): int
    {
        return (int) GpaiEnrollment::query()
            ->where('payment_mode', $mode)
            ->where('fiscal_year', $fiscalYear)
            ->max('sequence') + 1;
    }

    /** Cash advances of the year, newest first, with their remaining balance. */
    public function cashAdvances(int $fiscalYear): Collection
    {
        return GpaiCashAdvance::query()
            ->with('enrollments.items')
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('cash_advance_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, int>  $projectIds
     * @param  array{payment_mode: string, transmittal_date: string, coverage_start?: ?string, doc_stamp?: mixed, gpai_cash_advance_id?: mixed, remarks?: ?string}  $data
     */
    public function enroll(User $officer, array $projectIds, array $data): GpaiEnrollment
    {
        return DB::transaction(function () use ($officer, $projectIds, $data): GpaiEnrollment {
            $projects = $this->eligibleProjectsQuery()
                ->with('allocation.adl')
                ->whereKey($projectIds)
                ->lockForUpdate()
                ->get();

            if ($projects->count() !== count(array_unique($projectIds))) {
                throw ValidationException::withMessages([
                    'project_ids' => 'One or more selected projects are no longer available for GPAI enrollment. Refresh the list and try again.',
                ]);
            }

            $mode = $data['payment_mode'];
            $transmittal = CarbonImmutable::parse($data['transmittal_date']);
            $fiscalYear = (int) $transmittal->year;
            $coverageStart = filled($data['coverage_start'] ?? null)
                ? CarbonImmutable::parse($data['coverage_start'])
                : $transmittal->addDay();
            $premium = round($projects->sum(fn (Project $project): float => $this->premiumFor($project)), 2);
            $docStamp = filled($data['doc_stamp'] ?? null)
                ? round((float) $data['doc_stamp'], 2)
                : GpaiEnrollment::defaultDocStamp($premium);

            $cashAdvance = null;

            if ($mode === GpaiEnrollment::MODE_CASH_ADVANCE) {
                $cashAdvance = GpaiCashAdvance::query()
                    ->with('enrollments.items')
                    ->lockForUpdate()
                    ->find($data['gpai_cash_advance_id'] ?? null);

                if ($cashAdvance === null) {
                    throw ValidationException::withMessages([
                        'gpai_cash_advance_id' => 'Select the Cash Advance that pays this enrollment.',
                    ]);
                }

                $totalCents = (int) round(($premium + $docStamp) * 100);

                if ($totalCents > $cashAdvance->balanceCents()) {
                    throw ValidationException::withMessages([
                        'gpai_cash_advance_id' => sprintf(
                            'The enrollment total of %s exceeds the remaining balance of %s (%s).',
                            '₱'.number_format($totalCents / 100, 2),
                            $cashAdvance->label(),
                            '₱'.number_format($cashAdvance->balanceCents() / 100, 2),
                        ),
                    ]);
                }
            }

            $sequence = $this->nextSequence($mode, $fiscalYear);

            $enrollment = GpaiEnrollment::query()->create([
                'gpai_number' => GpaiEnrollment::formatNumber($mode, $fiscalYear, $sequence),
                'payment_mode' => $mode,
                'fiscal_year' => $fiscalYear,
                'sequence' => $sequence,
                'transmittal_date' => $transmittal->toDateString(),
                'coverage_start' => $coverageStart->toDateString(),
                'coverage_end' => $coverageStart->addYear()->subDay()->toDateString(),
                'doc_stamp' => $docStamp,
                'gpai_cash_advance_id' => $cashAdvance?->id,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $officer->id,
                'updated_by' => $officer->id,
            ]);

            foreach ($projects->values() as $index => $project) {
                $enrollment->items()->create([
                    'project_id' => $project->id,
                    'adl_number' => $project->allocation?->adl?->adl_number,
                    'particulars' => $this->particularsFor($project),
                    'beneficiaries' => (int) $project->insurance_beneficiaries,
                    'premium' => $this->premiumFor($project),
                    'sort_order' => $index + 1,
                ]);
            }

            $enrollment->load('items.project');
            $this->syncProjectInsurance($enrollment, $officer);

            return $enrollment;
        });
    }

    /**
     * Writes each project's "GSIS Enrollment (Insurance)" record from the
     * transmittal (date, beneficiaries, amount, payment mode, OR / Policy),
     * then lets the status engine move the project on.
     */
    public function syncProjectInsurance(GpaiEnrollment $enrollment, User $actor): void
    {
        $enrollment->loadMissing('items.project');

        foreach ($enrollment->items as $item) {
            $project = $item->project;

            if ($project === null) {
                continue;
            }

            $project->insuranceEnrollment()->updateOrCreate(
                ['project_id' => $project->id],
                [
                    'date_enrolled' => $enrollment->transmittal_date->toDateString(),
                    'beneficiary_count' => $item->beneficiaries,
                    'amount' => $item->premium,
                    'payment_mode' => $enrollment->payment_mode,
                    'or_number' => $enrollment->or_number,
                    'policy_number' => $enrollment->policy_id,
                    'remarks' => 'Enrolled to GSIS GPAI under '.$enrollment->gpai_number.'.',
                    'recorded_by' => $actor->id,
                ],
            );

            $this->statusEngine->synchronize($project->fresh(), actorId: $actor->id);
        }
    }
}
