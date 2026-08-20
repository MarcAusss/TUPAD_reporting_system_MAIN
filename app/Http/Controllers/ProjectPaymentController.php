<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectPaymentController extends Controller
{
    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_PAYMENT
        ) {
            abort(
                403,
                'Payment information can only be recorded for projects with For Payment status.'
            );
        }

        if ($project->obligation()->exists()) {
            return back()->withErrors([
                'payee' =>
                    'This project already has an obligation/payment record.',
            ]);
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'obligation_date' => [
                'required',
                'date',
            ],

            'month' => [
                'required',
                'string',
                'max:30',
            ],

            'payee' => [
                'required',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        return DB::transaction(function () use ($request, $project, $validated) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->with([
                    'allocation.adl',
                ])
                ->findOrFail($project->id);

            if (
                $lockedProject->status
                !== ProjectStatus::FOR_PAYMENT
            ) {
                return back()->withErrors([
                    'amount' =>
                        'This project is no longer available for payment processing.',
                ]);
            }

            if ($lockedProject->obligation()->exists()) {
                return back()->withErrors([
                    'payee' =>
                        'This project already has an obligation/payment record.',
                ]);
            }

            $amount = round(
                (float) $validated['amount'],
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Initial Payment Validation
            |--------------------------------------------------------------------------
            |
            | For now, do not allow payment above total project cost.
            |
            */

            if (
                $amount
                > (float) $lockedProject->total_project_cost
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'amount' => sprintf(
                            'Payment amount cannot exceed the total project cost of ₱%s.',
                            number_format(
                                $lockedProject->total_project_cost,
                                2
                            )
                        ),
                    ]);
            }

            $lockedProject->obligation()->create([
                'adl_number' =>
                    $lockedProject
                        ->allocation
                        ->adl
                        ->adl_number,

                'fund_sponsor' =>
                    $lockedProject
                        ->allocation
                        ->fund_sponsor,

                'partner' =>
                    $lockedProject
                        ->allocation
                        ->partner,

                'project_location' =>
                    implode(', ', [
                        $lockedProject->barangay,
                        $lockedProject->municipality,
                        $lockedProject->province,
                    ]),

                'term' =>
                    $lockedProject->term->label(),

                'beneficiaries_total' =>
                    $lockedProject
                        ->beneficiaries_total,

                'beneficiaries_female' =>
                    $lockedProject
                        ->beneficiaries_female,

                'amount' =>
                    $amount,

                'obligation_date' =>
                    $validated['obligation_date'],

                'month' =>
                    trim($validated['month']),

                'payee' =>
                    trim($validated['payee']),

                'remarks' =>
                    $validated['remarks'] ?? null,

                'recorded_by' =>
                    $request->user()->id,
            ]);

            return redirect()
                ->route(
                    'projects.show',
                    $lockedProject
                )
                ->with(
                    'success',
                    'Payment/obligation information recorded successfully.'
                );
        });
    }
}