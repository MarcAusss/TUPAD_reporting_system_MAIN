<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectTerm;
use App\Models\AdlAllocation;
use App\Models\ProjectDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectDraftController extends Controller
{
    public function index(Request $request): View
    {
        $drafts = ProjectDraft::query()
            ->where('encoded_by', $request->user()->id)
            ->with([
                'allocation.adl',
                'assignedTc',
                'confirmedProject',
            ])
            ->latest('updated_at')
            ->paginate(15);

        return view(
            'project-drafts.index',
            compact('drafts')
        );
    }

    public function create(Request $request): View
    {
        if (!$request->user()->supervisor_tc_id) {
            abort(
                403,
                'Your GIP account is not assigned to a TUPAD Coordinator.'
            );
        }

        $allocations = AdlAllocation::query()
            ->with('adl')
            ->orderByDesc('id')
            ->get();

        return view(
            'project-drafts.create',
            [
                'allocations' => $allocations,
                'implementationModes' => ImplementationMode::cases(),
                'ppeTypes' => PpeType::cases(),
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->supervisor_tc_id) {
            abort(
                403,
                'Your GIP account is not assigned to a TUPAD Coordinator.'
            );
        }

        $validated = $this->validateDraft($request);

        return DB::transaction(function () use ($request, $validated) {
            $computed = $this->computeDraftValues(
                $validated
            );

            $draft = ProjectDraft::create([
                'encoded_by' => $request->user()->id,

                'assigned_tc_id' =>
                    $request->user()->supervisor_tc_id,

                'adl_allocation_id' =>
                    $validated['adl_allocation_id'],

                'date_received' =>
                    $validated['date_received'],

                'project_title' =>
                    trim($validated['project_title']),

                'nature_of_work' =>
                    trim($validated['nature_of_work']),

                'province' =>
                    trim($validated['province']),

                'district' =>
                    trim($validated['district']),

                'municipality' =>
                    trim($validated['municipality']),

                'barangay' =>
                    trim($validated['barangay']),

                'income_class' =>
                    filled($validated['income_class'] ?? null)
                    ? trim($validated['income_class'])
                    : null,

                'implementation_mode' =>
                    $validated['implementation_mode'],

                'number_of_days' =>
                    $computed['number_of_days'],

                'term' =>
                    $computed['term'],

                'beneficiaries_total' =>
                    $computed['beneficiaries_total'],

                'beneficiaries_female' =>
                    $computed['beneficiaries_female'],

                'wage_rate' =>
                    $computed['wage_rate'],

                'wages_total' =>
                    $computed['wages_total'],

                'ppe_total' =>
                    $computed['ppe_total'],

                'insurance_rate' =>
                    $computed['insurance_rate'],

                'insurance_total' =>
                    $computed['insurance_total'],

                'total_project_cost' =>
                    $computed['total_project_cost'],

                'status' =>
                    ProjectDraftStatus::DRAFT,

                'remarks' =>
                    $validated['remarks'] ?? null,
            ]);

            if (!empty($computed['ppe_items'])) {
                $draft
                    ->ppeItems()
                    ->createMany(
                        $computed['ppe_items']
                    );
            }

            return redirect()
                ->route(
                    'project-drafts.show',
                    $draft
                )
                ->with(
                    'success',
                    'Project draft saved successfully.'
                );
        });
    }

    public function show(
        Request $request,
        ProjectDraft $projectDraft
    ): View {
        $this->ensureOwner(
            $request,
            $projectDraft
        );

        $projectDraft->load([
            'allocation.adl',
            'ppeItems',
            'assignedTc',
            'reviewer',
            'confirmedProject',
        ]);

        return view(
            'project-drafts.show',
            [
                'draft' => $projectDraft,
            ]
        );
    }

    public function edit(
        Request $request,
        ProjectDraft $projectDraft
    ): View {
        $this->ensureOwner(
            $request,
            $projectDraft
        );

        if (!$projectDraft->canBeEdited()) {
            abort(
                403,
                'This draft cannot currently be edited.'
            );
        }

        $projectDraft->load('ppeItems');

        $allocations = AdlAllocation::query()
            ->with('adl')
            ->orderByDesc('id')
            ->get();

        return view(
            'project-drafts.edit',
            [
                'draft' => $projectDraft,
                'allocations' => $allocations,
                'implementationModes' => ImplementationMode::cases(),
                'ppeTypes' => PpeType::cases(),
            ]
        );
    }

    public function update(
        Request $request,
        ProjectDraft $projectDraft
    ): RedirectResponse {
        $this->ensureOwner(
            $request,
            $projectDraft
        );

        if (!$projectDraft->canBeEdited()) {
            abort(
                403,
                'This draft cannot currently be edited.'
            );
        }

        $validated = $this->validateDraft($request);

        return DB::transaction(function () use ($validated, $projectDraft) {
            $computed = $this->computeDraftValues(
                $validated
            );

            $projectDraft->update([
                'adl_allocation_id' =>
                    $validated['adl_allocation_id'],

                'date_received' =>
                    $validated['date_received'],

                'project_title' =>
                    trim($validated['project_title']),

                'nature_of_work' =>
                    trim($validated['nature_of_work']),

                'province' =>
                    trim($validated['province']),

                'district' =>
                    trim($validated['district']),

                'municipality' =>
                    trim($validated['municipality']),

                'barangay' =>
                    trim($validated['barangay']),

                'income_class' =>
                    filled($validated['income_class'] ?? null)
                    ? trim($validated['income_class'])
                    : null,

                'implementation_mode' =>
                    $validated['implementation_mode'],

                'number_of_days' =>
                    $computed['number_of_days'],

                'term' =>
                    $computed['term'],

                'beneficiaries_total' =>
                    $computed['beneficiaries_total'],

                'beneficiaries_female' =>
                    $computed['beneficiaries_female'],

                'wage_rate' =>
                    $computed['wage_rate'],

                'wages_total' =>
                    $computed['wages_total'],

                'ppe_total' =>
                    $computed['ppe_total'],

                'insurance_rate' =>
                    $computed['insurance_rate'],

                'insurance_total' =>
                    $computed['insurance_total'],

                'total_project_cost' =>
                    $computed['total_project_cost'],

                'remarks' =>
                    $validated['remarks'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Clear previous TC return message after correction
                |--------------------------------------------------------------------------
                */

                'tc_review_remarks' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);

            $projectDraft->ppeItems()->delete();

            if (!empty($computed['ppe_items'])) {
                $projectDraft
                    ->ppeItems()
                    ->createMany(
                        $computed['ppe_items']
                    );
            }

            return redirect()
                ->route(
                    'project-drafts.show',
                    $projectDraft
                )
                ->with(
                    'success',
                    'Project draft updated successfully.'
                );
        });
    }

    public function submit(
        Request $request,
        ProjectDraft $projectDraft
    ): RedirectResponse {
        $this->ensureOwner(
            $request,
            $projectDraft
        );

        if (!$projectDraft->canBeSubmitted()) {
            abort(
                403,
                'This draft cannot currently be submitted.'
            );
        }

        $projectDraft->update([
            'status' =>
                ProjectDraftStatus::PENDING_TC_REVIEW,

            'submitted_at' => now(),

            'tc_review_remarks' => null,

            'reviewed_at' => null,

            'reviewed_by' => null,
        ]);

        return redirect()
            ->route(
                'project-drafts.show',
                $projectDraft
            )
            ->with(
                'success',
                'Project draft submitted to your TUPAD Coordinator for review.'
            );
    }

    private function ensureOwner(
        Request $request,
        ProjectDraft $draft
    ): void {
        if ($draft->encoded_by !== $request->user()->id) {
            abort(403);
        }
    }

    private function validateDraft(
        Request $request
    ): array {
        return $request->validate([
            'adl_allocation_id' => [
                'required',
                'integer',
                'exists:adl_allocations,id',
            ],

            'date_received' => [
                'required',
                'date',
            ],

            'project_title' => [
                'required',
                'string',
                'max:255',
            ],

            'nature_of_work' => [
                'required',
                'string',
                'max:3000',
            ],

            'province' => [
                'required',
                'string',
                'max:150',
            ],

            'district' => [
                'required',
                'string',
                'max:100',
            ],

            'municipality' => [
                'required',
                'string',
                'max:150',
            ],

            'barangay' => [
                'required',
                'string',
                'max:150',
            ],

            'income_class' => [
                'nullable',
                'string',
                'max:50',
            ],

            'implementation_mode' => [
                'required',
                Rule::enum(
                    ImplementationMode::class
                ),
            ],

            'number_of_days' => [
                'required',
                'integer',
                'min:10',
                'max:90',
            ],

            'beneficiaries_total' => [
                'required',
                'integer',
                'min:1',
            ],

            'beneficiaries_female' => [
                'required',
                'integer',
                'min:0',
            ],

            'wage_rate' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'insurance_rate' => [
                'required',
                'numeric',
                'min:0',
            ],

            'ppe_items' => [
                'nullable',
                'array',
            ],

            'ppe_items.*.ppe_type' => [
                'required_with:ppe_items.*.product',
                Rule::enum(PpeType::class),
            ],

            'ppe_items.*.product' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ppe_items.*.beneficiary_count' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'ppe_items.*.unit_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
    }

    private function computeDraftValues(
        array $validated
    ): array {
        $days = (int) $validated['number_of_days'];

        $term = ProjectTerm::fromDays($days);

        $beneficiaries =
            (int) $validated['beneficiaries_total'];

        $female =
            (int) $validated['beneficiaries_female'];

        if ($female > $beneficiaries) {
            throw ValidationException::withMessages([
                'beneficiaries_female' =>
                    'Female beneficiaries cannot exceed the total number of beneficiaries.',
            ]);
        }

        $wageRate = round(
            (float) $validated['wage_rate'],
            2
        );

        $wages = round(
            $wageRate
            * $beneficiaries
            * $days,
            2
        );

        $insuranceRate = round(
            (float) $validated['insurance_rate'],
            2
        );

        $insurance = round(
            $insuranceRate
            * $beneficiaries,
            2
        );

        $ppeItems = $this->preparePpeItems(
            $validated['ppe_items'] ?? [],
            $beneficiaries
        );

        $ppeTotal = round(
            collect($ppeItems)
                ->sum('total_amount'),
            2
        );

        return [
            'number_of_days' => $days,
            'term' => $term,

            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => $female,

            'wage_rate' => $wageRate,
            'wages_total' => $wages,

            'insurance_rate' => $insuranceRate,
            'insurance_total' => $insurance,

            'ppe_total' => $ppeTotal,
            'ppe_items' => $ppeItems,

            'total_project_cost' => round(
                $wages
                + $insurance
                + $ppeTotal,
                2
            ),
        ];
    }

    private function preparePpeItems(
        array $items,
        int $projectBeneficiaries
    ): array {
        $prepared = [];

        foreach ($items as $item) {
            if (
                blank($item['product'] ?? null)
                && blank($item['beneficiary_count'] ?? null)
                && blank($item['unit_amount'] ?? null)
            ) {
                continue;
            }

            $count = (int) (
                $item['beneficiary_count'] ?? 0
            );

            if ($count > $projectBeneficiaries) {
                throw ValidationException::withMessages([
                    'ppe_items' =>
                        'A PPE beneficiary count cannot exceed the total project beneficiaries.',
                ]);
            }

            $unitAmount = round(
                (float) ($item['unit_amount'] ?? 0),
                2
            );

            $prepared[] = [
                'ppe_type' =>
                    $item['ppe_type'],

                'product' =>
                    trim($item['product']),

                'beneficiary_count' =>
                    $count,

                'unit_amount' =>
                    $unitAmount,

                'total_amount' =>
                    round(
                        $count * $unitAmount,
                        2
                    ),
            ];
        }

        return $prepared;
    }
}