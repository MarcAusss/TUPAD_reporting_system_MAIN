<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectNafaAttachment;
use App\Models\ProjectPpeItem;
use App\Services\Projects\AcpWorkflowService;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectImplementationController extends Controller
{
    public function __construct(
        private readonly ProjectStatusEngine $statusEngine,
        private readonly AcpWorkflowService $acpWorkflow,
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
     * Record one or more PPE delivery receipts in a single submission (e.g.
     * shirts in one batch, boots in another). A project may already have
     * prior receipts — every submission of this form adds new receipts
     * rather than replacing them.
     */
    public function ppe(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        $project->loadMissing('ppeItems');
        $hasPlannedItems = $project->ppeItems->isNotEmpty();

        $validated = $request->validate([
            'deliveries' => ['required', 'array', 'min:1'],
            'deliveries.*.delivery_receipt_date' => ['required', 'date'],
            'deliveries.*.remarks' => ['nullable', 'string', 'max:3000'],

            'deliveries.*.items' => [
                $hasPlannedItems ? 'required' : 'nullable',
                'array',
            ],
            'deliveries.*.items.*.ppe_item_id' => [
                'required',
                'integer',
                Rule::exists('project_ppe_items', 'id')
                    ->where('project_id', $project->id),
            ],
            'deliveries.*.items.*.quantity' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $deliveries = collect($validated['deliveries'])
            ->map(function (array $delivery): array {
                $delivery['items'] = collect($delivery['items'] ?? [])
                    ->unique(fn (array $entry): int => (int) $entry['ppe_item_id'])
                    ->values()
                    ->all();

                return $delivery;
            })
            ->values();

        foreach ($deliveries as $index => $delivery) {
            if ($hasPlannedItems && $delivery['items'] === []) {
                throw ValidationException::withMessages([
                    "deliveries.{$index}.items" => 'Select at least one PPE item that was delivered.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Do Not Exceed What Was Planned, Across Every Receipt In This Submission
        |--------------------------------------------------------------------------
        |
        | Each project_ppe_items row already declares how many units are
        | planned (Beneficiaries x Quantity). Several receipts can be
        | submitted at once, so the same item's quantities are summed across
        | all of them before checking against what still remains — a single
        | receipt could look fine on its own while the batch as a whole
        | over-delivers an item.
        |
        */

        $requestedByItem = [];

        foreach ($deliveries as $delivery) {
            foreach ($delivery['items'] as $entry) {
                $itemId = (int) $entry['ppe_item_id'];
                $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + (int) $entry['quantity'];
            }
        }

        foreach ($requestedByItem as $itemId => $totalRequested) {
            /** @var ProjectPpeItem|null $ppeItem */
            $ppeItem = $project->ppeItems->firstWhere('id', $itemId);

            if (! $ppeItem) {
                continue;
            }

            $remaining = $ppeItem->remainingDeliverableQuantity();

            if ($totalRequested > $remaining) {
                /*
                |--------------------------------------------------------------------------
                | Error Key Matches the Submitted Field
                |--------------------------------------------------------------------------
                |
                | The form names each item's inputs
                | deliveries[{index}][items][{ppe_item_id}][...], so the error is keyed
                | by the last receipt that referenced this item, for the per-item
                | @error() block in that receipt card to pick it up.
                |
                */

                $lastIndex = $deliveries->keys()
                    ->filter(
                        fn (int $index): bool => collect($deliveries[$index]['items'])
                            ->contains(fn (array $entry): bool => (int) $entry['ppe_item_id'] === $itemId)
                    )
                    ->last();

                throw ValidationException::withMessages([
                    "deliveries.{$lastIndex}.items.{$itemId}.quantity" => sprintf(
                        'Only %s unit(s) of "%s" remain to be delivered (planned: %s). This submission requests %s in total.',
                        number_format($remaining),
                        $ppeItem->product,
                        number_format($ppeItem->plannedQuantity()),
                        number_format($totalRequested),
                    ),
                ]);
            }
        }

        DB::transaction(function () use ($request, $project, $deliveries): void {
            foreach ($deliveries as $delivery) {
                $items = collect($delivery['items']);

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

                $record = $project->ppeDeliveries()->create([
                    'delivery_receipt_date' => $delivery['delivery_receipt_date'],
                    'ppe_provided' => $summary,
                    'inventory_reference' => null,
                    'remarks' => $delivery['remarks'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($items as $entry) {
                    $record->items()->create([
                        'ppe_item_id' => (int) $entry['ppe_item_id'],
                        'quantity' => (int) $entry['quantity'],
                    ]);
                }
            }
        });

        $this->refreshPreImplementationStatus(
            $project,
            $request->user()->id
        );

        return back()->with(
            'success',
            $deliveries->count() > 1
                ? "{$deliveries->count()} PPE delivery receipts saved successfully."
                : 'PPE delivery receipt saved successfully.'
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

        $this->ensureAcpStartAfterCheckRelease($project, $validated['start_date']);

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

    /**
     * NAFA (Notice of Availability of Fund) — Through ACP only, recorded with
     * the other implementation preparation requirements.
     */
    public function nafa(Request $request, Project $project): RedirectResponse
    {
        $this->ensurePreparationAllowed($project);

        if (! $this->acpWorkflow->isAcp($project)) {
            abort(403, 'The NAFA applies only to Through ACP projects.');
        }

        $existing = $project->nafa()->with('attachments')->first();

        $validated = $request->validate([
            'nafa_date' => ['required', 'date'],
            'release_date' => ['required', 'date', 'after_or_equal:nafa_date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'attachments' => [
                $existing && $existing->attachments->isNotEmpty() ? 'nullable' : 'required',
                'array',
                'max:10',
            ],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ], [
            'attachments.required' => 'Upload the NAFA file (at least one attachment).',
            'attachments.max' => 'Upload at most 10 NAFA files at a time.',
            'attachments.*.max' => 'Each NAFA file must be 10 MB or smaller.',
            'attachments.*.mimes' => 'NAFA files must be PDF, image (JPG/PNG), Word, or Excel files.',
            'release_date.after_or_equal' => 'The NAFA release date cannot be earlier than the date of NAFA.',
        ], [
            'nafa_date' => 'date of NAFA',
            'release_date' => 'release date',
        ]);

        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $project, $validated, &$storedPaths): void {
                $nafa = $project->nafa()->updateOrCreate(
                    ['project_id' => $project->id],
                    [
                        'nafa_date' => $validated['nafa_date'],
                        'release_date' => $validated['release_date'],
                        'remarks' => $validated['remarks'] ?? null,
                        'recorded_by' => $request->user()->id,
                    ],
                );

                foreach ((array) $request->file('attachments', []) as $file) {
                    $path = $file->store("projects/{$project->id}/nafa", 'local');
                    $storedPaths[] = $path;

                    $nafa->attachments()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'attachment_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $this->refreshPreImplementationStatus($project, $request->user()->id);

        return back()->with('success', 'NAFA saved successfully.');
    }

    public function downloadNafaAttachment(Project $project, ProjectNafaAttachment $attachment): StreamedResponse
    {
        if ((int) $attachment->nafa?->project_id !== (int) $project->id) {
            abort(404);
        }

        if (blank($attachment->attachment_path) || ! Storage::disk('local')->exists($attachment->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->attachment_path, $attachment->original_name);
    }

    /**
     * Direct Administration and Through ACP share these implementation steps.
     * Through ACP reaches them after the check release (For Implementation).
     */
    private function ensureSupportedMode(Project $project): void
    {
        if (! in_array($project->implementation_mode, [
            ImplementationMode::DIRECT_ADMINISTRATION,
            ImplementationMode::THROUGH_ACP,
        ], true)) {
            abort(403, 'Project Implementation records are not available for this project.');
        }
    }

    private function ensurePreparationAllowed(Project $project): void
    {
        $this->ensureSupportedMode($project);

        $allowed = $this->acpWorkflow->isAcp($project)
            ? [ProjectStatus::FOR_IMPLEMENTATION]
            : [ProjectStatus::APPROVED, ProjectStatus::FOR_IMPLEMENTATION];

        if (! in_array($project->status, $allowed, true)) {
            abort(
                403,
                $this->acpWorkflow->isAcp($project)
                    ? 'Through ACP Insurance, PPE, NAFA, and Notice to Proceed can only be recorded after the check release (For Implementation).'
                    : 'Insurance, PPE, and Notice to Proceed can only be modified for Approved or For Implementation projects.'
            );
        }
    }

    private function ensureSchedulingAllowed(Project $project): void
    {
        $this->ensureSupportedMode($project);

        if ($project->status !== ProjectStatus::FOR_IMPLEMENTATION) {
            abort(
                403,
                'Orientation and the Implementation Work Period can only be recorded after the project reaches For Implementation.'
            );
        }

        // Direct Administration only reaches For Implementation after its
        // preparation is complete; Through ACP gets there at check release,
        // so the same preparation requirement is checked here.
        if ($this->acpWorkflow->isAcp($project) && ! $this->acpWorkflow->preparationComplete($project)) {
            throw ValidationException::withMessages([
                'implementation' => 'Complete the preparation requirements first: '
                    .implode(', ', $this->acpWorkflow->missingPreparation($project)).'.',
            ]);
        }
    }

    /** Through ACP work period cannot start before the check was released to the proponent. */
    private function ensureAcpStartAfterCheckRelease(Project $project, string $startDate): void
    {
        if (! $this->acpWorkflow->isAcp($project)) {
            return;
        }

        $project->loadMissing('acpCheckRelease');
        $released = $project->acpCheckRelease?->released_date;

        if ($released && Carbon::parse($startDate)->startOfDay()->lt($released->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'start_date' => 'The implementation start date cannot be earlier than the date the check was released to the proponent.',
            ]);
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
