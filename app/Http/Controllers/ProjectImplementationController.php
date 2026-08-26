<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ImplementationStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectImplementationController extends Controller
{
    public function __construct(
        private readonly ImplementationStageService $implementationStageService
    ) {
    }

    /**
     * Save Insurance Enrollment, PPE Delivery, and Notice to Proceed
     * as one implementation-requirements submission.
     */
    public function preparationRequirements(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            /*
            |--------------------------------------------------------------------------
            | Insurance Enrollment
            |--------------------------------------------------------------------------
            */

            'insurance.date_enrolled' => [
                'required',
                'date',
            ],

            'insurance.payment_mode' => [
                'required',
                Rule::in([
                    'voucher',
                    'ca',
                ]),
            ],

            'insurance.or_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'insurance.policy_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'insurance.remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],

            /*
            |--------------------------------------------------------------------------
            | PPE Delivery
            |--------------------------------------------------------------------------
            */

            'ppe.delivery_receipt_date' => [
                'required',
                'date',
            ],

            'ppe.ppe_provided' => [
                'required',
                'string',
                'max:5000',
            ],

            'ppe.remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Notice to Proceed
            |--------------------------------------------------------------------------
            */

            'ntp.date_issued' => [
                'required',
                'date',
            ],

            'ntp.date_released' => [
                'required',
                'date',
                'after_or_equal:ntp.date_issued',
            ],

            'ntp.remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        DB::transaction(
            function () use (
                $request,
                $project,
                $validated
            ) {
                /*
                |--------------------------------------------------------------------------
                | Insurance - approved values stay locked
                |--------------------------------------------------------------------------
                */

                $project
                    ->insuranceEnrollment()
                    ->updateOrCreate(
                        [
                            'project_id' =>
                                $project->id,
                        ],
                        [
                            'date_enrolled' =>
                                $validated['insurance']['date_enrolled'],

                            'beneficiary_count' =>
                                (int) (
                                    $project->insurance_beneficiaries
                                    ?? $project->beneficiaries_total
                                ),

                            'amount' =>
                                (float) $project->insurance_total,

                            'payment_mode' =>
                                $validated['insurance']['payment_mode'],

                            'or_number' =>
                                $validated['insurance']['or_number']
                                    ?? null,

                            'policy_number' =>
                                $validated['insurance']['policy_number']
                                    ?? null,

                            'remarks' =>
                                $validated['insurance']['remarks']
                                    ?? null,

                            'recorded_by' =>
                                $request->user()->id,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | PPE Delivery
                |--------------------------------------------------------------------------
                */

                $project
                    ->ppeDelivery()
                    ->updateOrCreate(
                        [
                            'project_id' =>
                                $project->id,
                        ],
                        [
                            'delivery_receipt_date' =>
                                $validated['ppe']['delivery_receipt_date'],

                            'ppe_provided' =>
                                $validated['ppe']['ppe_provided'],

                            'inventory_reference' =>
                                null,

                            'remarks' =>
                                $validated['ppe']['remarks']
                                    ?? null,

                            'recorded_by' =>
                                $request->user()->id,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | Notice to Proceed
                |--------------------------------------------------------------------------
                */

                $project
                    ->noticeToProceed()
                    ->updateOrCreate(
                        [
                            'project_id' =>
                                $project->id,
                        ],
                        [
                            'date_issued' =>
                                $validated['ntp']['date_issued'],

                            'date_released' =>
                                $validated['ntp']['date_released'],

                            'remarks' =>
                                $validated['ntp']['remarks']
                                    ?? null,

                            'recorded_by' =>
                                $request->user()->id,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | Refresh Preparation Status Once
                |--------------------------------------------------------------------------
                |
                | The three requirement records are committed together. If one
                | save fails, none of the three remains partially saved.
                |
                */

                $this->refreshPreImplementationStatus(
                    $project,
                    $request->user()->id
                );
            }
        );

        return back()->with(
            'success',
            'Implementation requirements saved successfully.'
        );
    }

    public function insurance(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'date_enrolled' => ['required', 'date'],

            /*
            |--------------------------------------------------------------------------
            | Approved Project Values Are Locked
            |--------------------------------------------------------------------------
            |
            | beneficiary_count and amount are intentionally NOT accepted from
            | the request. They are derived from the approved Project record.
            |
            */

            'payment_mode' => ['required', Rule::in(['voucher', 'ca'])],
            'or_number' => ['nullable', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $project->insuranceEnrollment()->updateOrCreate(
            ['project_id' => $project->id],
            [
                ...$validated,

                /*
                |--------------------------------------------------------------------------
                | Locked values from the approved Project
                |--------------------------------------------------------------------------
                */

                'beneficiary_count' =>
                    (int) (
                        $project->insurance_beneficiaries
                        ?? $project->beneficiaries_total
                    ),

                'amount' =>
                    (float) $project->insurance_total,

                'recorded_by' =>
                    $request->user()->id,
            ]
        );

        $this->refreshPreImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Insurance enrollment saved successfully.'
        );
    }

    public function ppe(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'delivery_receipt_date' => ['required', 'date'],
            'ppe_provided' => ['required', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $project->ppeDelivery()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'delivery_receipt_date' => $validated['delivery_receipt_date'],
                'ppe_provided' => $validated['ppe_provided'],
                'inventory_reference' => null,
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'PPE delivery information saved successfully.'
        );
    }

    public function noticeToProceed(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        $validated = $request->validate([
            'date_issued' => ['required', 'date'],
            'date_released' => ['required', 'date', 'after_or_equal:date_issued'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $project->noticeToProceed()->updateOrCreate(
            ['project_id' => $project->id],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->refreshPreImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Notice to Proceed saved successfully.'
        );
    }

    public function orientation(Request $request, Project $project): RedirectResponse
    {
        $this->ensureSchedulingAllowed($project);

        $validated = $request->validate([
            'orientation_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $project->orientation()->updateOrCreate(
            ['project_id' => $project->id],
            [
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->synchronizeImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Orientation information saved successfully.'
        );
    }

    public function implementationPeriod(Request $request, Project $project): RedirectResponse
    {
        $this->ensureSchedulingAllowed($project);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Automatic Implementation End Date
        |--------------------------------------------------------------------------
        |
        | The project's approved Duration / Number of Days is the source of truth.
        | The TC selects only the Start Date.
        |
        | Example:
        | Duration: 20 days
        | Start:    August 25, 2026
        | End:      September 14, 2026
        |
        | Any end_date sent by a manipulated request is ignored.
        |
        */

        $durationDays =
            max(
                1,
                (int) $project->number_of_days
            );

        $startDate =
            Carbon::parse(
                $validated['start_date']
            )->startOfDay();

        $endDate =
            $startDate
                ->copy()
                ->addDays(
                    $durationDays
                );

        $project->implementation()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'start_date' =>
                    $startDate->toDateString(),

                'end_date' =>
                    $endDate->toDateString(),

                'remarks' =>
                    $validated['remarks'] ?? null,

                'recorded_by' =>
                    $request->user()->id,
            ]
        );

        $this->synchronizeImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            sprintf(
                'Implementation period saved. End Date was automatically calculated as %s from the %d-day project duration.',
                $endDate->format('F d, Y'),
                $durationDays,
            )
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
                'Project Implementation records in this workflow apply only to Direct Administration projects.'
            );
        }
    }

    private function ensurePreparationAllowed(Project $project): void
    {
        $this->ensureDirectAdministration($project);

        if (! in_array(
            $project->status,
            [
                ProjectStatus::APPROVED,
                ProjectStatus::FOR_IMPLEMENTATION,
            ],
            true
        )) {
            abort(
                403,
                'Insurance, PPE, and Notice to Proceed can only be modified for Approved or For Implementation projects.'
            );
        }
    }

    private function ensureSchedulingAllowed(Project $project): void
    {
        $this->ensureDirectAdministration($project);

        if ($project->status !== ProjectStatus::FOR_IMPLEMENTATION) {
            abort(
                403,
                'Orientation and the Implementation Work Period can only be recorded after the project reaches For Implementation.'
            );
        }
    }

    private function refreshPreImplementationStatus(
        Project $project,
        int $userId
    ): void {
        $project->refresh();

        if (
            $project->status === ProjectStatus::APPROVED
            && $project->preImplementationRequirementsComplete()
        ) {
            $project->update([
                'status' =>
                    ProjectStatus::FOR_IMPLEMENTATION,

                'updated_by' =>
                    $userId,
            ]);
        }
    }

    private function synchronizeImplementationStatus(
        Project $project,
        int $userId
    ): void {
        $project->refresh();

        $this->implementationStageService->synchronize(
            $project,
            $userId
        );
    }
}
