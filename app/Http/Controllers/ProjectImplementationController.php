<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectPpeItem;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectImplementationController extends Controller
{
    public function __construct(
        private readonly ProjectStatusEngine $statusEngine
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
                    ->ppeDeliveries()
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

    /**
     * Record one PPE delivery receipt. A project may have several receipts
     * (e.g. shirts in one batch, boots in another) — each submission of this
     * form adds a new receipt rather than replacing a prior one.
     */
    public function ppe(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        $project->loadMissing('ppeItems');
        $hasPlannedItems = $project->ppeItems->isNotEmpty();

        $validated = $request->validate([
            'delivery_receipt_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:3000'],

            'items' => [
                $hasPlannedItems ? 'required' : 'nullable',
                'array',
            ],
            'items.*.ppe_item_id' => [
                'required',
                'integer',
                Rule::exists('project_ppe_items', 'id')
                    ->where('project_id', $project->id),
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $items = collect($validated['items'] ?? [])
            ->unique(fn (array $entry): int => (int) $entry['ppe_item_id'])
            ->values();

        if ($hasPlannedItems && $items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Select at least one PPE item that was delivered.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Do Not Exceed What Was Planned
        |--------------------------------------------------------------------------
        |
        | Each project_ppe_items row already declares how many units are
        | planned (Beneficiaries x Quantity). A delivery receipt cannot push
        | the cumulative delivered amount for that item past that plan.
        |
        */

        foreach ($items as $entry) {
            /** @var ProjectPpeItem|null $ppeItem */
            $ppeItem = $project->ppeItems->firstWhere(
                'id',
                (int) $entry['ppe_item_id']
            );

            if (! $ppeItem) {
                continue;
            }

            $remaining = $ppeItem->remainingDeliverableQuantity();

            if ((int) $entry['quantity'] > $remaining) {
                /*
                |--------------------------------------------------------------------------
                | Error Key Matches the Submitted Field
                |--------------------------------------------------------------------------
                |
                | The form names each item's inputs items[{ppe_item_id}][...], so the
                | error must be keyed by the item's own id (not the loop position,
                | which was re-indexed by the earlier unique()->values() call) for
                | the per-item @error() block in the view to pick it up.
                |
                */

                throw ValidationException::withMessages([
                    "items.{$ppeItem->id}.quantity" => sprintf(
                        'Only %s unit(s) of "%s" remain to be delivered (planned: %s).',
                        number_format($remaining),
                        $ppeItem->product,
                        number_format($ppeItem->plannedQuantity()),
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($request, $project, $validated, $items): void {
            $summary = $items->isEmpty()
                ? 'No PPE items required for this project.'
                : $items
                    ->map(function (array $entry) use ($project): string {
                        $ppeItem = $project->ppeItems->firstWhere(
                            'id',
                            (int) $entry['ppe_item_id']
                        );

                        return ($ppeItem?->product ?? 'PPE item')
                            .' x'.(int) $entry['quantity'];
                    })
                    ->implode(', ');

            $delivery = $project->ppeDeliveries()->create([
                'delivery_receipt_date' => $validated['delivery_receipt_date'],
                'ppe_provided' => $summary,
                'inventory_reference' => null,
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($items as $entry) {
                $delivery->items()->create([
                    'ppe_item_id' => (int) $entry['ppe_item_id'],
                    'quantity' => (int) $entry['quantity'],
                ]);
            }
        });

        $this->refreshPreImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'PPE delivery receipt saved successfully.'
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
            'beneficiaries_oriented' => [
                'required',
                'integer',
                'min:0',
                'lte:'.(int) $project->beneficiaries_total,
            ],
            'venue' => ['required', 'string', 'max:255'],
            'oriented_by' => ['required', 'string', 'max:255'],
            'alkansssya_conducted' => ['nullable', 'boolean'],
            'yakap_conducted' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $project->orientation()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'orientation_date' => $validated['orientation_date'],
                'beneficiaries_oriented' => $validated['beneficiaries_oriented'],
                'venue' => trim($validated['venue']),
                'oriented_by' => trim($validated['oriented_by']),
                'alkansssya_conducted' => $request->boolean('alkansssya_conducted'),
                'yakap_conducted' => $request->boolean('yakap_conducted'),
                'remarks' => $validated['remarks'] ?? null,
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
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Manually Recorded Implementation Period
        |--------------------------------------------------------------------------
        |
        | Start Date and End Date are both authoritative user inputs. The approved
        | project duration remains reference information only and is not used to
        | calculate or overwrite the End Date.
        |
        */

        $project->implementation()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->synchronizeImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Implementation period saved successfully.'
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
        $this->statusEngine->synchronize(
            $project,
            actorId: $userId,
        );
    }

    private function synchronizeImplementationStatus(
        Project $project,
        int $userId
    ): void {
        $this->statusEngine->synchronize(
            $project,
            actorId: $userId,
        );
    }
}
