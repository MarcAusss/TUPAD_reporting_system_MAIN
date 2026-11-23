<?php

namespace App\Http\Controllers;

use App\Models\GpaiCashAdvance;
use App\Models\GpaiEnrollment;
use App\Models\Project;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Gsis\GpaiEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * GSIS Enrollment Officer workspace: Dashboard, Yearly Status, CA (Balance
 * Calculation), and Yearly Summary of GPAI enrollment of TUPAD projects.
 */
class GsisEnrollmentController extends Controller
{
    public function __construct(
        private readonly GpaiEnrollmentService $gpai,
    ) {}

    public function dashboard(Request $request): View
    {
        $year = $this->fiscalYear($request);
        $enrollments = $this->enrollmentsFor($year);
        $eligible = $this->gpai->eligibleProjects();
        $cashAdvances = $this->gpai->cashAdvances($year);

        return view('gsis.dashboard', [
            'fiscalYear' => $year,
            'fiscalYears' => $this->fiscalYears(),
            'eligible' => $eligible,
            'eligiblePremium' => $eligible->sum(fn ($project): float => $this->gpai->premiumFor($project)),
            'enrollments' => $enrollments,
            'stageCounts' => $enrollments->countBy(fn (GpaiEnrollment $enrollment): string => $enrollment->stage()),
            'enrolledBeneficiaries' => $enrollments->sum(fn (GpaiEnrollment $enrollment): int => $enrollment->beneficiaries()),
            'enrolledTotalCents' => $enrollments->sum(fn (GpaiEnrollment $enrollment): int => $enrollment->totalCents()),
            'currentCashAdvance' => $cashAdvances->last(),
        ]);
    }

    public function status(Request $request): View
    {
        return view('gsis.status', $this->statusViewData($request, $this->enrollmentsFor($this->fiscalYear($request))));
    }

    /**
     * Shared Yearly Status data. The table is split in two:
     *  - "billing": every transmittal (billing, SOA, routing, and its status);
     *  - "ops": only Signed/Completed transmittals (on-call payment slip, OR & Policy).
     * Both can be narrowed to Voucher or Cash Advance payments.
     *
     * @param  Collection<int, GpaiEnrollment>  $enrollments
     * @return array<string, mixed>
     */
    private function statusViewData(Request $request, Collection $enrollments, array $extra = []): array
    {
        $stage = (string) $request->query('stage');
        $validStage = array_key_exists($stage, GpaiEnrollment::STAGES);
        $mode = (string) $request->query('mode');
        $mode = in_array($mode, [GpaiEnrollment::MODE_VOUCHER, GpaiEnrollment::MODE_CASH_ADVANCE], true) ? $mode : null;
        $table = $request->query('table') === 'ops' ? 'ops' : 'billing';

        $byMode = $enrollments
            ->when($mode !== null, fn (Collection $rows) => $rows->where('payment_mode', $mode))
            ->values();
        $byStage = $byMode
            ->when($validStage, fn (Collection $rows) => $rows
                ->filter(fn (GpaiEnrollment $enrollment): bool => $enrollment->stage() === $stage))
            ->values();
        $signed = $byStage->filter(fn (GpaiEnrollment $enrollment): bool => $enrollment->isBillingSigned())->values();

        return [
            'fiscalYear' => $this->fiscalYear($request),
            'fiscalYears' => $this->fiscalYears(),
            'stage' => $validStage ? $stage : null,
            'mode' => $mode,
            'table' => $table,
            'enrollments' => $table === 'ops' ? $signed : $byStage,
            'billingCount' => $byStage->count(),
            'opsCount' => $signed->count(),
            'allEnrollments' => $byMode,
            ...$extra,
        ];
    }

