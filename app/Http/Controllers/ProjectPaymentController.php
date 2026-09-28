<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectObligation;
use App\Services\Payments\ProjectPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectPaymentController extends Controller
{
    private const MONEY_RULE = 'regex:/^\d{1,13}(?:\.\d{1,2})?$/';

    /**
     * Fields that make a tranche row "filled". Obligation date and remarks
     * are excluded because the date is prefilled for every new row.
     */
    private const ROW_CONTENT_FIELDS = [
        'beneficiaries_total',
        'beneficiaries_female',
        'wages_amount',
        'insurance_amount',
        'ppe_amount',
        'payee',
    ];

    public function show(
        Project $project,
        ProjectPaymentService $paymentService
    ): View {
        $this->ensureDirectAdministration($project);

        if (
            ! in_array(
                $project->status,
                [ProjectStatus::FOR_PAYMENT, ProjectStatus::COMPLETED],
                true
            )
        ) {
            abort(403);
        }

        $project->load([
            'allocation.adl',
            'approval',
            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',
            'obligations' => fn ($query) =>
                $query->orderBy('tranche_number'),
            'obligations.recorder',
            'obligations.disbursements' => fn ($query) =>
                $query->orderBy('date_disbursed')->orderBy('id'),
            'obligations.disbursements.recorder',
            'obligationsCompleter',
            'payout.recorder',
        ]);

        $summary = $paymentService->summary($project);
        $nextTranche = ((int) $project->obligations
            ->max('tranche_number')) + 1;

        $obligatedSum = fn (string $field): int => $project->obligations->sum(
            fn (ProjectObligation $obligation): int =>
                $paymentService->amountToCents($obligation->{$field})
        );

        return view('payments.show', [
            'project' => $project,
            'summary' => $summary,
            'nextTranche' => $nextTranche,
            'canEditTranches' =>
                $project->status === ProjectStatus::FOR_PAYMENT
                && ! $summary['obligations_completed'],
            'remainingDefaults' => [
                'beneficiaries_total' => max(
                    0,
                    (int) $project->beneficiaries_total
                        - (int) $project->obligations->sum('beneficiaries_total')
                ),
                'beneficiaries_female' => max(
                    0,
                    (int) $project->beneficiaries_female
                        - (int) $project->obligations->sum('beneficiaries_female')
                ),
                'wages_amount' => $paymentService->centsToDecimal(max(
                    0,
                    $paymentService->amountToCents($project->wages_total) - $obligatedSum('wages_amount')
                )),
                'insurance_amount' => $paymentService->centsToDecimal(max(
                    0,
                    $paymentService->amountToCents($project->insurance_total) - $obligatedSum('insurance_amount')
                )),
                'ppe_amount' => $paymentService->centsToDecimal(max(
                    0,
                    $paymentService->amountToCents($project->ppe_total) - $obligatedSum('ppe_amount')
                )),
            ],
            'paymentService' => $paymentService,
        ]);
    }

    /**
     * Save any number of obligation tranches in one submit. With
     * intent=complete, the tranches are also locked as complete, which
     * requires their totals to equal the full project cost.
     */
    public function store(
        Request $request,
        Project $project,
        ProjectPaymentService $paymentService
    ): RedirectResponse {
        $this->ensureDirectAdministration($project);

        if ($project->status !== ProjectStatus::FOR_PAYMENT) {
            abort(
                403,
                'Obligations can only be recorded for projects with For Payment status.'
            );
        }

        $completing = $request->input('intent') === 'complete';

        $rows = collect($request->input('tranches', []))
            ->filter(fn ($row): bool => is_array($row) && collect(self::ROW_CONTENT_FIELDS)
                ->contains(fn (string $field): bool => trim((string) ($row[$field] ?? '')) !== ''));

        $request->merge(['tranches' => $rows->all()]);

        $validated = $request->validate([
            'intent' => ['required', 'in:save,complete'],
            'tranches' => [$completing ? 'nullable' : 'required', 'array'],
            'tranches.*.beneficiaries_total' => ['required', 'integer', 'min:1'],
            'tranches.*.beneficiaries_female' => ['required', 'integer', 'min:0', 'lte:tranches.*.beneficiaries_total'],
            'tranches.*.wages_amount' => ['required', self::MONEY_RULE],
            'tranches.*.insurance_amount' => ['required', self::MONEY_RULE],
            'tranches.*.ppe_amount' => ['required', self::MONEY_RULE],
            'tranches.*.obligation_date' => ['required', 'date'],
            'tranches.*.payee' => ['required', 'string', 'max:255'],
            'tranches.*.remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'tranches.required' => 'Enter at least one tranche before saving.',
            'tranches.*.beneficiaries_female.lte' =>
                'Female beneficiaries cannot exceed the tranche\'s total beneficiaries.',
            'tranches.*.*.regex' =>
                'Amounts must be valid positive values with no more than two decimal places.',
        ], [
            'tranches.*.beneficiaries_total' => 'number of beneficiaries',
            'tranches.*.beneficiaries_female' => 'female beneficiaries',
            'tranches.*.wages_amount' => 'wages',
            'tranches.*.insurance_amount' => 'insurance',
            'tranches.*.ppe_amount' => 'PPE',
            'tranches.*.obligation_date' => 'obligation date',
            'tranches.*.payee' => 'payee',
        ]);

        $savedCount = DB::transaction(function () use (
            $request,
            $project,
            $validated,
            $completing,
            $paymentService
        ): int {
            $lockedProject = Project::query()
                ->with(['allocation.adl'])
                ->lockForUpdate()
                ->findOrFail($project->id);

            if (
                $lockedProject->implementation_mode
                    !== ImplementationMode::DIRECT_ADMINISTRATION
                || $lockedProject->status !== ProjectStatus::FOR_PAYMENT
            ) {
                throw ValidationException::withMessages([
                    'tranches' =>
                        'This project is no longer available for payment processing.',
                ]);
            }

            if ($lockedProject->obligations_completed_at !== null) {
                throw ValidationException::withMessages([
                    'tranches' =>
                        'The obligation tranches for this project are already completed and locked.',
                ]);
            }

            $obligations = ProjectObligation::query()
                ->where('project_id', $lockedProject->id)
                ->orderBy('tranche_number')
                ->lockForUpdate()
                ->get();

            $obligatedCents = $obligations->sum(
                fn (ProjectObligation $obligation): int =>
                    $paymentService->obligationCents($obligation)
            );

            $payableCents = $paymentService->payableCents($lockedProject);
            $nextTranche = ((int) $obligations->max('tranche_number')) + 1;
            $rows = $validated['tranches'] ?? [];

            foreach ($rows as $key => $row) {
                $wagesCents = $paymentService->amountToCents($row['wages_amount']);
                $insuranceCents = $paymentService->amountToCents($row['insurance_amount']);
                $ppeCents = $paymentService->amountToCents($row['ppe_amount']);
                $totalCents = $wagesCents + $insuranceCents + $ppeCents;

                if ($totalCents <= 0) {
                    throw ValidationException::withMessages([
                        "tranches.{$key}.wages_amount" =>
                            'A tranche\'s total overall amount must be greater than zero.',
                    ]);
                }

                if ($obligatedCents + $totalCents > $payableCents) {
                    throw ValidationException::withMessages([
                        "tranches.{$key}.wages_amount" => sprintf(
                            'Tranche totals cannot exceed the Total Project Cost. Remaining amount to obligate: ₱%s.',
                            number_format(
                                max(0, $payableCents - $obligatedCents) / 100,
                                2
                            )
                        ),
                    ]);
                }

                $obligatedCents += $totalCents;

                $lockedProject->obligations()->create([
                    'tranche_number' => $nextTranche++,
                    'adl_number' =>
                        $lockedProject->allocation->adl->adl_number,
                    'fund_sponsor' =>
                        $lockedProject->fund_sponsor
                        ?: $lockedProject->allocation->fund_sponsor
                        ?: 'Not specified',
                    'partner' =>
                        $lockedProject->partner
                        ?: $lockedProject->allocation->partner
                        ?: 'Not specified',
                    'project_location' =>
                        Str::limit(
                            $lockedProject->payment_location_summary
                                ?: 'Not specified',
                            500,
                            ''
                        ),
                    'term' => $lockedProject->term->label(),
                    'beneficiaries_total' => (int) $row['beneficiaries_total'],
                    'beneficiaries_female' => (int) $row['beneficiaries_female'],
                    'wages_amount' => $paymentService->centsToDecimal($wagesCents),
                    'insurance_amount' => $paymentService->centsToDecimal($insuranceCents),
                    'ppe_amount' => $paymentService->centsToDecimal($ppeCents),
                    'amount' => $paymentService->centsToDecimal($totalCents),
                    'obligation_date' => $row['obligation_date'],
                    'month' => date(
                        'F Y',
                        strtotime($row['obligation_date'])
                    ),
                    'payee' => trim($row['payee']),
                    'remarks' => $row['remarks'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);
            }

            if ($completing) {
                if ($nextTranche === 1) {
                    throw ValidationException::withMessages([
                        'tranches' => 'Record at least one tranche before completing the obligations.',
                    ]);
                }

                if ($obligatedCents !== $payableCents) {
                    throw ValidationException::withMessages([
                        'tranches' => sprintf(
                            'The tranche totals (₱%s) must equal the Total Project Cost (₱%s) before completing. Remaining: ₱%s.',
                            number_format($obligatedCents / 100, 2),
                            number_format($payableCents / 100, 2),
                            number_format(($payableCents - $obligatedCents) / 100, 2)
                        ),
                    ]);
                }

                $lockedProject->update([
                    'obligations_completed_at' => now(),
                    'obligations_completed_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return count($rows);
        });

        if ($completing) {
            return redirect()
                ->route('projects.show', $project)
                ->with(
                    'success',
                    'Obligation tranches completed. The TUPAD Coordinator can now record the Release of Assistance.'
                );
        }

        return redirect()
            ->route('payments.show', $project)
            ->with(
                'success',
                sprintf('%d tranche(s) saved successfully.', $savedCount)
            );
    }

    private function ensureDirectAdministration(Project $project): void
    {
        if (
            $project->implementation_mode
                !== ImplementationMode::DIRECT_ADMINISTRATION
        ) {
            abort(
                403,
                'The Payment of Wages interface applies only to Direct Administration projects.'
            );
        }
    }

}
