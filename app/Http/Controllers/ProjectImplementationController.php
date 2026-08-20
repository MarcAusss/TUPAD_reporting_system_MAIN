<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectImplementationController extends Controller
{
    public function insurance(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'date_enrolled' => [
                'required',
                'date',
            ],

            'beneficiary_count' => [
                'required',
                'integer',
                'min:1',
                'max:' . $project->beneficiaries_total,
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'payment_mode' => [
                'required',
                Rule::in([
                    'voucher',
                    'ca',
                ]),
            ],

            'or_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'policy_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $project->insuranceEnrollment()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Insurance enrollment saved successfully.'
        );
    }

    public function ppe(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'delivery_receipt_date' => [
                'required',
                'date',
            ],

            'ppe_provided' => [
                'required',
                'string',
                'max:5000',
            ],

            'inventory_reference' => [
                'nullable',
                'string',
                'max:150',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $project->ppeDelivery()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'PPE delivery information saved successfully.'
        );
    }

    public function noticeToProceed(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'date_issued' => [
                'required',
                'date',
            ],

            'date_released' => [
                'required',
                'date',
                'after_or_equal:date_issued',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $project->noticeToProceed()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Notice to Proceed saved successfully.'
        );
    }

    public function orientation(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'orientation_date' => [
                'required',
                'date',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $project->orientation()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Orientation information saved successfully.'
        );
    }

    public function implementationPeriod(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $start = \Carbon\Carbon::parse(
            $validated['start_date']
        );

        $end = \Carbon\Carbon::parse(
            $validated['end_date']
        );

        /*
        |--------------------------------------------------------------------------
        | Duration Validation
        |--------------------------------------------------------------------------
        |
        | Inclusive count:
        | Aug 1 through Aug 20 = 20 days.
        |
        */

        $scheduledDays =
            $start->diffInDays($end) + 1;

        if ($scheduledDays !== $project->number_of_days) {
            return back()
                ->withInput()
                ->withErrors([
                    'end_date' => sprintf(
                        'The selected implementation period contains %d days, but the project profile specifies %d days.',
                        $scheduledDays,
                        $project->number_of_days
                    ),
                ]);
        }

        $project->implementation()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Implementation period saved successfully.'
        );
    }

    private function ensurePreparationAllowed(
        Project $project
    ): void {
        if (
            !in_array(
                $project->status,
                [
                    ProjectStatus::APPROVED,
                    ProjectStatus::FOR_IMPLEMENTATION,
                ],
                true
            )
        ) {
            abort(
                403,
                'Implementation preparation can only be modified for Approved or For Implementation projects.'
            );
        }
    }

    private function refreshPreparationStatus(
        Project $project,
        int $userId
    ): void {
        $project->refresh();

        if (
            $project->status === ProjectStatus::APPROVED
            && $project->implementationPreparationComplete()
        ) {
            $project->update([
                'status' =>
                    ProjectStatus::FOR_IMPLEMENTATION,

                'updated_by' =>
                    $userId,
            ]);
        }
    }
}