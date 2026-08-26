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
use App\Models\ProjectLocation;
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
                'projectLocations.province',
                'projectLocations.municipality',
                'projectLocations.barangays',
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
            ->whereIn('name', [
                'Albay',
                'Camarines Norte',
                'Camarines Sur',
                'Catanduanes',
                'Masbate',
                'Sorsogon',
            ])
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Focal-maintained Sponsor / Partner choices
        |--------------------------------------------------------------------------
        */

        $fundSponsorOptions = AdlAllocation::query()
            ->whereNotNull('fund_sponsor')
            ->where('fund_sponsor', '!=', '')
            ->distinct()
            ->orderBy('fund_sponsor')
            ->pluck('fund_sponsor')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $partnerOptions = AdlAllocation::query()
            ->whereNotNull('partner')
            ->where('partner', '!=', '')
            ->distinct()
            ->orderBy('partner')
            ->pluck('partner')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return view(
            'projects.create',
            [
                'allocations' => $allocations,
                'provinces' => $provinces,
                'fundSponsorOptions' => $fundSponsorOptions,
                'partnerOptions' => $partnerOptions,
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

        /*
        |--------------------------------------------------------------------------
        | Resolve Sponsor / Partner Selection
        |--------------------------------------------------------------------------
        |
        | TC normally selects a Focal-maintained value. "Other" is allowed as a
        | project-specific exception and does not modify Focal's reference list.
        |
        */

        if (($validated['fund_sponsor'] ?? null) === '__other__') {
            $validated['fund_sponsor'] =
                trim($validated['fund_sponsor_other']);
        }

        if (($validated['partner'] ?? null) === '__other__') {
            $validated['partner'] =
                trim($validated['partner_other']);
        }

        unset(
            $validated['fund_sponsor_other'],
            $validated['partner_other']
        );

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
            | Validate Multi-Location Geographic Hierarchy
            |--------------------------------------------------------------------------
            */

            $province = Province::query()
                ->where('is_active', true)
                ->findOrFail($validated['province_id']);

            $resolvedLocations = collect($validated['project_locations'])
                ->values()
                ->map(function (array $location, int $index) use ($province) {
                    $municipality = Municipality::query()
                        ->where('province_id', $province->id)
                        ->where('district', $location['district'])
                        ->where('is_active', true)
                        ->findOrFail($location['municipality_id']);

                    $barangayIds = array_values(
                        array_unique($location['barangay_ids'])
                    );

                    $barangays = Barangay::query()
                        ->where('municipality_id', $municipality->id)
                        ->where('is_active', true)
                        ->whereIn('id', $barangayIds)
                        ->get();

                    if ($barangays->count() !== count($barangayIds)) {
                        /*
                        |--------------------------------------------------------------------------
                        | Geographic Hierarchy Violation
                        |--------------------------------------------------------------------------
                        |
                        | Preserve the system's existing hardening behavior:
                        | a barangay that does not belong to the selected municipality is
                        | treated as an invalid geographic resource reference (404), not as
                        | a normal form-validation redirect (302).
                        |
                        */

                        abort(
                            404,
                            'One or more selected barangays do not belong to the selected municipality.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Exact Barangay Beneficiary Allocation
                    |--------------------------------------------------------------------------
                    |
                    | New Project Create requests allocate the project beneficiary totals to
                    | every selected barangay. Older integrations may omit the allocation
                    | object; those requests remain supported for backward compatibility.
                    |
                    */

                    $submittedAllocations = collect(
                        $location['barangay_allocations'] ?? []
                    );

                    $beneficiaryAllocations = null;

                    if ($submittedAllocations->isNotEmpty()) {
                        $selectedBarangayIds = $barangays
                            ->pluck('id')
                            ->map(fn ($id) => (string) $id)
                            ->values();

                        $unexpectedBarangayIds = $submittedAllocations
                            ->keys()
                            ->map(fn ($id) => (string) $id)
                            ->diff($selectedBarangayIds);

                        if ($unexpectedBarangayIds->isNotEmpty()) {
                            throw ValidationException::withMessages([
                                "project_locations.{$index}.barangay_allocations" =>
                                    'Beneficiary allocations may only be entered for selected barangays.',
                            ]);
                        }

                        $beneficiaryAllocations = collect();

                        foreach ($barangays as $barangay) {
                            $allocation =
                                $submittedAllocations->get((string) $barangay->id)
                                ?? $submittedAllocations->get($barangay->id);

                            if (! is_array($allocation)) {
                                throw ValidationException::withMessages([
                                    "project_locations.{$index}.barangay_allocations.{$barangay->id}" =>
                                        "Enter the beneficiary allocation for {$barangay->name}.",
                                ]);
                            }

                            $allocatedTotal =
                                (int) ($allocation['beneficiaries_total'] ?? -1);

                            $allocatedFemale =
                                (int) ($allocation['beneficiaries_female'] ?? -1);

                            if (
                                $allocatedTotal < 0
                                || $allocatedFemale < 0
                            ) {
                                throw ValidationException::withMessages([
                                    "project_locations.{$index}.barangay_allocations.{$barangay->id}" =>
                                        'Barangay beneficiary allocations cannot be negative.',
                                ]);
                            }

                            if ($allocatedFemale > $allocatedTotal) {
                                throw ValidationException::withMessages([
                                    "project_locations.{$index}.barangay_allocations.{$barangay->id}.beneficiaries_female" =>
                                        "Female beneficiaries for {$barangay->name} cannot exceed its total beneficiaries.",
                                ]);
                            }

                            $beneficiaryAllocations->put(
                                $barangay->id,
                                [
                                    'beneficiaries_total' => $allocatedTotal,
                                    'beneficiaries_female' => $allocatedFemale,
                                ]
                            );
                        }
                    }

                    return [
                        'municipality' => $municipality,
                        'barangays' => $barangays,
                        'district' => $municipality->district,
                        'beneficiary_allocations' => $beneficiaryAllocations,
                    ];
                });

            if (
                $resolvedLocations
                    ->pluck('municipality.id')
                    ->duplicates()
                    ->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'project_locations' =>
                        'Each municipality/city may only be added once. Select multiple barangays inside the same location card.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Cross-location Beneficiary Allocation Integrity
            |--------------------------------------------------------------------------
            |
            | If the new allocation payload is present, every selected location must
            | participate and the barangay sums must exactly match the project totals.
            | This is what makes Province -> Municipality -> Barangay reporting exact.
            |
            */

            $locationsWithExactAllocation = $resolvedLocations
                ->filter(
                    fn (array $location) =>
                        $location['beneficiary_allocations'] !== null
                )
                ->count();

            $requiresExactBarangayAllocation =
                $request->boolean(
                    'exact_barangay_allocation'
                );

            if (
                (
                    $requiresExactBarangayAllocation
                    && $locationsWithExactAllocation !== $resolvedLocations->count()
                )
                || (
                    $locationsWithExactAllocation > 0
                    && $locationsWithExactAllocation !== $resolvedLocations->count()
                )
            ) {
                throw ValidationException::withMessages([
                    'project_locations' =>
                        'Enter beneficiary allocations for every selected barangay in every project location.',
                ]);
            }

            if ($locationsWithExactAllocation === $resolvedLocations->count()) {
                $allocatedBeneficiaries = (int) $resolvedLocations
                    ->sum(
                        fn (array $location) =>
                            $location['beneficiary_allocations']
                                ->sum('beneficiaries_total')
                    );

                $allocatedFemaleBeneficiaries = (int) $resolvedLocations
                    ->sum(
                        fn (array $location) =>
                            $location['beneficiary_allocations']
                                ->sum('beneficiaries_female')
                    );

                if ($allocatedBeneficiaries !== $beneficiaries) {
                    throw ValidationException::withMessages([
                        'project_locations' => sprintf(
                            'Barangay beneficiary allocations must total %s. The current allocation totals %s.',
                            number_format($beneficiaries),
                            number_format($allocatedBeneficiaries),
                        ),
                    ]);
                }

                if ($allocatedFemaleBeneficiaries !== $femaleBeneficiaries) {
                    throw ValidationException::withMessages([
                        'project_locations' => sprintf(
                            'Barangay female beneficiary allocations must total %s. The current allocation totals %s.',
                            number_format($femaleBeneficiaries),
                            number_format($allocatedFemaleBeneficiaries),
                        ),
                    ]);
                }
            } elseif (
                $resolvedLocations
                    ->sum(
                        fn (array $location) =>
                            $location['barangays']->count()
                    ) === 1
            ) {
                /*
                 * Safe backward compatibility: when the whole project has only one
                 * barangay, its exact allocation is unambiguous even if an older
                 * client omitted the new allocation fields.
                 */

                $resolvedLocations = $resolvedLocations
                    ->map(function (array $location) use (
                        $beneficiaries,
                        $femaleBeneficiaries
                    ) {
                        $barangay = $location['barangays']->first();

                        $location['beneficiary_allocations'] = collect([
                            $barangay->id => [
                                'beneficiaries_total' => $beneficiaries,
                                'beneficiaries_female' => $femaleBeneficiaries,
                            ],
                        ]);

                        return $location;
                    });
            }

            $primaryLocation = $resolvedLocations->first();
            $municipality = $primaryLocation['municipality'];
            $barangay = $primaryLocation['barangays']->first();

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

            $insuranceBeneficiaries =
                array_key_exists(
                    'insurance_beneficiaries',
                    $validated
                )
                && $validated['insurance_beneficiaries'] !== null
                    ? (int) $validated['insurance_beneficiaries']
                    : (int) $validated['beneficiaries_total'];

            $insuranceTotal = round(
                $insuranceRate
                * $insuranceBeneficiaries,
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

                'insurance_beneficiaries' =>
                    $insuranceBeneficiaries,

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
                    ProjectStatus::TSSD_EVALUATION,

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
            | Create Multi-Location Records
            |--------------------------------------------------------------------------
            */

            foreach ($resolvedLocations as $index => $resolvedLocation) {
                $projectLocation = $project->projectLocations()->create([
                    'province_id' => $province->id,
                    'municipality_id' => $resolvedLocation['municipality']->id,
                    'district' => $resolvedLocation['district'],
                    'sort_order' => $index + 1,
                ]);

                $beneficiaryAllocations =
                    $resolvedLocation['beneficiary_allocations'];

                $syncPayload = $resolvedLocation['barangays']
                    ->mapWithKeys(function ($barangay) use ($beneficiaryAllocations) {
                        $allocation = $beneficiaryAllocations?->get($barangay->id);

                        return [
                            $barangay->id => [
                                'beneficiaries_total' =>
                                    $allocation['beneficiaries_total'] ?? null,

                                'beneficiaries_female' =>
                                    $allocation['beneficiaries_female'] ?? null,
                            ],
                        ];
                    })
                    ->all();

                $projectLocation->barangays()->sync(
                    $syncPayload
                );
            }

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
                    'Project profile saved successfully and moved to TSSD Evaluation.'
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

            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',

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
        if (
            ! $request->has('project_locations')
            && $request->filled('municipality_id')
            && $request->filled('barangay_id')
        ) {
            $legacyMunicipality = Municipality::query()
                ->find($request->integer('municipality_id'));

            if ($legacyMunicipality) {
                $request->merge([
                    'project_locations' => [[
                        'district' => $legacyMunicipality->district ?? 'Not Assigned',
                        'municipality_id' => $request->integer('municipality_id'),
                        'barangay_ids' => [
                            $request->integer('barangay_id'),
                        ],
                    ]],
                ]);
            }
        }

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

            'fund_sponsor_other' => [
                'nullable',
                'required_if:fund_sponsor,__other__',
                'string',
                'max:255',
            ],

            'partner' => [
                'required',
                'string',
                'max:255',
            ],

            'partner_other' => [
                'nullable',
                'required_if:partner,__other__',
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

            'exact_barangay_allocation' => [
                'nullable',
                'boolean',
            ],

            'project_locations' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],

            'project_locations.*.district' => [
                'required',
                'string',
                'max:100',
            ],

            'project_locations.*.municipality_id' => [
                'required',
                'integer',
                'exists:municipalities,id',
            ],

            'project_locations.*.barangay_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'project_locations.*.barangay_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:barangays,id',
            ],

            'project_locations.*.barangay_allocations' => [
                'nullable',
                'array',
            ],

            'project_locations.*.barangay_allocations.*' => [
                'nullable',
                'array',
            ],

            'project_locations.*.barangay_allocations.*.beneficiaries_total' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'project_locations.*.barangay_allocations.*.beneficiaries_female' => [
                'nullable',
                'integer',
                'min:0',
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

            'insurance_beneficiaries' => [
                /*
                |--------------------------------------------------------------------------
                | Backward Compatibility
                |--------------------------------------------------------------------------
                |
                | The Project Create UI requires this field, but older tests,
                | integrations, and existing server-side payloads may omit it.
                | When omitted, store() falls back to beneficiaries_total.
                |
                */
                'nullable',
                'integer',
                'min:0',
                'lte:beneficiaries_total',
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