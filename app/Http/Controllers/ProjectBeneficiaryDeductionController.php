<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectEditLog;
use App\Models\ProjectEditRequest;
use App\Services\Projects\ProjectBeneficiaryDeductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Records which beneficiary addresses the beneficiaries excluded from the
 * completed obligation tranches came from (Actual Beneficiary Mapping).
 *
 * The TUPAD Coordinator records the first entry freely. Afterwards, like
 * other workflow data, the TC needs a Focal-approved edit request (one save);
 * Focal/Admin can correct it directly.
 */
class ProjectBeneficiaryDeductionController extends Controller
{
    public const SECTION = 'beneficiary_deduction';

    public function update(
        Request $request,
        Project $project,
        ProjectBeneficiaryDeductionService $deductions,
    ): RedirectResponse {
        $user = $request->user();
        $bag = ProjectSectionEditController::errorBag(self::SECTION, (int) $project->id);

        abort_unless($deductions->needsDeduction($project), 403, 'This project has no beneficiary shortfall to deduct.');

        $alreadyRecorded = $deductions->isRecorded($project);
        $approvedRequest = null;

        if ($alreadyRecorded && ! ($user->isAdmin() || $user->isFocal())) {
            $approvedRequest = ProjectEditRequest::query()
                ->where('project_id', $project->id)
                ->forTarget(self::SECTION, (int) $project->id)
                ->where('status', ProjectEditRequest::APPROVED)
                ->latest('decided_at')
                ->first();

            abort_if($approvedRequest === null, 403, 'Changing the recorded deductions requires an approved edit request from the Focal.');
        }

        try {
            $changes = $deductions->save($project, (array) $request->input('deductions', []), $user);
        } catch (ValidationException $exception) {
            return $this->back($project)
                ->withErrors($exception->errors(), $bag)
                ->withInput($request->all() + ['section_target' => $bag]);
        }

        if ($alreadyRecorded && $changes !== []) {
            ProjectEditLog::create([
                'project_id' => $project->id,
                'section' => self::SECTION,
                'record_id' => $project->id,
                'changes' => $changes,
                'edited_by' => $user->id,
                'approved_by' => $approvedRequest?->decided_by,
                'edit_request_id' => $approvedRequest?->id,
            ]);
        }

        $approvedRequest?->update([
            'status' => ProjectEditRequest::USED,
            'used_at' => now(),
        ]);

        return $this->back($project)->with(
            'success',
            $alreadyRecorded
                ? 'Beneficiary deductions updated. The Actual Beneficiary Mapping reflects the change.'
                : 'Beneficiary deductions recorded. The Actual Beneficiary Mapping is now available.'
        );
    }

    private function back(Project $project): RedirectResponse
    {
        return redirect()
            ->route('projects.show', ['project' => $project, 'workspace' => 'overview'])
            ->withFragment('section-beneficiary-deduction-'.$project->id);
    }
}