    /**
     * Read-only enrollment progress for Admin, Focal (including TUPAD
     * Coordinators with Focal access), and TUPAD Coordinators. A TUPAD
     * Coordinator only sees the transmittals (and projects) of their own
     * province. Viewing only: no GPAI notifications go to Focal or Admin.
     */
    public function coordinatorStatus(Request $request, ProvinceAccessService $provinceAccess): View
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isTc() || $user->isFocal(), 403);

        $enrollments = $this->enrollmentsFor($this->fiscalYear($request));
        $visibleProjectIds = $this->visibleProjectIds($user, $provinceAccess, $enrollments);

        if ($visibleProjectIds !== null) {
            $enrollments = $enrollments
                ->filter(fn (GpaiEnrollment $enrollment): bool => $enrollment->items
                    ->contains(fn ($item): bool => in_array($item->project_id, $visibleProjectIds, true)))
                ->values();
        }

        return view('gsis.status', $this->statusViewData($request, $enrollments, [
            'readOnly' => true,
            'statusRoute' => 'gpai-status.index',
            'visibleProjectIds' => $visibleProjectIds,
        ]));
    }

    /**
     * Inline update from the Yearly Status tables: a status dropdown or a
     * remarks / notes cell. Only the submitted fields change.
     */
    public function updateStatus(Request $request, GpaiEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'billing_status' => ['sometimes', 'nullable', Rule::in(array_keys(GpaiEnrollment::BILLING_STATUSES))],
            'billing_received_via' => ['sometimes', 'nullable', Rule::in(array_keys(GpaiEnrollment::RECEIVED_VIA))],
            'ops_status' => ['sometimes', 'nullable', Rule::in(array_keys(GpaiEnrollment::OPS_STATUSES))],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'ops_remarks' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'ops_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'ops_document_url' => ['sometimes', 'nullable', 'url', 'max:2048', GpaiEnrollment::googleDriveLinkRule()],
        ]);

        abort_if($validated === [], 422);

        $this->saveProgress($request, $enrollment, $validated);

        return redirect()->back(fallback: route('gsis.status', ['fiscal_year' => $enrollment->fiscal_year]))
            ->with('success', $enrollment->gpai_number.' updated.');
    }

    /**
     * Project ids a province-scoped user (TUPAD Coordinator) may see among
     * the given transmittals; null when the user sees every project.
     *
     * @param  Collection<int, GpaiEnrollment>  $enrollments
     * @return array<int, int>|null
     */
    private function visibleProjectIds($user, ProvinceAccessService $provinceAccess, Collection $enrollments): ?array
    {
        if (! $provinceAccess->isProvinceScoped($user)) {
            return null;
        }

        return $provinceAccess
            ->scopeProjects(Project::query(), $user)
            ->whereIn('id', $enrollments->flatMap->items->pluck('project_id'))
            ->pluck('id')
            ->all();
    }

    /** Save fields on a transmittal and keep the projects' insurance records in step. */
    private function saveProgress(Request $request, GpaiEnrollment $enrollment, array $values): void
    {
        DB::transaction(function () use ($request, $enrollment, $values): void {
            $enrollment->fill(collect($values)
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all());

            // On-call Payment Slip status "Complete" means the OR and Policy are in.
            if ($enrollment->ops_status === 'complete' && $enrollment->or_policy_received_on === null) {
                $enrollment->or_policy_received_on = now()->toDateString();
            }

            // Distributed to PCs: the moment the system notifies the TUPAD
            // Coordinators (on completion). Cleared if it is no longer complete.
            if ($enrollment->isComplete()) {
                $enrollment->distributed_on ??= now();
            } else {
                $enrollment->distributed_on = null;
            }

            $enrollment->updated_by = $request->user()->id;
            $enrollment->save();

            $this->gpai->syncProjectInsurance($enrollment, $request->user());
        });
    }

    public function create(Request $request): View
    {
        $year = (int) now()->year;

        return view('gsis.enrollments.create', [
            'projects' => $this->gpai->eligibleProjects(),
            'cashAdvances' => $this->gpai->cashAdvances($year),
            'nextNumbers' => [
                GpaiEnrollment::MODE_VOUCHER => GpaiEnrollment::formatNumber(GpaiEnrollment::MODE_VOUCHER, $year, $this->gpai->nextSequence(GpaiEnrollment::MODE_VOUCHER, $year)),
                GpaiEnrollment::MODE_CASH_ADVANCE => GpaiEnrollment::formatNumber(GpaiEnrollment::MODE_CASH_ADVANCE, $year, $this->gpai->nextSequence(GpaiEnrollment::MODE_CASH_ADVANCE, $year)),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'distinct', 'exists:projects,id'],
            'payment_mode' => ['required', Rule::in([GpaiEnrollment::MODE_VOUCHER, GpaiEnrollment::MODE_CASH_ADVANCE])],
            'transmittal_date' => ['required', 'date'],
            'coverage_start' => ['nullable', 'date', 'after_or_equal:transmittal_date'],
            'doc_stamp' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'gpai_cash_advance_id' => [
                Rule::requiredIf($request->input('payment_mode') === GpaiEnrollment::MODE_CASH_ADVANCE),
                'nullable',
                'integer',
                'exists:gpai_cash_advances,id',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'project_ids.required' => 'Select at least one project to enroll.',
            'gpai_cash_advance_id.required' => 'Select the Cash Advance that pays this enrollment.',
        ]);

        $enrollment = $this->gpai->enroll(
            $request->user(),
            array_map('intval', $validated['project_ids']),
            $validated,
        );

        return redirect()
            ->route('gsis.enrollments.edit', $enrollment)
            ->with('success', sprintf(
                '%s created for %d %s. The TUPAD Coordinators were notified.',
                $enrollment->gpai_number,
                $enrollment->items->count(),
                str('project')->plural($enrollment->items->count()),
            ));
    }

    public function edit(GpaiEnrollment $enrollment): View
    {
        $enrollment->load(['items.project', 'cashAdvance', 'creator']);

        return view('gsis.enrollments.edit', [
            'enrollment' => $enrollment,
        ]);
    }

    public function update(Request $request, GpaiEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'billing_emailed_at' => ['nullable', 'date'],
            'soa_received_at' => ['nullable', 'date'],
            'routing_released_on' => ['nullable', 'date'],
            'payment_slip_on' => ['nullable', 'date'],
            'payment_slip_validated_at' => ['nullable', 'date'],
            'or_policy_requested_at' => ['nullable', 'date'],
            'or_policy_received_on' => ['nullable', 'date'],
            'or_number' => ['nullable', 'string', 'max:150'],
            'policy_id' => ['nullable', 'string', 'max:150'],
            'doc_stamp' => ['required', 'numeric', 'min:0', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'billing_status' => ['nullable', Rule::in(array_keys(GpaiEnrollment::BILLING_STATUSES))],
            'billing_received_via' => ['nullable', Rule::in(array_keys(GpaiEnrollment::RECEIVED_VIA))],
            'ops_status' => ['nullable', Rule::in(array_keys(GpaiEnrollment::OPS_STATUSES))],
            'ops_remarks' => ['nullable', 'string', 'max:3000'],
            'ops_notes' => ['nullable', 'string', 'max:3000'],
            'ops_document_url' => ['nullable', 'url', 'max:2048', GpaiEnrollment::googleDriveLinkRule()],
        ]);

        $wasComplete = $enrollment->isComplete();
        $this->saveProgress($request, $enrollment, $validated);

        $message = ! $wasComplete && $enrollment->isComplete()
            ? $enrollment->gpai_number.' is complete. The TUPAD Coordinators were notified that the OR and Policy were received.'
            : $enrollment->gpai_number.' updated.';

        // Land on the next step to record, so the officer can keep going.
        $next = $enrollment->fresh()->nextStep();

        return redirect()
            ->to(route('gsis.enrollments.edit', $enrollment).($next ? '#step-'.$next : ''))
            ->with('success', $message);
    }

    public function transmittal(GpaiEnrollment $enrollment): View
    {
        $enrollment->load(['items.project', 'creator']);

        return view('gsis.enrollments.transmittal', [
            'enrollment' => $enrollment,
        ]);
    }

    public function cashAdvances(Request $request): View
    {
        $year = $this->fiscalYear($request);

        return view('gsis.cash-advances', [
            'fiscalYear' => $year,
            'fiscalYears' => $this->fiscalYears(),
            'cashAdvances' => $this->gpai->cashAdvances($year),
            'voucherEnrollments' => $this->enrollmentsFor($year)
                ->where('payment_mode', GpaiEnrollment::MODE_VOUCHER)
                ->values(),
        ]);
    }

    public function storeCashAdvance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'reference' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $cashAdvance = GpaiCashAdvance::query()->create([
            ...$validated,
            'fiscal_year' => (int) date('Y', strtotime($validated['cash_advance_date'])),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('gsis.cash-advances', ['fiscal_year' => $cashAdvance->fiscal_year])
            ->with('success', $cashAdvance->label().' of ₱'.number_format((float) $cashAdvance->amount, 2).' added.');
    }

    public function summary(Request $request): View
    {
        $year = $this->fiscalYear($request);
        $enrollments = $this->enrollmentsFor($year)
            ->sortBy(fn (GpaiEnrollment $enrollment): string => $enrollment->transmittal_date->format('Ymd').sprintf('%06d', $enrollment->id))
            ->values();

        return view($request->boolean('print') ? 'gsis.summary-print' : 'gsis.summary', [
            'fiscalYear' => $year,
            'fiscalYears' => $this->fiscalYears(),
            'semesters' => [
                '1st Semester' => $enrollments->filter(fn (GpaiEnrollment $enrollment): bool => $enrollment->transmittal_date->month <= 6)->values(),
                '2nd Semester' => $enrollments->filter(fn (GpaiEnrollment $enrollment): bool => $enrollment->transmittal_date->month > 6)->values(),
            ],
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Enrolled projects report: one row per enrolled project with its GPAI
     * No., current Project Code (read live, so a code assigned after
     * enrollment shows automatically), beneficiaries, and amount
     * (beneficiaries x ₱50), plus each transmittal's documentary stamp, split
     * into Voucher, Cash Advance, and Overall.
     * Filter by year, semester, quarter, or month of the transmittal date.
     */
    public function report(Request $request): View
    {
        $year = $this->fiscalYear($request);
        $period = in_array($request->query('period'), ['semester', 'quarter', 'month'], true) ? $request->query('period') : 'annual';
        $limits = ['semester' => 2, 'quarter' => 4, 'month' => 12];
        // "all" (the default) = the whole year, grouped by month / quarter / semester.
        $part = $period === 'annual' || in_array($request->query('part'), [null, '', 'all'], true)
            ? null
            : max(1, min($limits[$period], (int) $request->query('part')));

        [$from, $to] = match (true) {
            $part === null => [Carbon::create($year, 1, 1), Carbon::create($year, 12, 31)],
            $period === 'semester' => [Carbon::create($year, ($part - 1) * 6 + 1, 1), Carbon::create($year, $part * 6, 1)->endOfMonth()],
            $period === 'quarter' => [Carbon::create($year, ($part - 1) * 3 + 1, 1), Carbon::create($year, $part * 3, 1)->endOfMonth()],
            default => [Carbon::create($year, $part, 1), Carbon::create($year, $part, 1)->endOfMonth()],
        };

        // Group label of a transmittal date for "All" (e.g. "Q2 2026", "March 2026").
        $groupOf = fn (Carbon $date): array => match ($period) {
            'semester' => [(int) ceil($date->month / 6), ($date->month <= 6 ? '1st' : '2nd').' Semester '.$year],
            'quarter' => [(int) ceil($date->month / 3), 'Q'.(int) ceil($date->month / 3).' '.$year],
            'month' => [$date->month, $date->format('F Y')],
            default => [0, ''],
        };

        $enrollments = GpaiEnrollment::query()
            ->with(['items.project.approval', 'cashAdvance'])
            ->whereBetween('transmittal_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transmittal_date')
            ->orderBy('id')
            ->get();

        // The documentary stamp is charged once per transmittal (transaction), so it
        // is carried on the transmittal's first project row and added to the totals.
        $rows = $enrollments->flatMap(fn (GpaiEnrollment $enrollment) => $enrollment->items->values()->map(function ($item, int $index) use ($enrollment, $groupOf): array {
            $amount = (int) $item->beneficiaries * GpaiEnrollment::REPORT_RATE * 100;
            $docStamp = $index === 0 ? $enrollment->docStampCents() : 0;

            return [
                'enrollment' => $enrollment,
                'item' => $item,
                'project' => $item->project,
                'project_code' => $item->project?->approval?->project_code,
                'beneficiaries' => (int) $item->beneficiaries,
                'amount_cents' => $amount,
                'first_of_transmittal' => $index === 0,
                'transmittal_rows' => $enrollment->items->count(),
                'doc_stamp_cents' => $docStamp,
                'total_cents' => $amount + $docStamp,
                'group' => $groupOf($enrollment->transmittal_date),
            ];
        }));

        $totals = fn (Collection $group): array => [
            'projects' => $group->count(),
            'transmittals' => $group->where('first_of_transmittal', true)->count(),
            'beneficiaries' => (int) $group->sum('beneficiaries'),
            'amount_cents' => (int) $group->sum('amount_cents'),
            'doc_stamp_cents' => (int) $group->sum('doc_stamp_cents'),
            'total_cents' => (int) $group->sum('total_cents'),
        ];

        $sections = collect([
            GpaiEnrollment::MODE_VOUCHER => 'Voucher',
            GpaiEnrollment::MODE_CASH_ADVANCE => 'Cash Advance',
        ])->map(function (string $label, string $mode) use ($rows, $totals): array {
            $group = $rows->filter(fn (array $row): bool => $row['enrollment']->payment_mode === $mode)->values();

            return [
                'label' => $label,
                'rows' => $group,
                'totals' => $totals($group),
                // Month / quarter / semester groups with sub-totals ("All" only).
                'groups' => $group->groupBy(fn (array $row): int => $row['group'][0])
                    ->sortKeys()
                    ->map(fn (Collection $rows): array => ['label' => $rows->first()['group'][1], 'rows' => $rows->values(), 'totals' => $totals($rows)])
                    ->values(),
            ];
        });

        return view('gsis.report', [
            'fiscalYear' => $year,
            'fiscalYears' => $this->fiscalYears(),
            'period' => $period,
            'part' => $part,
            'grouped' => $period !== 'annual' && $part === null,
            'periodLabel' => match (true) {
                $part === null && $period !== 'annual' => 'All '.['semester' => 'semesters', 'quarter' => 'quarters', 'month' => 'months'][$period].' of '.$year,
                $period === 'semester' => ($part === 1 ? '1st' : '2nd').' Semester '.$year,
                $period === 'quarter' => 'Q'.$part.' '.$year,
                $period === 'month' => $from->format('F Y'),
                default => 'January – December '.$year,
            },
            'sections' => $sections,
            'overall' => $totals($rows),
            'enrollments' => $enrollments,
        ]);
    }

    /** @return Collection<int, GpaiEnrollment> */
    private function enrollmentsFor(int $year): Collection
    {
        return GpaiEnrollment::query()
            ->with(['items.project', 'cashAdvance'])
            ->where('fiscal_year', $year)
            ->orderBy('transmittal_date')
            ->orderBy('id')
            ->get();
    }

    private function fiscalYear(Request $request): int
    {
        $year = (int) $request->query('fiscal_year', now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }

    /** @return Collection<int, int> */
    private function fiscalYears(): Collection
    {
        return GpaiEnrollment::query()->distinct()->pluck('fiscal_year')
            ->merge(GpaiCashAdvance::query()->distinct()->pluck('fiscal_year'))
            ->push((int) now()->year)
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();
    }
}
