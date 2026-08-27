<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectObligation;
use App\Services\Payments\ProjectPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectDisbursementController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectObligation $obligation,
        ProjectPaymentService $paymentService
    ): RedirectResponse {
        if ((int) $obligation->project_id !== (int) $project->id) {
            abort(404);
        }

        if ($project->status !== ProjectStatus::FOR_PAYMENT) {
            abort(
                403,
                'Disbursements can only be recorded for projects with For Payment status.'
            );
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
            ],
            'date_disbursed' => ['required', 'date'],
            'ldap_check_number' => [
                'required',
                'string',
                'max:150',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'amount.regex' =>
                'The disbursement amount must be a valid positive amount with no more than two decimal places.',
        ]);

        $completed = DB::transaction(function () use (
            $request,
            $project,
            $obligation,
            $validated,
            $paymentService
        ): bool {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            if ($lockedProject->status !== ProjectStatus::FOR_PAYMENT) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'This project is no longer available for payment processing.',
                ]);
            }

            $lockedObligation = ProjectObligation::query()
                ->where('project_id', $lockedProject->id)
                ->lockForUpdate()
                ->findOrFail($obligation->id);

            ProjectDisbursement::query()
                ->where('project_obligation_id', $lockedObligation->id)
                ->lockForUpdate()
                ->get();

            $amountCents = $paymentService->amountToCents(
                $validated['amount']
            );

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The disbursement amount must be greater than zero.',
                ]);
            }

            $alreadyDisbursed = $paymentService
                ->disbursedForObligationCents($lockedObligation);

            $obligationAmount = $paymentService
                ->obligationCents($lockedObligation);

            if ($alreadyDisbursed + $amountCents > $obligationAmount) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Disbursement cannot exceed the remaining tranche obligation of ₱%s.',
                        number_format(
                            ($obligationAmount - $alreadyDisbursed) / 100,
                            2
                        )
                    ),
                ]);
            }

            $duplicateReference = ProjectDisbursement::query()
                ->where('project_obligation_id', $lockedObligation->id)
                ->where(
                    'ldap_check_number',
                    trim($validated['ldap_check_number'])
                )
                ->exists();

            if ($duplicateReference) {
                throw ValidationException::withMessages([
                    'ldap_check_number' =>
                        'This LDAP / Check Number is already recorded for the tranche.',
                ]);
            }

            $lockedObligation->disbursements()->create([
                'amount' => $paymentService->centsToDecimal($amountCents),
                'date_disbursed' => $validated['date_disbursed'],
                'ldap_check_number' =>
                    trim($validated['ldap_check_number']),
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            return $paymentService->synchronizeCompletion(
                $lockedProject,
                (int) $request->user()->id
            );
        });

        return redirect()
            ->route('payments.show', $project)
            ->with(
                'success',
                $completed
                    ? 'Disbursement recorded. The payable amount is fully disbursed and the project is now Completed.'
                    : 'Disbursement recorded for the selected tranche.'
            );
    }
}
