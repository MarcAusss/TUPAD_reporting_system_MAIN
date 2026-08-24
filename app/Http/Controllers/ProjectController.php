<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\AdlAllocation;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->with([
                'allocation.adl',
                'creator',
            ])
            ->latest('date_received')
            ->latest('id')
            ->paginate(15);

        return view(
            'projects.index',
            compact('projects')
        );
    }

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

    public function create(): View
    {
        $allocations = AdlAllocation::query()
            ->with('adl')
            ->orderByDesc('id')
            ->get();

        return view(
            'projects.create',
            [
                'allocations' => $allocations,
                'implementationModes' => ImplementationMode::cases(),
                'ppeTypes' => PpeType::cases(),
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);

        return DB::transaction(function () use ($request, $validated) {
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

            $numberOfDays = (int) $validated['number_of_days'];

            $term = ProjectTerm::fromDays(
                $numberOfDays
            );

            $beneficiaries = (int) $validated['beneficiaries_total'];

            $femaleBeneficiaries = (int) $validated['beneficiaries_female'];

            if ($femaleBeneficiaries > $beneficiaries) {
                throw ValidationException::withMessages([
                    'beneficiaries_female' =>
                        'Female beneficiaries cannot exceed the total number of beneficiaries.',
                ]);
            }

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
                        number_format($totalProjectCost, 2),
                        number_format($availableProjectBudget, 2),
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Create Project
            |--------------------------------------------------------------------------
            */

            $project = Project::create([
                'adl_allocation_id' => $allocation->id,

                'date_received' => $validated['date_received'],

                'project_title' => trim(
                    $validated['project_title']
                ),

                'nature_of_work' => trim(
                    $validated['nature_of_work']
                ),

                'province' => trim(
                    $validated['province']
                ),

                'district' => trim(
                    $validated['district']
                ),

                'municipality' => trim(
                    $validated['municipality']
                ),

                'barangay' => trim(
                    $validated['barangay']
                ),

                'income_class' =>
                    filled($validated['income_class'] ?? null)
                    ? trim($validated['income_class'])
                    : null,

                'implementation_mode' =>
                    $validated['implementation_mode'],

                'number_of_days' => $numberOfDays,

                'term' => $term,

                'beneficiaries_total' => $beneficiaries,

                'beneficiaries_female' => $femaleBeneficiaries,

                'wage_rate' => $wageRate,

                'wages_total' => $wagesTotal,

                'ppe_total' => $ppeTotal,

                'insurance_rate' => $insuranceRate,

                'insurance_total' => $insuranceTotal,

                'total_project_cost' => $totalProjectCost,

                'status' => ProjectStatus::ONGOING_PROFILING,

                'remarks' => $validated['remarks'] ?? null,

                'created_by' => $request->user()->id,
            ]);

            if (!empty($ppeItems)) {
                $project
                    ->ppeItems()
                    ->createMany($ppeItems);
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
            && $project->status !== ProjectStatus::FOR_PAYMENT
            && $project->status !== ProjectStatus::COMPLETED
        ) {
            abort(403);
        }

        $project->load([
            'allocation.adl',
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

    private function validateProject(Request $request): array
    {
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
                Rule::enum(ImplementationMode::class),
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

            $beneficiaryCount = (int) (
                $item['beneficiary_count'] ?? 0
            );

            if ($beneficiaryCount > $projectBeneficiaries) {
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
                'ppe_type' => $item['ppe_type'],
                'product' => trim($item['product']),
                'beneficiary_count' => $beneficiaryCount,
                'unit_amount' => $unitAmount,
                'total_amount' => round(
                    $beneficiaryCount * $unitAmount,
                    2
                ),
            ];
        }

        return $prepared;
    }
}