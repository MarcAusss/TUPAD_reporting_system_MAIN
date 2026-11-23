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
     *
     * Payment tranches cover wages only: insurance is paid at Preparation
     * (GSIS enrollment with its OR number) and PPE at PPE Delivery.
     */
    private const ROW_CONTENT_FIELDS = [
        'beneficiaries_total',
        'beneficiaries_female',
        'wages_amount',
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
            'obligations.releaser',
            'ppeItems',
            'paymentInsurancePpe.recorder',
        ]);

        $summary = $paymentService->summary($project);
        $nextTranche = ((int) $project->obligations
            ->max('tranche_number')) + 1;

        $limits = $paymentService->obligationLimits($project);
        $obligatedTotals = $paymentService->obligatedTotals($project->obligations);
        $remaining = fn (string $key): int => max(0, $limits[$key] - $obligatedTotals[$key]);
        $wagePerBeneficiary = $paymentService->wagePerBeneficiaryCents($project);

        return view('payments.show', [
            'project' => $project,
            'summary' => $summary,
            'nextTranche' => $nextTranche,
            'canEditTranches' =>
                $project->status === ProjectStatus::FOR_PAYMENT
                && ! $summary['obligations_completed'],
            'obligationLimits' => $limits,
            'obligatedTotals' => $obligatedTotals,
            'wagePerBeneficiaryCents' => $wagePerBeneficiary,
            'remainingDefaults' => [
                'beneficiaries_total' => $remaining('beneficiaries_total'),
                'beneficiaries_female' => $remaining('beneficiaries_female'),
                'wages_amount' => $paymentService->centsToDecimal(min(
                    $remaining('wages'),
                    $remaining('beneficiaries_total') * $wagePerBeneficiary
                )),
            ],
            'paymentService' => $paymentService,
            // Insurance and PPE for the beneficiaries saved in tranches so
            // far; the page recomputes it live as tranches are entered.
            'insurancePpe' => $paymentService->insurancePpeComputation(
                $project,
                $obligatedTotals['beneficiaries_total'],
            ),
            'canEditInsurancePpe' =>
                $project->status === ProjectStatus::FOR_PAYMENT
                && ! $summary['obligations_completed'],
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
                ->contains(fn (string $field): bool => trim((string) ($row[$field] ?? '')) !== ''))
            // Accept formatted amounts such as "9,100.00".
            ->map(function (array $row): array {
                if (isset($row['wages_amount'])) {
                    $row['wages_amount'] = str_replace([',', ' '], '', trim((string) $row['wages_amount']));
                }

                return $row;
            });

        $request->merge(['tranches' => $rows->all()]);

        $validated = $request->validate([
            // Missing intent (e.g. submitted without a button) means Save.
            'intent' => ['nullable', 'in:save,complete'],
            'tranches' => [$completing ? 'nullable' : 'required', 'array'],
            'tranches.*.beneficiaries_total' => ['required', 'integer', 'min:1'],
            'tranches.*.beneficiaries_female' => ['nullable', 'integer', 'min:0', 'lte:tranches.*.beneficiaries_total'],
            'tranches.*.wages_amount' => ['required', self::MONEY_RULE],
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

            // Tranches may add up to less than the project's own figures,
            // but never more.
            $limits = $paymentService->obligationLimits($lockedProject);
            $running = $paymentService->obligatedTotals($obligations);
            $nextTranche = ((int) $obligations->max('tranche_number')) + 1;
            $rows = $validated['tranches'] ?? [];

            foreach ($rows as $key => $row) {
                // Wages only; insurance and PPE were settled at Preparation.
                $wagesCents = $paymentService->amountToCents($row['wages_amount'] ?? null);
                $insuranceCents = 0;
                $ppeCents = 0;
                $totalCents = $wagesCents;

                if ($totalCents <= 0) {
                    throw ValidationException::withMessages([
                        "tranches.{$key}.wages_amount" =>
                            'A tranche\'s wages must be greater than zero.',
                    ]);
                }

                $rowValues = [
                    'beneficiaries_total' => (int) $row['beneficiaries_total'],
                    'beneficiaries_female' => (int) ($row['beneficiaries_female'] ?? 0),
                    'wages' => $wagesCents,
                    'insurance' => $insuranceCents,
                    'ppe' => $ppeCents,
                    'total' => $totalCents,
                ];

                $this->ensureWithinProjectLimits($key, $rowValues, $running, $limits);

                foreach ($rowValues as $limitKey => $value) {
                    $running[$limitKey] += $value;
                }

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
                    'beneficiaries_female' => (int) ($row['beneficiaries_female'] ?? 0),
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

                // Insurance and PPE must match the final paid beneficiaries:
                // with none deducted they stay as planned (no record); a form
                // saved for them (with any edits) is kept; a missing or
                // out-of-date one is recomputed automatically.
                $finalBeneficiaries = (int) $running['beneficiaries_total'];
                $savedInsurancePpe = $lockedProject->paymentInsurancePpe()->first();

                if ($finalBeneficiaries >= (int) $lockedProject->beneficiaries_total) {
                    $savedInsurancePpe?->delete();
                } elseif ($savedInsurancePpe?->beneficiaries_paid !== $finalBeneficiaries) {
                    $paymentService->recordInsurancePpe(
                        $lockedProject,
                        $finalBeneficiaries,
                        $request->user()->id,
                    );
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
                sprintf(
                    '%d tranche(s) saved successfully. Record the disbursements next; the TUPAD Coordinator is notified once a tranche is fully disbursed.',
                    $savedCount
                )
            );
    }

    /**
     * Insurance and PPE form (saved separately from the tranches). The
     * number of paid beneficiaries comes from the tranches — saved plus any
     * still being entered — and every amount is recomputed here.
     */
    public function storeInsurancePpe(
        Request $request,
        Project $project,
        ProjectPaymentService $paymentService
    ): RedirectResponse {
        $this->ensureDirectAdministration($project);

        if ($project->status !== ProjectStatus::FOR_PAYMENT) {
            abort(403, 'Insurance and PPE can only be recorded for projects with For Payment status.');
        }

        $project->loadMissing('ppeItems');

        // Accept formatted amounts such as "4,500.00".
        $unformat = fn ($value) => $value === null ? null : str_replace([',', ' '], '', trim((string) $value));
        $request->merge([
            'insurance_amount' => $unformat($request->input('insurance_amount')),
            'ppe' => collect($request->input('ppe', []))
                ->map(fn ($row) => is_array($row) ? [...$row, 'amount' => $unformat($row['amount'] ?? null)] : $row)
                ->all(),
        ]);

        $insuredPlanned = (int) ($project->insurance_beneficiaries ?? $project->beneficiaries_total);
        $rules = [
            // Only open when beneficiaries were deducted (paid < project).
            'beneficiaries_paid' => ['required', 'integer', 'min:1', 'max:'.max(0, (int) $project->beneficiaries_total - 1)],
            'insurance_beneficiaries' => ['nullable', 'integer', 'min:0', 'max:'.$insuredPlanned],
            'insurance_amount' => ['nullable', self::MONEY_RULE],
            'ppe' => ['nullable', 'array'],
            'insurance_ppe_remarks' => ['nullable', 'string', 'max:3000'],
        ];

        foreach ($project->ppeItems as $item) {
            $rules["ppe.{$item->id}.beneficiaries"] = ['nullable', 'integer', 'min:0', 'max:'.(int) $item->beneficiary_count];
            $rules["ppe.{$item->id}.amount"] = ['nullable', self::MONEY_RULE];
        }

        $validated = $request->validate($rules, [
            'beneficiaries_paid.min' => 'Enter the tranche beneficiaries first; Insurance and PPE are computed from them.',
            'beneficiaries_paid.max' => 'No beneficiaries are deducted, so Insurance and PPE stay as planned and cannot be saved here.',
            '*.regex' => 'Amounts must be valid positive values with no more than two decimal places.',
            'ppe.*.amount.regex' => 'PPE amounts must be valid positive values with no more than two decimal places.',
        ], [
            'beneficiaries_paid' => 'paid beneficiaries',
            'insurance_beneficiaries' => 'insured beneficiaries',
            'insurance_amount' => 'insurance amount',
            'ppe.*.beneficiaries' => 'PPE beneficiaries',
            'insurance_ppe_remarks' => 'remarks',
        ]);

        $overrides = $this->insurancePpeOverrides($project, $validated, $paymentService);

        DB::transaction(function () use ($request, $project, $validated, $overrides, $paymentService): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($lockedProject->obligations_completed_at !== null) {
                throw ValidationException::withMessages([
                    'beneficiaries_paid' => 'The obligation tranches are completed; Insurance and PPE were locked with them.',
                ]);
            }

            $savedBeneficiaries = (int) ProjectObligation::query()
                ->where('project_id', $lockedProject->id)
                ->sum('beneficiaries_total');

            if ((int) $validated['beneficiaries_paid'] < $savedBeneficiaries) {
                throw ValidationException::withMessages([
                    'beneficiaries_paid' => sprintf(
                        'The saved tranches already cover %s beneficiaries, so Insurance and PPE cannot be computed for fewer.',
                        number_format($savedBeneficiaries),
                    ),
                ]);
            }

            $paymentService->recordInsurancePpe(
                $lockedProject,
                (int) $validated['beneficiaries_paid'],
                $request->user()->id,
                $validated['insurance_ppe_remarks'] ?? null,
                replaceRemarks: true,
                overrides: $overrides,
            );
        });

        return redirect()
            ->to(route('payments.show', $project).'#insurance-ppe')
            ->with('success', 'Insurance and PPE saved.');
    }

    /**
     * Edited Insurance and PPE values, each capped at the project's planned
     * amount (they may be reduced, never increased).
     *
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function insurancePpeOverrides(
        Project $project,
        array $validated,
        ProjectPaymentService $paymentService,
    ): array {
        $overrides = ['ppe' => []];
        $errors = [];
        $money = fn (int $cents): string => '₱'.number_format($cents / 100, 2);

        if (($validated['insurance_beneficiaries'] ?? null) !== null) {
            $overrides['insurance_beneficiaries'] = (int) $validated['insurance_beneficiaries'];
        }

        if (($validated['insurance_amount'] ?? null) !== null) {
            $cents = $paymentService->amountToCents($validated['insurance_amount']);
            $planned = $paymentService->amountToCents($project->insurance_total);

            if ($cents > $planned) {
                $errors['insurance_amount'] = 'Insurance cannot exceed the project\'s '.$money($planned).'.';
            }

            $overrides['insurance_amount_cents'] = $cents;
        }

        foreach ($project->ppeItems as $item) {
            $row = $validated['ppe'][$item->id] ?? [];

            if (($row['beneficiaries'] ?? null) !== null) {
                $overrides['ppe'][$item->id]['beneficiaries'] = (int) $row['beneficiaries'];
            }

            if (($row['amount'] ?? null) !== null) {
                $cents = $paymentService->amountToCents($row['amount']);
                $planned = $paymentService->amountToCents($item->total_amount);

                if ($cents > $planned) {
                    $errors["ppe.{$item->id}.amount"] = "{$item->product} cannot exceed the project's ".$money($planned).'.';
                }

                $overrides['ppe'][$item->id]['amount_cents'] = $cents;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $overrides;
    }

    /**
     * @param  array<string,int>  $rowValues
     * @param  array<string,int>  $running  already obligated (saved + earlier rows)
     * @param  array<string,int>  $limits  the project's own figures
     */
    private function ensureWithinProjectLimits(
        int|string $key,
        array $rowValues,
        array $running,
        array $limits,
    ): void {
        $checks = [
            'beneficiaries_total' => ['beneficiaries_total', 'number of beneficiaries', false],
            'beneficiaries_female' => ['beneficiaries_female', 'female beneficiaries', false],
            'wages' => ['wages_amount', 'wages', true],
            'insurance' => ['insurance_amount', 'insurance', true],
            'ppe' => ['ppe_amount', 'PPE amount', true],
            'total' => ['wages_amount', 'Total Project Cost', true],
        ];

        foreach ($checks as $limitKey => [$field, $label, $isMoney]) {
            if ($running[$limitKey] + $rowValues[$limitKey] <= $limits[$limitKey]) {
                continue;
            }

            $format = fn (int $value): string => $isMoney
                ? '₱'.number_format($value / 100, 2)
                : number_format($value);

            throw ValidationException::withMessages([
                "tranches.{$key}.{$field}" => sprintf(
                    'This exceeds the project\'s %s. Project: %s · already obligated: %s · remaining: %s.',
                    $label,
                    $format($limits[$limitKey]),
                    $format($running[$limitKey]),
                    $format(max(0, $limits[$limitKey] - $running[$limitKey])),
                ),
            ]);
        }
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
