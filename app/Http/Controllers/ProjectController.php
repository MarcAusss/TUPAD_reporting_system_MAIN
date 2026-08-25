<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Official Project List
    |--------------------------------------------------------------------------
    */

    public function index(): View
    {
        $projects = Project::query()
            ->with([
                'allocation.adl',
                'creator',
                'provinceReference',
                'municipalityReference',
                'barangayReference',
            ])
            ->latest('date_received')
            ->latest('id')
            ->paginate(15);

        return view(
            'projects.index',
            compact('projects')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Queue
    |--------------------------------------------------------------------------
    */

    public function paymentQueue(): View
    {
        $projects = Project::query()
            ->whereIn('status', [
                ProjectStatus::FOR_PAYMENT->value,
                ProjectStatus::COMPLETED->value,
            ])
            ->with([
                'allocation.adl',
                'obligation',
                'payout',
            ])
            ->latest('updated_at')
            ->paginate(15);

        return view(
            'payments.index',
            compact('projects')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Official Project
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $allocations = AdlAllocation::query()
            ->with('adl')
            ->orderByDesc('id')
            ->get();

        $provinces = Province::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'projects.create',
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
    | Store Official Project
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);

        return DB::transaction(function () use (
            $request,
            $validated
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock Allocation
            |--------------------------------------------------------------------------
            */

            $allocation = AdlAllocation::query()
                ->lockForUpdate()
                ->findOrFail(
                    $validated['adl_allocation_id']
                );

            /*
            |--------------------------------------------------------------------------
            | Project Duration
            |--------------------------------------------------------------------------
            */

            $numberOfDays = (int) $validated['number_of_days'];

            $term = ProjectTerm::fromDays(
                $numberOfDays
            );

            /*
            |--------------------------------------------------------------------------
            | Beneficiaries
            |--------------------------------------------------------------------------
            */

            $beneficiaries =
                (int) $validated['beneficiaries_total'];

            $femaleBeneficiaries =
                (int) $validated['beneficiaries_female'];

            if ($femaleBeneficiaries > $beneficiaries) {
                throw ValidationException::withMessages([
                    'beneficiaries_female' =>
                        'Female beneficiaries cannot exceed the total number of beneficiaries.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Geographic Hierarchy
            |--------------------------------------------------------------------------
            |
            | Province
            |   ↓
            | Municipality
            |   ↓
            | Barangay
            |
            | This prevents users from manually submitting mismatched IDs.
            |
            */

            $province = Province::query()
                ->where('is_active', true)
                ->findOrFail(
                    $validated['province_id']
                );

            $municipality = Municipality::query()
                ->where(
                    'province_id',
                    $province->id
                )
                ->where('is_active', true)
                ->findOrFail(
                    $validated['municipality_id']
                );

            $barangay = Barangay::query()
                ->where(
                    'municipality_id',
                    $municipality->id
                )
                ->where('is_active', true)
                ->findOrFail(
                    $validated['barangay_id']
                );

            /*
            |--------------------------------------------------------------------------
            | Wage Computation
            |--------------------------------------------------------------------------
            */

            $wageRate = round(
                (float) $validated['wage_rate'],
                2
            );

            $wagesTotal = round(
                $wageRate
                * $beneficiaries
                * $numberOfDays,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Insurance Computation
            |--------------------------------------------------------------------------
            */

            $insuranceRate = round(
                (float) $validated['insurance_rate'],
                2
            );

            $insuranceTotal = round(
                $insuranceRate
                * $beneficiaries,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | PPE Computation
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
            | Total Project Cost
            |--------------------------------------------------------------------------
            */

            $totalProjectCost = round(
                $wagesTotal
                + $ppeTotal
                + $insuranceTotal,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Allocation Budget Validation
            |--------------------------------------------------------------------------
            */

            $existingProjectCost = (float) $allocation
                ->projects()
                ->sum('total_project_cost');

            $availableProjectBudget = round(
                (float) $allocation->amount
                - $existingProjectCost,
                2
            );

            if ($totalProjectCost > $availableProjectBudget) {
                throw ValidationException::withMessages([
                    'adl_allocation_id' => sprintf(
                        'The project cost of ₱%s exceeds the remaining allocation budget of ₱%s.',
                        number_format(
                            $totalProjectCost,
                            2
                        ),
                        number_format(
                            $availableProjectBudget,
                            2
                        ),
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Create Official Project
            |--------------------------------------------------------------------------
            */

            $project = Project::create([
                'adl_allocation_id' =>
                    $allocation->id,

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
                | Funding Ownership
                |--------------------------------------------------------------------------
                |
                | Sponsor and Partner belong to the official project and are
                | encoded by the TUPAD Coordinator.
                |
                */

                'fund_sponsor' =>
                    trim(
                        $validated['fund_sponsor']
                    ),

                'partner' =>
                    trim(
                        $validated['partner']
                    ),

                /*
                |--------------------------------------------------------------------------
                | Project Series / TEVS Verification
                |--------------------------------------------------------------------------
                */

                'project_series' =>
                    trim(
                        $validated['project_series']
                    ),

                'project_series_remarks' =>
                    filled($validated['project_series_remarks'] ?? null)
                        ? trim($validated['project_series_remarks'])
                        : null,

                'tevs_date_verified' =>
                    $validated['tevs_date_verified'],

                'tevs_remarks' =>
                    filled($validated['tevs_remarks'] ?? null)
                        ? trim($validated['tevs_remarks'])
                        : null,

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
                | Geographic Text Snapshot
                |--------------------------------------------------------------------------
                |
                | We retain these fields for historical compatibility and reporting.
                |
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
                    $numberOfDays,

                'term' =>
                    $term,

                /*
                |--------------------------------------------------------------------------
                | Beneficiaries
                |--------------------------------------------------------------------------
                */

                'beneficiaries_total' =>
                    $beneficiaries,

                'beneficiaries_female' =>
                    $femaleBeneficiaries,

                /*
                |--------------------------------------------------------------------------
                | Wage
                |--------------------------------------------------------------------------
                */

                'wage_rate' =>
                    $wageRate,

                'wages_total' =>
                    $wagesTotal,

                /*
                |--------------------------------------------------------------------------
                | PPE
                |--------------------------------------------------------------------------
                */

                'ppe_total' =>
                    $ppeTotal,

                /*
                |--------------------------------------------------------------------------
                | Insurance
                |--------------------------------------------------------------------------
                */

                'insurance_rate' =>
                    $insuranceRate,

                'insurance_total' =>
                    $insuranceTotal,

                /*
                |--------------------------------------------------------------------------
                | Overall Cost
                |--------------------------------------------------------------------------
                */

                'total_project_cost' =>
                    $totalProjectCost,

                /*
                |--------------------------------------------------------------------------
                | Workflow
                |--------------------------------------------------------------------------
                */

                'status' =>
                    ProjectStatus::ONGOING_PROFILING,

                'remarks' =>
                    $validated['remarks'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Audit
                |--------------------------------------------------------------------------
                */

                'created_by' =>
                    $request->user()->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create PPE Items
            |--------------------------------------------------------------------------
            */

            if (! empty($ppeItems)) {
                $project
                    ->ppeItems()
                    ->createMany(
                        $ppeItems
                    );
            }

            return redirect()
                ->route(
                    'projects.show',
                    $project
                )
                ->with(
                    'success',
                    'Project profile created successfully.'
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Show Official Project
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        Project $project
    ): View {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Project Viewing Authorization
        |--------------------------------------------------------------------------
        */

        if ($user->isGip()) {
            abort(403);
        }

        if (
            $user->isFocal()
            && ! in_array(
                $project->status,
                [
                    ProjectStatus::FOR_PAYMENT,
                    ProjectStatus::COMPLETED,
                ],
                true
            )
        ) {
            abort(403);
        }

        $project->load([
            'allocation.adl',

            'provinceReference',
            'municipalityReference',
            'barangayReference',

            'ppeItems',

            'creator',
            'updater',

            'evaluations.evaluator',
            'approval.approver',

            'insuranceEnrollment.recorder',
            'ppeDelivery.recorder',
            'noticeToProceed.recorder',
            'orientation.recorder',
            'implementation.recorder',

            'postDocuments.recorder',
            'obligation.recorder',
            'payout.recorder',


            'statusHistory.changer',
        ]);

        return view(
            'projects.show',
            compact('project')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Project
    |--------------------------------------------------------------------------
    */

    private function validateProject(
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

            'fund_sponsor' => [
                'required',
                'string',
                'max:255',
            ],

            'partner' => [
                'required',
                'string',
                'max:255',
            ],

            'project_series' => [
                'required',
                'string',
                'max:100',
            ],

            'project_series_remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'tevs_date_verified' => [
                'required',
                'date',
            ],

            'tevs_remarks' => [
                'nullable',
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
            | Financial
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
    | Prepare PPE Items
    |--------------------------------------------------------------------------
    */

    private function preparePpeItems(
        array $items,
        int $projectBeneficiaries
    ): array {
        $prepared = [];

        foreach ($items as $index => $item) {
            /*
            |--------------------------------------------------------------------------
            | Ignore Empty PPE Rows
            |--------------------------------------------------------------------------
            */

            if (
                blank($item['product'] ?? null)
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
            | Validate Complete PPE Row
            |--------------------------------------------------------------------------
            */

            if (blank($item['ppe_type'] ?? null)) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.ppe_type" =>
                        'Select a PPE type.',
                ]);
            }

            if (blank($item['product'] ?? null)) {
                throw ValidationException::withMessages([
                    "ppe_items.$index.product" =>
                        'Enter the PPE product name.',
                ]);
            }

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
            | Prepared PPE
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