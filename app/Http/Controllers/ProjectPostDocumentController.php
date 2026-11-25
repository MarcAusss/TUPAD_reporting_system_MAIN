<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectPostDocument;
use App\Services\Projects\LongTermTrancheService;
use App\Services\Projects\ProjectStatusEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProjectPostDocumentController extends Controller
{
    public const COVERAGE_ERROR_PREFIX = 'coverage_';

    public static function coverageErrorBag(ProjectPostDocument $document): string
    {
        return self::COVERAGE_ERROR_PREFIX.$document->id;
    }

    public function store(
        Request $request,
        Project $project,
        ProjectStatusEngine $statusEngine,
        LongTermTrancheService $tranches,
    ): RedirectResponse
    {
        if (! $tranches->postDocumentsOpen($project)) {
            abort(
                403,
                $tranches->applies($project)
                    ? 'Tranche post-documentary requirements are open from Ongoing Implementation until the Focal completes the obligation tranches.'
                    : 'Post-documentary requirements apply only to Direct Administration projects with For Submission of Post-Docs status.'
            );
        }

        $longTerm = $tranches->applies($project);

        $rules = [
            'date_received' => ['required', 'date'],
            'document_type' => ['nullable', 'string', 'max:255'],
            'date_forwarded_to_imsd' => [
                'required',
                'date',
                'after_or_equal:date_received',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];

        if ($longTerm) {
            $rules += [
                'beneficiaries_total' => ['required', 'integer', 'min:1', 'max:'.(int) $project->beneficiaries_total],
                'beneficiaries_female' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:'.(int) $project->beneficiaries_female,
                    'lte:beneficiaries_total',
                ],
                ...$this->coverageRules(),
            ];
        }

        $validated = Validator::make($request->all(), $rules, $this->coverageMessages() + [
            'beneficiaries_total.max' => 'The tranche cannot include more than the project\'s :max beneficiaries.',
            'beneficiaries_female.max' => 'The tranche cannot include more than the project\'s :max female beneficiaries.',
            'beneficiaries_female.lte' => 'Female beneficiaries cannot exceed the tranche\'s number of beneficiaries.',
        ], [
            'document_type' => 'documents received',
            'beneficiaries_total' => 'number of beneficiaries',
            'beneficiaries_female' => 'female beneficiaries',
            'coverage_start_date' => 'coverage start date',
            'coverage_end_date' => 'coverage end date',
        ])->validate();

        $trancheNumber = DB::transaction(function () use ($request, $project, $statusEngine, $validated, $longTerm, $tranches): ?int {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if (! $tranches->postDocumentsOpen($lockedProject)) {
                throw ValidationException::withMessages([
                    'date_received' => 'This project no longer accepts post-documentary requirements.',
                ]);
            }

            $attributes = [
                'date_received' => $validated['date_received'],
                'document_type' => filled($validated['document_type'] ?? null)
                    ? trim($validated['document_type'])
                    : 'Post-Documentary Requirements',
                'date_forwarded_to_imsd' => $validated['date_forwarded_to_imsd'],
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ];

            $trancheNumber = null;

            if ($longTerm) {
                $this->ensureCoverageFits($lockedProject, $validated);

                $trancheNumber = $tranches->nextTrancheNumber($lockedProject);

                $attributes += [
                    'tranche_number' => $trancheNumber,
                    'beneficiaries_total' => (int) $validated['beneficiaries_total'],
                    'beneficiaries_female' => (int) $validated['beneficiaries_female'],
                    'coverage_start_date' => $validated['coverage_start_date'],
                    'coverage_end_date' => $validated['coverage_end_date'],
                ];
            }

            $lockedProject->postDocuments()->create($attributes);

            $statusEngine->synchronize(
                $lockedProject,
                actorId: (int) $request->user()->id,
            );

            return $trancheNumber;
        });

        $project->refresh();

        if ($trancheNumber !== null) {
            return back()->with(
                'success',
                "Post-documentary requirements saved for Tranche {$trancheNumber}. The Focal can now process its payment."
            );
        }

        return back()->with(
            'success',
            $project->status === ProjectStatus::FOR_PAYMENT
                ? 'Post-documentary requirements saved. Project automatically moved to For Payment.'
                : 'Post-documentary requirements saved successfully.'
        );
    }

    /**
     * TC/Admin correction of a long-term tranche's coverage dates. The Focal
     * only sees these dates; they cannot change them.
     */
    public function updateCoverage(
        Request $request,
        Project $project,
        ProjectPostDocument $postDocument,
        LongTermTrancheService $tranches,
    ): RedirectResponse {
        if ((int) $postDocument->project_id !== (int) $project->id) {
            abort(404);
        }

        if (! $tranches->coverageEditable($project, $postDocument)) {
            abort(403, 'The coverage dates can only be edited for long-term tranches of projects that are not yet completed.');
        }

        $bag = self::coverageErrorBag($postDocument);

        $validated = Validator::make(
            $request->all(),
            $this->coverageRules(),
            $this->coverageMessages(),
            [
                'coverage_start_date' => 'coverage start date',
                'coverage_end_date' => 'coverage end date',
            ],
        )->validateWithBag($bag);

        DB::transaction(function () use ($request, $project, $postDocument, $validated, $bag): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            try {
                $this->ensureCoverageFits($lockedProject, $validated, $postDocument);
            } catch (ValidationException $exception) {
                throw $exception->errorBag($bag);
            }

            $postDocument->update([
                'coverage_start_date' => $validated['coverage_start_date'],
                'coverage_end_date' => $validated['coverage_end_date'],
                'coverage_updated_by' => $request->user()->id,
                'coverage_updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('projects.show', ['project' => $project, 'workspace' => 'workflow'])
            ->withFragment('post-documents')
            ->with('success', "Coverage dates updated for Tranche {$postDocument->tranche_number}.");
    }

    /** @return array<string, list<string>> */
    private function coverageRules(): array
    {
        return [
            'coverage_start_date' => ['required', 'date'],
            'coverage_end_date' => ['required', 'date', 'after_or_equal:coverage_start_date'],
        ];
    }

    /** @return array<string, string> */
    private function coverageMessages(): array
    {
        return [
            'coverage_end_date.after_or_equal' => 'The coverage end date must be on or after the coverage start date.',
        ];
    }

    /**
     * Coverage must stay inside the work period and must not overlap another
     * tranche's coverage.
     *
     * @param  array<string, mixed>  $validated
     */
    private function ensureCoverageFits(
        Project $project,
        array $validated,
        ?ProjectPostDocument $ignore = null,
    ): void {
        $start = CarbonImmutable::parse($validated['coverage_start_date'])->startOfDay();
        $end = CarbonImmutable::parse($validated['coverage_end_date'])->startOfDay();

        $implementation = $project->implementation()->first();

        if ($implementation !== null) {
            $periodStart = CarbonImmutable::parse($implementation->start_date->format('Y-m-d'))->startOfDay();
            $periodEnd = CarbonImmutable::parse($implementation->end_date->format('Y-m-d'))->startOfDay();

            if ($start->lt($periodStart) || $end->gt($periodEnd)) {
                throw ValidationException::withMessages([
                    'coverage_start_date' => sprintf(
                        'The coverage must fall within the work period (%s to %s).',
                        $periodStart->format('M d, Y'),
                        $periodEnd->format('M d, Y'),
                    ),
                ]);
            }
        }

        $overlap = $project->postDocuments()
            ->whereNotNull('tranche_number')
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereDate('coverage_start_date', '<=', $end->toDateString())
            ->whereDate('coverage_end_date', '>=', $start->toDateString())
            ->orderBy('tranche_number')
            ->first();

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'coverage_start_date' => sprintf(
                    'The coverage overlaps Tranche %d (%s to %s).',
                    $overlap->tranche_number,
                    $overlap->coverage_start_date->format('M d, Y'),
                    $overlap->coverage_end_date->format('M d, Y'),
                ),
            ]);
        }
    }
}
