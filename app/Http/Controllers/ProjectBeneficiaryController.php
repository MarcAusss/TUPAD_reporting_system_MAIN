<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectBeneficiary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectBeneficiaryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Beneficiary Registry
    |--------------------------------------------------------------------------
    */

    public function index(
        Project $project
    ): View {
        $this->ensureRegistryEditableStatus(
            $project
        );

        $beneficiaries = $project
            ->beneficiaries()
            ->with('encoder')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        return view(
            'projects.beneficiaries.index',
            [
                'project' => $project,
                'beneficiaries' => $beneficiaries,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Beneficiary
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        $this->ensureRegistryEditableStatus(
            $project
        );

        $validated = $this->validateBeneficiary(
            $request
        );

        return DB::transaction(function () use ($request, $project, $validated) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            $currentCount = $lockedProject
                ->beneficiaries()
                ->count();

            if (
                $currentCount
                >= (int) $lockedProject->beneficiaries_total
            ) {
                throw ValidationException::withMessages([
                    'first_name' =>
                        'The project already contains the declared number of beneficiaries.',
                ]);
            }

            $lockedProject
                ->beneficiaries()
                ->create([
                    ...$this->normalizedData(
                        $validated
                    ),

                    'encoded_by' =>
                        $request->user()->id,
                ]);

            $this->synchronizeFemaleCount(
                $lockedProject
            );

            return back()->with(
                'success',
                'Beneficiary added successfully.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(
        Project $project,
        ProjectBeneficiary $beneficiary
    ): View {
        $this->ensureRegistryEditableStatus(
            $project
        );

        $this->ensureBelongsToProject(
            $project,
            $beneficiary
        );

        return view(
            'projects.beneficiaries.edit',
            compact(
                'project',
                'beneficiary'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Project $project,
        ProjectBeneficiary $beneficiary
    ): RedirectResponse {
        $this->ensureRegistryEditableStatus(
            $project
        );

        $this->ensureBelongsToProject(
            $project,
            $beneficiary
        );

        $validated = $this->validateBeneficiary(
            $request
        );

        return DB::transaction(function () use ($project, $beneficiary, $validated) {
            $beneficiary->update(
                $this->normalizedData(
                    $validated
                )
            );

            $this->synchronizeFemaleCount(
                $project
            );

            return redirect()
                ->route(
                    'projects.beneficiaries.index',
                    $project
                )
                ->with(
                    'success',
                    'Beneficiary updated successfully.'
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Project $project,
        ProjectBeneficiary $beneficiary
    ): RedirectResponse {
        $this->ensureRegistryEditableStatus(
            $project
        );

        $this->ensureBelongsToProject(
            $project,
            $beneficiary
        );

        return DB::transaction(function () use ($project, $beneficiary) {
            $beneficiary->delete();

            $this->synchronizeFemaleCount(
                $project
            );

            return back()->with(
                'success',
                'Beneficiary removed successfully.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    private function validateBeneficiary(
        Request $request
    ): array {
        return $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'suffix' => [
                'nullable',
                'string',
                'max:30',
            ],

            'sex' => [
                'required',
                Rule::in([
                    'male',
                    'female',
                ]),
            ],

            'birth_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'is_pwd' => ['nullable', 'boolean'],

            'is_rebel_returnee' => ['nullable', 'boolean'],

            'grant_amount' => ['nullable', 'numeric', 'min:0'],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Input
    |--------------------------------------------------------------------------
    */

    private function normalizedData(
        array $validated
    ): array {
        return [
            'first_name' =>
                trim(
                    $validated['first_name']
                ),

            'middle_name' =>
                filled(
                    $validated['middle_name']
                    ?? null
                )
                ? trim(
                    $validated['middle_name']
                )
                : null,

            'last_name' =>
                trim(
                    $validated['last_name']
                ),

            'suffix' =>
                filled(
                    $validated['suffix']
                    ?? null
                )
                ? trim(
                    $validated['suffix']
                )
                : null,

            'sex' =>
                $validated['sex'],

            'birth_date' =>
                $validated['birth_date']
                ?? null,

            'contact_number' =>
                filled(
                    $validated['contact_number']
                    ?? null
                )
                ? trim(
                    $validated['contact_number']
                )
                : null,

            'is_pwd' => (bool) ($validated['is_pwd'] ?? false),

            'is_rebel_returnee' => (bool) ($validated['is_rebel_returnee'] ?? false),

            'grant_amount' => isset($validated['grant_amount'])
                ? round((float) $validated['grant_amount'], 2)
                : null,

            'remarks' =>
                $validated['remarks']
                ?? null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Synchronize Female Summary
    |--------------------------------------------------------------------------
    |
    | beneficiaries_total remains the approved/declared project target.
    |
    | beneficiaries_female is now derived from individual registry records.
    |
    */

    private function synchronizeFemaleCount(
        Project $project
    ): void {
        $femaleCount = $project
            ->beneficiaries()
            ->where(
                'sex',
                'female'
            )
            ->count();

        $project->update([
            'beneficiaries_female' =>
                $femaleCount,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Project Status Restriction
    |--------------------------------------------------------------------------
    */

    private function ensureRegistryEditableStatus(
        Project $project
    ): void {
        if (
            $project->status
            !== ProjectStatus::ONGOING_PROFILING
        ) {
            abort(
                403,
                'Beneficiary records can only be modified while the project is under Ongoing Profiling.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    */

    private function ensureBelongsToProject(
        Project $project,
        ProjectBeneficiary $beneficiary
    ): void {
        if (
            $beneficiary->project_id
            !== $project->id
        ) {
            abort(404);
        }
    }
}