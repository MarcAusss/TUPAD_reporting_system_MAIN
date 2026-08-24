<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectTerm;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\ProjectDraft;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectDraftController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GIP Draft List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        $drafts = ProjectDraft::query()
            ->where(
                'encoded_by',
                $request->user()->id
            )
            ->with([
                'allocation.adl',
                'assignedTc',
                'confirmedProject',
                'provinceReference',
                'municipalityReference',
                'barangayReference',
            ])
            ->latest('updated_at')
            ->paginate(15);

        return view(
            'project-drafts.index',
            compact('drafts')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create GIP Draft
    |--------------------------------------------------------------------------
    */

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

        $provinces = Province::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'project-drafts.create',
            [
                'allocations' => $allocations,
                'provinces' => $provinces,
                'implementationModes' => ImplementationMode::cases(),
                'ppeTypes' => PpeType::cases(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store GIP Draft
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->supervisor_tc_id) {
            abort(
                403,
                'Your GIP account is not assigned to a TUPAD Coordinator.'
            );
        }

        $validated = $this->validateDraft(
            $request
        );

        return DB::transaction(function () use ($request, $validated) {
            /*
            |--------------------------------------------------------------------------
            | Compute Project Values
            |--------------------------------------------------------------------------
            */

            $computed = $this->computeDraftValues(
                $validated
            );

            /*
            |--------------------------------------------------------------------------
            | Resolve Geographic Hierarchy
            |--------------------------------------------------------------------------
            */

            $location = $this->resolveLocation(
                $validated
            );

            $province = $location['province'];
            $municipality = $location['municipality'];
            $barangay = $location['barangay'];

            /*
            |--------------------------------------------------------------------------
            | Create Draft
            |--------------------------------------------------------------------------
            */

            $draft = ProjectDraft::create([
                /*
                |--------------------------------------------------------------------------
                | Ownership / Assignment
                |--------------------------------------------------------------------------
                */

                'encoded_by' =>
                    $request->user()->id,

                'assigned_tc_id' =>
                    $request->user()->supervisor_tc_id,

                /*
                |--------------------------------------------------------------------------
                | Allocation
                |--------------------------------------------------------------------------
                */

                'adl_allocation_id' =>
                    $validated['adl_allocation_id'],

                /*
                |--------------------------------------------------------------------------
                | General Information
                |--------------------------------------------------------------------------
                */

                'date_received' =>
                    $validated['date_received'],

                'project_title' =>
                    trim(
                        $validated['project_title']
                    ),

                'nature_of_work' =>
                    trim(
                        $validated['nature_of_work']
                    ),

                /*
                |--------------------------------------------------------------------------
                | Geographic References
                |--------------------------------------------------------------------------
                */

                'province_id' =>
                    $province->id,

                'municipality_id' =>
                    $municipality->id,

                'barangay_id' =>
                    $barangay->id,

                /*
                |--------------------------------------------------------------------------
                | Geographic Snapshot
                |--------------------------------------------------------------------------
                */

                'province' =>
                    $province->name,

                'district' =>
                    $municipality->district
                    ?? 'Not Assigned',

                'municipality' =>
                    $municipality->name,

                'barangay' =>
                    $barangay->name,

                'income_class' =>
                    $municipality->income_class,

                /*
                |--------------------------------------------------------------------------
                | Implementation
                |--------------------------------------------------------------------------
                */

                'implementation_mode' =>
                    $validated['implementation_mode'],

                'number_of_days' =>
                    $computed['number_of_days'],

                'term' =>
                    $computed['term'],

                /*
                |--------------------------------------------------------------------------
                | Beneficiaries
                |--------------------------------------------------------------------------
                */

                'beneficiaries_total' =>
                    $computed['beneficiaries_total'],

                'beneficiaries_female' =>
                    $computed['beneficiaries_female'],

                /*
                |--------------------------------------------------------------------------
                | Wage
                |--------------------------------------------------------------------------
                */

                'wage_rate' =>
                    $computed['wage_rate'],

                'wages_total' =>
                    $computed['wages_total'],

                /*
                |--------------------------------------------------------------------------
                | PPE
                |--------------------------------------------------------------------------
                */

                'ppe_total' =>
                    $computed['ppe_total'],

                /*
                |--------------------------------------------------------------------------
                | Insurance
                |--------------------------------------------------------------------------
                */

                'insurance_rate' =>
                    $computed['insurance_rate'],

                'insurance_total' =>
                    $computed['insurance_total'],

                /*
                |--------------------------------------------------------------------------
                | Total
                |--------------------------------------------------------------------------
                */

                'total_project_cost' =>
                    $computed['total_project_cost'],

                /*
                |--------------------------------------------------------------------------
                | Draft Workflow
                |--------------------------------------------------------------------------
                */

                'status' =>
                    ProjectDraftStatus::DRAFT,

                'remarks' =>
                    $validated['remarks'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Draft PPE Items
            |--------------------------------------------------------------------------
            */

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

    /*
    |--------------------------------------------------------------------------
    | Show GIP Draft
    |--------------------------------------------------------------------------
    */

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

            'provinceReference',
            'municipalityReference',
            'barangayReference',
        ]);

        return view(
            'project-drafts.show',
            [
                'draft' => $projectDraft,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit GIP Draft
    |--------------------------------------------------------------------------
    */

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

        $projectDraft->load([
            'ppeItems',
            'provinceReference',
            'municipalityReference',
            'barangayReference',
        ]);

        $allocations = AdlAllocation::query()
            ->with('adl')
            ->orderByDesc('id')
            ->get();

        $provinces = Province::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'project-drafts.edit',
            [
                'draft' => $projectDraft,
                'allocations' => $allocations,
                'provinces' => $provinces,
                'implementationModes' => ImplementationMode::cases(),
                'ppeTypes' => PpeType::cases(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update GIP Draft
    |--------------------------------------------------------------------------
    */

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

        $validated = $this->validateDraft(
            $request
        );

        return DB::transaction(function () use ($validated, $projectDraft) {
            /*
            |--------------------------------------------------------------------------
            | Recompute Values
            |--------------------------------------------------------------------------
            */

            $computed = $this->computeDraftValues(
                $validated
            );

            /*
            |--------------------------------------------------------------------------
            | Resolve Location
            |--------------------------------------------------------------------------
            */

            $location = $this->resolveLocation(
                $validated
            );

            $province = $location['province'];
            $municipality = $location['municipality'];
            $barangay = $location['barangay'];

            /*
            |--------------------------------------------------------------------------
            | Update Draft
            |--------------------------------------------------------------------------
            */

            $projectDraft->update([
                /*
                |--------------------------------------------------------------------------
                | Allocation
                |--------------------------------------------------------------------------
                */

                'adl_allocation_id' =>
                    $validated['adl_allocation_id'],

                /*
                |--------------------------------------------------------------------------
                | General
                |--------------------------------------------------------------------------
                */

                'date_received' =>
                    $validated['date_received'],

                'project_title' =>
                    trim(
                        $validated['project_title']
                    ),

                'nature_of_work' =>
                    trim(
                        $validated['nature_of_work']
                    ),

                /*
                |--------------------------------------------------------------------------
                | Geographic References
                |--------------------------------------------------------------------------
                */

                'province_id' =>
                    $province->id,

                'municipality_id' =>
                    $municipality->id,

                'barangay_id' =>
                    $barangay->id,

                /*
                |--------------------------------------------------------------------------
                | Geographic Snapshot
                |--------------------------------------------------------------------------
                */

                'province' =>
                    $province->name,

                'district' =>
                    $municipality->district
                    ?? 'Not Assigned',

                'municipality' =>
                    $municipality->name,

                'barangay' =>
                    $barangay->name,

                'income_class' =>
                    $municipality->income_class,

                /*
                |--------------------------------------------------------------------------
                | Implementation
                |--------------------------------------------------------------------------
                */

                'implementation_mode' =>
                    $validated['implementation_mode'],

                'number_of_days' =>
                    $computed['number_of_days'],

                'term' =>
                    $computed['term'],

                /*
                |--------------------------------------------------------------------------
                | Beneficiaries
                |--------------------------------------------------------------------------
                */

                'beneficiaries_total' =>
                    $computed['beneficiaries_total'],

                'beneficiaries_female' =>
                    $computed['beneficiaries_female'],

                /*
                |--------------------------------------------------------------------------
                | Wage
                |--------------------------------------------------------------------------
                */

                'wage_rate' =>
                    $computed['wage_rate'],

                'wages_total' =>
                    $computed['wages_total'],

                /*
                |--------------------------------------------------------------------------
                | PPE
                |--------------------------------------------------------------------------
                */

                'ppe_total' =>
                    $computed['ppe_total'],

                /*
                |--------------------------------------------------------------------------
                | Insurance
                |--------------------------------------------------------------------------
                */

                'insurance_rate' =>
                    $computed['insurance_rate'],

                'insurance_total' =>
                    $computed['insurance_total'],

                /*
                |--------------------------------------------------------------------------
                | Overall Project Cost
                |--------------------------------------------------------------------------
                */

                'total_project_cost' =>
                    $computed['total_project_cost'],

                /*
                |--------------------------------------------------------------------------
                | Remarks
                |--------------------------------------------------------------------------
                */

                'remarks' =>
                    $validated['remarks'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Clear Previous TC Review State
                |--------------------------------------------------------------------------
                |
                | When a returned draft is corrected, the previous return message
                | must not remain as the active review state.
                |
                */

                'tc_review_remarks' =>
                    null,

                'reviewed_at' =>
                    null,

                'reviewed_by' =>
                    null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Replace PPE Items
            |--------------------------------------------------------------------------
            */

            $projectDraft
                ->ppeItems()
                ->delete();

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

    /*
    |--------------------------------------------------------------------------
    | Submit Draft to TC
    |--------------------------------------------------------------------------
    */

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

        /*
        |--------------------------------------------------------------------------
        | Verify TC Assignment Still Exists
        |--------------------------------------------------------------------------
        */

        if (!$projectDraft->assigned_tc_id) {
            abort(
                403,
                'This draft does not have an assigned TUPAD Coordinator.'
            );
        }

        $projectDraft->update([
            'status' =>
                ProjectDraftStatus::PENDING_TC_REVIEW,

            'submitted_at' =>
                now(),

            'tc_review_remarks' =>
                null,

            'reviewed_at' =>
                null,

            'reviewed_by' =>
                null,
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

    /*
    |--------------------------------------------------------------------------
    | Ensure GIP Owns Draft
    |--------------------------------------------------------------------------
    */

    private function ensureOwner(
        Request $request,
        ProjectDraft $draft
    ): void {
        if (
            $draft->encoded_by
            !== $request->user()->id
        ) {
            abort(403);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Draft Validation
    |--------------------------------------------------------------------------
    */

    private function validateDraft(
        Request $request
    ): array {
        return $request->validate([
            /*
            |--------------------------------------------------------------------------
            | Allocation
            |--------------------------------------------------------------------------
            */

            'adl_allocation_id' => [
                'required',
                'integer',
                'exists:adl_allocations,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Geographic References
            |--------------------------------------------------------------------------
            */

            'province_id' => [
                'required',
                'integer',
                'exists:provinces,id',
            ],

            'municipality_id' => [
                'required',
                'integer',
                'exists:municipalities,id',
            ],

            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Implementation
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Beneficiaries
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Wage / Insurance
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | PPE
            |--------------------------------------------------------------------------
            */

            'ppe_items' => [
                'nullable',
                'array',
            ],

            'ppe_items.*.ppe_type' => [
                'required_with:ppe_items.*.product',
                Rule::enum(
                    PpeType::class
                ),
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

            /*
            |--------------------------------------------------------------------------
            | Remarks
            |--------------------------------------------------------------------------
            */

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Geographic Hierarchy
    |--------------------------------------------------------------------------
    |
    | Province
    |   ↓
    | Municipality
    |   ↓
    | Barangay
    |
    | This prevents a manipulated request from pairing records that do not
    | actually belong together.
    |
    */

    private function resolveLocation(
        array $validated
    ): array {
        $province = Province::query()
            ->where(
                'is_active',
                true
            )
            ->findOrFail(
                $validated['province_id']
            );

        $municipality = Municipality::query()
            ->where(
                'province_id',
                $province->id
            )
            ->where(
                'is_active',
                true
            )
            ->findOrFail(
                $validated['municipality_id']
            );

        $barangay = Barangay::query()
            ->where(
                'municipality_id',
                $municipality->id
            )
            ->where(
                'is_active',
                true
            )
            ->findOrFail(
                $validated['barangay_id']
            );

        return [
            'province' =>
                $province,

            'municipality' =>
                $municipality,

            'barangay' =>
                $barangay,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Compute Draft Values
    |--------------------------------------------------------------------------
    */

    private function computeDraftValues(
        array $validated
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Duration / Term
        |--------------------------------------------------------------------------
        */

        $days =
            (int) $validated['number_of_days'];

        $term =
            ProjectTerm::fromDays(
                $days
            );

        /*
        |--------------------------------------------------------------------------
        | Beneficiaries
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Wage
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Insurance
        |--------------------------------------------------------------------------
        */

        $insuranceRate = round(
            (float) $validated['insurance_rate'],
            2
        );

        $insurance = round(
            $insuranceRate
            * $beneficiaries,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | PPE
        |--------------------------------------------------------------------------
        */

        $ppeItems = $this->preparePpeItems(
            $validated['ppe_items'] ?? [],
            $beneficiaries
        );

        $ppeTotal = round(
            collect($ppeItems)
                ->sum('total_amount'),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Return Computed Values
        |--------------------------------------------------------------------------
        */

        return [
            'number_of_days' =>
                $days,

            'term' =>
                $term,

            'beneficiaries_total' =>
                $beneficiaries,

            'beneficiaries_female' =>
                $female,

            'wage_rate' =>
                $wageRate,

            'wages_total' =>
                $wages,

            'insurance_rate' =>
                $insuranceRate,

            'insurance_total' =>
                $insurance,

            'ppe_total' =>
                $ppeTotal,

            'ppe_items' =>
                $ppeItems,

            'total_project_cost' =>
                round(
                    $wages
                    + $insurance
                    + $ppeTotal,
                    2
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare PPE Items
    |--------------------------------------------------------------------------
    */

    private function preparePpeItems(
        array $items,
        int $projectBeneficiaries
    ): array {
        $prepared = [];

        foreach (
            $items as $index => $item
        ) {
            /*
            |--------------------------------------------------------------------------
            | Ignore Completely Empty Rows
            |--------------------------------------------------------------------------
            */

            if (
                blank(
                    $item['product']
                    ?? null
                )
                && blank(
                    $item['beneficiary_count']
                    ?? null
                )
                && blank(
                    $item['unit_amount']
                    ?? null
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | PPE Type
            |--------------------------------------------------------------------------
            */

            if (
                blank(
                    $item['ppe_type']
                    ?? null
                )
            ) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.ppe_type" =>
                        'Select a PPE type.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | PPE Product
            |--------------------------------------------------------------------------
            */

            if (
                blank(
                    $item['product']
                    ?? null
                )
            ) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.product" =>
                        'Enter the PPE product name.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Beneficiary Count
            |--------------------------------------------------------------------------
            */

            $beneficiaryCount = (int) (
                $item['beneficiary_count']
                ?? 0
            );

            if ($beneficiaryCount < 1) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.beneficiary_count" =>
                        'PPE beneficiary count must be at least 1.',
                ]);
            }

            if (
                $beneficiaryCount
                > $projectBeneficiaries
            ) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.beneficiary_count" =>
                        'A PPE beneficiary count cannot exceed the total project beneficiaries.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Unit Amount
            |--------------------------------------------------------------------------
            */

            $unitAmount = round(
                (float) (
                    $item['unit_amount']
                    ?? 0
                ),
                2
            );

            if ($unitAmount < 0) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.unit_amount" =>
                        'PPE unit amount cannot be negative.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Prepared Item
            |--------------------------------------------------------------------------
            */

            $prepared[] = [
                'ppe_type' =>
                    $item['ppe_type'],

                'product' =>
                    trim(
                        $item['product']
                    ),

                'beneficiary_count' =>
                    $beneficiaryCount,

                'unit_amount' =>
                    $unitAmount,

                'total_amount' =>
                    round(
                        $beneficiaryCount
                        * $unitAmount,
                        2
                    ),
            ];
        }

        return $prepared;
    }
}