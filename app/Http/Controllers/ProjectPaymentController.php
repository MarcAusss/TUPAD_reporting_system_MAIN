<?php

namespace App\Http\Controllers;

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
    public function show(
        Project $project,
        ProjectPaymentService $paymentService
    ): View {
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
            'payout.recorder',
        ]);

        $summary = $paymentService->summary($project);
        $nextTranche = ((int) $project->obligations
            ->max('tranche_number')) + 1;

        return view('payments.show', [
            'project' => $project,
            'summary' => $summary,
            'nextTranche' => $nextTranche,
            'canAddTranche' =>
                $project->status === ProjectStatus::FOR_PAYMENT
                && $project->obligations->count() < 5
                && $summary['obligated_cents']
                    < $summary['payable_cents'],
            'paymentService' => $paymentService,
        ]);
    }

    public function store(
        Request $request,
        Project $project,
        ProjectPaymentService $paymentService
    ): RedirectResponse {
        if ($project->status !== ProjectStatus::FOR_PAYMENT) {
            abort(
                403,
                'Obligations can only be recorded for projects with For Payment status.'
            );
        }

        $validated = $request->validate([
            'tranche_number' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'amount' => [
                'required',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
            ],
            'obligation_date' => ['required', 'date'],
            'payee' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'amount.regex' =>
                'The obligation amount must be a valid positive amount with no more than two decimal places.',
            'tranche_number.between' =>
                'A project may only have Tranches 1 through 5.',
        ]);

        DB::transaction(function () use (
            $request,
            $project,
            $validated,
            $paymentService
        ): void {
            $lockedProject = Project::query()
                ->with(['allocation.adl'])
                ->lockForUpdate()
                ->findOrFail($project->id);

            if ($lockedProject->status !== ProjectStatus::FOR_PAYMENT) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'This project is no longer available for payment processing.',
                ]);
            }

            $obligations = ProjectObligation::query()
                ->where('project_id', $lockedProject->id)
                ->orderBy('tranche_number')
                ->lockForUpdate()
                ->get();

            if ($obligations->count() >= 5) {
                throw ValidationException::withMessages([
                    'tranche_number' =>
                        'The maximum of five payment tranches has already been reached.',
                ]);
            }

            $trancheNumber = (int) $validated['tranche_number'];

            if (
                $obligations->contains(
                    fn (ProjectObligation $obligation): bool =>
                        (int) $obligation->tranche_number === $trancheNumber
                )
            ) {
                throw ValidationException::withMessages([
                    'tranche_number' =>
                        'This tranche number already exists for the project.',
                ]);
            }

            $expectedTranche = ((int) $obligations
                ->max('tranche_number')) + 1;

            if ($trancheNumber !== $expectedTranche) {
                throw ValidationException::withMessages([
                    'tranche_number' => sprintf(
                        'The next allowed payment tranche is Tranche %d.',
                        $expectedTranche
                    ),
                ]);
            }

            $amountCents = $paymentService->amountToCents(
                $validated['amount']
            );

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The obligation amount must be greater than zero.',
                ]);
            }

            $obligatedCents = $obligations->sum(
                fn (ProjectObligation $obligation): int =>
                    $paymentService->obligationCents($obligation)
            );

            $payableCents = $paymentService
                ->payableCents($lockedProject);

            if ($obligatedCents + $amountCents > $payableCents) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Total obligations cannot exceed the remaining payable wage amount of ₱%s.',
                        number_format(
                            ($payableCents - $obligatedCents) / 100,
                            2
                        )
                    ),
                ]);
            }

            if (
                $trancheNumber === 5
                && $obligatedCents + $amountCents !== $payableCents
            ) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'The fifth and final tranche must obligate the full remaining payable wage amount of ₱%s.',
                        number_format(
                            ($payableCents - $obligatedCents) / 100,
                            2
                        )
                    ),
                ]);
            }

            $lockedProject->obligations()->create([
                'tranche_number' => $trancheNumber,
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
                'beneficiaries_total' =>
                    $lockedProject->beneficiaries_total,
                'beneficiaries_female' =>
                    $lockedProject->beneficiaries_female,
                'amount' => $paymentService->centsToDecimal($amountCents),
                'obligation_date' => $validated['obligation_date'],
                'month' => date(
                    'F Y',
                    strtotime($validated['obligation_date'])
                ),
                'payee' => trim($validated['payee']),
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('payments.show', $project)
            ->with(
                'success',
                sprintf(
                    'Tranche %d obligation recorded successfully.',
                    (int) $validated['tranche_number']
                )
            );
    }
}
