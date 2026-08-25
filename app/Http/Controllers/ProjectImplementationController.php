<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ProjectImplementationController extends Controller
{
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
                    (int) $project->beneficiaries_total,

                'amount' =>
                    (float) $project->insurance_total,

                'recorded_by' =>
                    $request->user()->id,
            ]
        );

        $this->refreshPreparationStatus($project, $request->user()->id);

        return back()->with('success', 'Insurance enrollment saved successfully.');
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

        $this->refreshPreparationStatus($project, $request->user()->id);

        return back()->with('success', 'PPE delivery information saved successfully.');
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

        $this->refreshPreparationStatus($project, $request->user()->id);

        return back()->with('success', 'Notice to Proceed saved successfully.');
    }

    public function orientation(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

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

        $this->refreshPreparationStatus($project, $request->user()->id);

        return back()->with('success', 'Orientation information saved successfully.');
    }

    public function implementationPeriod(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

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

        $this->refreshPreparationStatus(
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

    private function ensurePreparationAllowed(Project $project): void
    {
        if (! in_array(
            $project->status,
            [ProjectStatus::APPROVED, ProjectStatus::FOR_IMPLEMENTATION],
            true
        )) {
            abort(
                403,
                'Implementation preparation can only be modified for Approved or For Implementation projects.'
            );
        }
    }

    private function refreshPreparationStatus(Project $project, int $userId): void
    {
        $project->refresh();

        if (
            $project->status === ProjectStatus::APPROVED
            && $project->implementationPreparationComplete()
        ) {
            $project->update([
                'status' => ProjectStatus::FOR_IMPLEMENTATION,
                'updated_by' => $userId,
            ]);
        }
    }
}
