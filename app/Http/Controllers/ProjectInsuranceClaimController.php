<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectInsuranceClaimController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $this->validateRequest($request, $project);

        $user = $request->user();

        DB::transaction(function () use ($project, $validated, $user) {
            $claim = $project->insuranceClaims()->create([
                'incident_description' => trim($validated['incident_description']),
                'incident_date' => $validated['incident_date'],
                'incident_time' => $validated['incident_time'] ?? null,
                'reported_by' => $user->id,
            ]);

            foreach ($validated['beneficiaries'] as $entry) {
                $claim->beneficiaries()->create([
                    'beneficiary_id' => $entry['beneficiary_id'] ?? null,
                    'full_name' => trim($entry['full_name']),
                    'address' => trim($entry['address']),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Project Progress History Marker
            |--------------------------------------------------------------------------
            |
            | Keeps the claim visible inline in the existing Project Status History
            | timeline. The expandable detail is rendered from the claim record
            | itself.
            |
            */

            ProjectStatusHistory::create([
                'project_id' => $project->id,
                'from_status' => $project->status,
                'to_status' => $project->status,
                'changed_by' => $user->id,
                'remarks' => sprintf(
                    'Insurance Claim recorded: %d beneficiary/beneficiaries injured on %s.',
                    count($validated['beneficiaries']),
                    Carbon::parse($validated['incident_date'])->format('M d, Y'),
                ),
                'changed_at' => now(),
            ]);
        });

        return redirect()
            ->route('projects.show', [
                'project' => $project,
                'workspace' => 'beneficiaries',
            ])
            ->with('success', 'Insurance claim recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, Project $project): array
    {
        return $request->validate([
            'incident_description' => ['required', 'string', 'max:2000'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],

            'beneficiaries' => ['required', 'array', 'min:1'],
            'beneficiaries.*.beneficiary_id' => [
                'nullable',
                'integer',
                Rule::exists('project_beneficiaries', 'id')
                    ->where('project_id', $project->id),
            ],
            'beneficiaries.*.full_name' => ['required', 'string', 'max:150'],
            'beneficiaries.*.address' => ['required', 'string', 'max:255'],
        ]);
    }
}
