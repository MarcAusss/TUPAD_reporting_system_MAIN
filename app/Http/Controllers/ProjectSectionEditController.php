<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectEditLog;
use App\Models\ProjectEditRequest;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Projects\ProjectSectionRegistry;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Overview section edits. Focal/Admin edit directly; a TUPAD Coordinator
 * needs a Focal/Admin-approved request, which unlocks that section record
 * for one save. Every save is logged with its field-level changes.
 */
class ProjectSectionEditController extends Controller
{
    public static function errorBag(string $section, int $recordId): string
    {
        return 'section_'.$section.'_'.$recordId;
    }

    public function update(
        Request $request,
        Project $project,
        string $section,
        int $record,
        ProjectSectionRegistry $registry,
        ProjectStatusEngine $statusEngine,
    ): RedirectResponse {
        $user = $request->user();
        $definition = $registry->definition($section);

        // Custom sections (e.g. beneficiary deductions) have their own save endpoint.
        abort_if(isset($definition['view']), 404);

        $this->loadSectionRelations($project);
        $model = $registry->resolveRecord($project, $section, $record);
        $bag = self::errorBag($section, $record);

        $approvedRequest = null;

        if (! ($user->isAdmin() || $user->isFocal())) {
            $approvedRequest = ProjectEditRequest::query()
                ->where('project_id', $project->id)
                ->forTarget($section, $record)
                ->where('status', ProjectEditRequest::APPROVED)
                ->latest('decided_at')
                ->first();

            if ($approvedRequest === null) {
                abort(403, 'Editing this section requires an approved edit request from the Focal.');
            }
        }

        $input = $registry->prepareInput($definition, $request->all());

        $validator = Validator::make(
            $input,
            $registry->rules($definition, $project),
            [],
            $registry->attributeNames($definition),
        );

        if ($validator->fails()) {
            return $this->backToSection($project, $section, $record)
                ->withErrors($validator, $bag)
                ->withInput($request->all() + ['section_target' => $bag]);
        }

        $data = $registry->valuesToSave($definition, $validator->validated());

        try {
            $changes = DB::transaction(function () use ($definition, $project, $model, $data, $registry, $user, $approvedRequest, $section, $record): array {
                if (isset($definition['validate'])) {
                    $definition['validate']($project, $model, $data);
                }

                $before = [];
                foreach ($definition['fields'] as $name => $field) {
                    $before[$name] = $registry->display($field, $model->getAttribute($name));
                }

                isset($definition['save'])
                    ? $definition['save']($project, $model, $data)
                    : $model->update($data);

                $model->refresh();

                $changes = [];
                foreach ($definition['fields'] as $name => $field) {
                    $after = $registry->display($field, $model->getAttribute($name));

                    if ($after !== $before[$name]) {
                        $changes[] = ['field' => $field['label'], 'old' => $before[$name], 'new' => $after];
                    }
                }

                if ($changes !== []) {
                    ProjectEditLog::create([
                        'project_id' => $project->id,
                        'section' => $section,
                        'record_id' => $record,
                        'changes' => $changes,
                        'edited_by' => $user->id,
                        'approved_by' => $approvedRequest?->decided_by,
                        'edit_request_id' => $approvedRequest?->id,
                    ]);
                }

                // The approval covers one save, even when nothing changed.
                $approvedRequest?->update([
                    'status' => ProjectEditRequest::USED,
                    'used_at' => now(),
                ]);

                return $changes;
            });
        } catch (ValidationException $exception) {
            return $this->backToSection($project, $section, $record)
                ->withErrors($exception->errors(), $bag)
                ->withInput($request->all() + ['section_target' => $bag]);
        }

        if (! empty($definition['sync_status'])) {
            $statusEngine->synchronize($project, actorId: (int) $user->id);
        }

        return $this->backToSection($project, $section, $record)->with(
            'success',
            $changes === []
                ? "No changes were made to {$definition['label']}."
                : "{$definition['label']} updated. The change is noted in the project overview."
        );
    }

    public function requestEdit(
        Request $request,
        Project $project,
        string $section,
        int $record,
        ProjectSectionRegistry $registry,
    ): RedirectResponse {
        $this->loadSectionRelations($project);
        $registry->resolveRecord($project, $section, $record);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $open = ProjectEditRequest::query()
            ->where('project_id', $project->id)
            ->forTarget($section, $record)
            ->whereIn('status', [ProjectEditRequest::PENDING, ProjectEditRequest::APPROVED])
            ->exists();

        if (! $open) {
            ProjectEditRequest::create([
                'project_id' => $project->id,
                'section' => $section,
                'record_id' => $record,
                'reason' => $validated['reason'] ?? null,
                'status' => ProjectEditRequest::PENDING,
                'requested_by' => $request->user()->id,
            ]);
        }

        return $this->backToSection($project, $section, $record)->with(
            'success',
            $open
                ? 'An edit request for this section is already open.'
                : 'Edit request sent. The Focal has been notified and can approve it from their notifications.'
        );
    }

    public function approve(
        Request $request,
        ProjectEditRequest $editRequest,
        ProvinceAccessService $provinceAccess,
    ): JsonResponse|RedirectResponse {
        return $this->decide($request, $editRequest, $provinceAccess, ProjectEditRequest::APPROVED);
    }

    public function decline(
        Request $request,
        ProjectEditRequest $editRequest,
        ProvinceAccessService $provinceAccess,
    ): JsonResponse|RedirectResponse {
        return $this->decide($request, $editRequest, $provinceAccess, ProjectEditRequest::DECLINED);
    }

    private function decide(
        Request $request,
        ProjectEditRequest $editRequest,
        ProvinceAccessService $provinceAccess,
        string $status,
    ): JsonResponse|RedirectResponse {
        abort_unless($provinceAccess->canAccessProject($request->user(), $editRequest->project), 403);

        $decided = false;

        if ($editRequest->status === ProjectEditRequest::PENDING) {
            $editRequest->update([
                'status' => $status,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            $decided = true;
        }

        $message = ! $decided
            ? 'This edit request was already decided.'
            : ($status === ProjectEditRequest::APPROVED
                ? 'Edit request approved. The TUPAD Coordinator can now edit the section once.'
                : 'Edit request declined.');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $editRequest->fresh()->status,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    private function backToSection(Project $project, string $section, int $record): RedirectResponse
    {
        return redirect()
            ->route('projects.show', ['project' => $project, 'workspace' => 'overview'])
            ->withFragment('section-'.str_replace('_', '-', $section).'-'.$record);
    }

    private function loadSectionRelations(Project $project): void
    {
        $project->loadMissing([
            'evaluations',
            'approval',
            'insuranceEnrollment',
            'ppeDeliveries.items.ppeItem',
            'noticeToProceed',
            'orientation',
            'implementation',
            'postDocuments',
            'obligations.disbursements',
            'nafa.attachments',
            'payout',
        ]);
    }
}
