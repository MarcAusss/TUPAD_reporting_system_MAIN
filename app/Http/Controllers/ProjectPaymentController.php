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
            'obligations.releaser',
        ]);

        $summary = $paymentService->summary($project);
        $nextTranche = ((int) $project->obligations
            ->max('tranche_number')) + 1;

        $limits = $paymentService->obligationLimits($project);
        $obligatedTotals = $paymentService->obligatedTotals($project->obligations);
        $remaining = fn (string $key): int => max(0, $limits[$key] - $obligatedTotals[$key]);
        $wagePerBeneficiary = $paymentService->wagePerBeneficiaryCents($project);
        $insurancePerBeneficiary = $paymentService->insurancePerBeneficiaryCents($project);

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
            'insurancePerBeneficiaryCents' => $insurancePerBeneficiary,
            'remainingDefaults' => [
                'beneficiaries_total' => $remaining('beneficiaries_total'),
                'beneficiaries_female' => $remaining('beneficiaries_female'),
                'wages_amount' => $paymentService->centsToDecimal(min(
                    $remaining('wages'),
                    $remaining('beneficiaries_total') * $wagePerBeneficiary
                )),
                'insurance_amount' => $paymentService->centsToDecimal(min(
                    $remaining('insurance'),
                    $remaining('beneficiaries_total') * $insurancePerBeneficiary
                )),
                'ppe_amount' => $paymentService->centsToDecimal($remaining('ppe')),
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
                ->contains(fn (string $field): bool => trim((string) ($row[$field] ?? '')) !== ''))
            // Accept formatted amounts such as "9,100.00".
            ->map(function (array $row): array {
                foreach (['wages_amount', 'insurance_amount', 'ppe_amount'] as $field) {
                    if (isset($row[$field])) {
                        $row[$field] = str_replace([',', ' '], '', trim((string) $row[$field]));
                    }
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
            // Only one of wages, insurance, or PPE is needed; blanks count as zero.
            'tranches.*.wages_amount' => [
                'nullable',
                'required_without_all:tranches.*.insurance_amount,tranches.*.ppe_amount',
                self::MONEY_RULE,
            ],
            'tranches.*.insurance_amount' => ['nullable', self::MONEY_RULE],
            'tranches.*.ppe_amount' => ['nullable', self::MONEY_RULE],
            'tranches.*.obligation_date' => ['required', 'date'],
            'tranches.*.payee' => ['required', 'string', 'max:255'],
            'tranches.*.remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'tranches.required' => 'Enter at least one tranche before saving.',
            'tranches.*.wages_amount.required_without_all' =>
                'Enter at least one amount (wages, insurance, or PPE) for each tranche.',
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

            // Tranches may add up to less than the project's own figures,
            // but never more.
            $limits = $paymentService->obligationLimits($lockedProject);
            $running = $paymentService->obligatedTotals($obligations);
            $nextTranche = ((int) $obligations->max('tranche_number')) + 1;
            $rows = $validated['tranches'] ?? [];

            foreach ($rows as $key => $row) {
                $wagesCents = $paymentService->amountToCents($row['wages_amount'] ?? null);
                $insuranceCents = $paymentService->amountToCents($row['insurance_amount'] ?? null);
                $ppeCents = $paymentService->amountToCents($row['ppe_amount'] ?? null);
                $totalCents = $wagesCents + $insuranceCents + $ppeCents;

                if ($totalCents <= 0) {
                    throw ValidationException::withMessages([
                        "tranches.{$key}.wages_amount" =>
                            'A tranche\'s total overall amount must be greater than zero.',
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
