<?php

namespace App\Http\Controllers;

use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectStatus;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectDraftReviewController extends Controller
{
    public function index(Request $request, ProvinceAccessService $provinceAccess): View
    {
        $query = $provinceAccess->scopeProjectDrafts(ProjectDraft::query(), $request->user())
            ->with([
                'allocation.adl',
                'encoder',
            ])
            ->where(
                'status',
                ProjectDraftStatus::PENDING_TC_REVIEW
            );

        /*
        |--------------------------------------------------------------------------
        | TC only sees drafts assigned to them
        |--------------------------------------------------------------------------
        */

        if ($request->user()->isTc()) {
            $query->where(
                'assigned_tc_id',
                $request->user()->id
            );
        }

        $drafts = $query
            ->oldest('submitted_at')
            ->paginate(15);

        return view(
            'project-drafts.review-index',
            compact('drafts')
        );
    }

    public function show(
        Request $request,
        ProjectDraft $projectDraft
    ): View {
        $this->ensureReviewerAccess(
            $request,
            $projectDraft
        );

        $projectDraft->load([
            'allocation.adl',
            'ppeItems',
            'encoder',
            'assignedTc',
            'reviewer',
            'confirmedProject',
        ]);

        return view(
            'project-drafts.review-show',
            [
                'draft' => $projectDraft,
            ]
        );
    }

    public function returnForCorrection(
        Request $request,
        ProjectDraft $projectDraft
    ): RedirectResponse {
        $this->ensureReviewerAccess(
            $request,
            $projectDraft
        );

        if (!$projectDraft->isPendingReview()) {
            abort(
                403,
                'Only drafts pending review may be returned.'
            );
        }

        $validated = $request->validate([
            'tc_review_remarks' => [
                'required',
                'string',
                'max:3000',
            ],
        ]);

        $projectDraft->update([
            'status' =>
                ProjectDraftStatus::RETURNED_FOR_CORRECTION,

            'tc_review_remarks' =>
                trim(
                    $validated['tc_review_remarks']
                ),

            'reviewed_at' => now(),

            'reviewed_by' =>
                $request->user()->id,
        ]);

        return redirect()
            ->route(
                'project-draft-reviews.index'
            )
            ->with(
                'success',
                'Draft returned to the GIP encoder for correction.'
            );
    }

    public function confirm(
        Request $request,
        ProjectDraft $projectDraft
    ): RedirectResponse {
        $this->ensureReviewerAccess(
            $request,
            $projectDraft
        );

        if (!$projectDraft->isPendingReview()) {
            abort(
                403,
                'Only drafts pending review may be confirmed.'
            );
        }

        return DB::transaction(function () use ($request, $projectDraft) {
            /*
            |--------------------------------------------------------------------------
            | Lock Draft
            |--------------------------------------------------------------------------
            */

            $lockedDraft = ProjectDraft::query()
                ->lockForUpdate()
                ->findOrFail(
                    $projectDraft->id
                );

            if (
                $lockedDraft->status
                !== ProjectDraftStatus::PENDING_TC_REVIEW
            ) {
                throw ValidationException::withMessages([
                    'draft' =>
                        'This draft has already been processed.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Allocation
            |--------------------------------------------------------------------------
            */

            $allocation = AdlAllocation::query()
                ->lockForUpdate()
                ->findOrFail(
                    $lockedDraft->adl_allocation_id
                );

            /*
            |--------------------------------------------------------------------------
            | Calculate Remaining Official Allocation
            |--------------------------------------------------------------------------
            |
            | Drafts are intentionally excluded.
            |
            */

            $existingOfficialProjectCost =
                (float) $allocation
                    ->projects()
                    ->sum('total_project_cost');

            $remainingAllocation = round(
                (float) $allocation->amount
                - $existingOfficialProjectCost,
                2
            );

            $draftCost =
                (float) $lockedDraft
                    ->total_project_cost;

            if ($draftCost > $remainingAllocation) {
                throw ValidationException::withMessages([
                    'allocation' => sprintf(
                        'This project costs ₱%s, but only ₱%s remains in the selected allocation.',
                        number_format(
                            $draftCost,
                            2
                        ),
                        number_format(
                            $remainingAllocation,
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

            $officialProject = Project::create([
                'province_id' =>
                    $lockedDraft->province_id,

                'municipality_id' =>
                    $lockedDraft->municipality_id,

                'barangay_id' =>
                    $lockedDraft->barangay_id,

                'adl_allocation_id' =>
                    $allocation->id,

                'date_received' =>
                    $lockedDraft->date_received,

                'project_title' =>
                    $lockedDraft->project_title,

                'nature_of_work' =>
                    $lockedDraft->nature_of_work,

                'province' =>
                    $lockedDraft->province,

                'district' =>
                    $lockedDraft->district,

                'municipality' =>
                    $lockedDraft->municipality,

                'barangay' =>
                    $lockedDraft->barangay,

                'income_class' =>
                    $lockedDraft->income_class,

                'implementation_mode' =>
                    $lockedDraft
                        ->implementation_mode
                        ->value,

                'number_of_days' =>
                    $lockedDraft->number_of_days,

                'term' =>
                    $lockedDraft->term->value,

                'beneficiaries_total' =>
                    $lockedDraft
                        ->beneficiaries_total,

                'beneficiaries_female' =>
                    $lockedDraft
                        ->beneficiaries_female,

                'wage_rate' =>
                    $lockedDraft->wage_rate,

                'wages_total' =>
                    $lockedDraft->wages_total,

                'ppe_total' =>
                    $lockedDraft->ppe_total,

                'insurance_rate' =>
                    $lockedDraft
                        ->insurance_rate,

                'insurance_total' =>
                    $lockedDraft
                        ->insurance_total,

                'total_project_cost' =>
                    $lockedDraft
                        ->total_project_cost,

                'status' =>
                    ProjectStatus::ONGOING_PROFILING,

                'remarks' =>
                    $lockedDraft->remarks,

                /*
                |--------------------------------------------------------------------------
                | TC becomes creator of official record
                |--------------------------------------------------------------------------
                |
                | The original GIP encoder remains preserved on project_drafts.
                |
                */

                'created_by' =>
                    $request->user()->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Copy PPE
            |--------------------------------------------------------------------------
            */

            $lockedDraft->load('ppeItems');

            foreach (
                $lockedDraft->ppeItems
                as $draftPpe
            ) {
                $officialProject
                    ->ppeItems()
                    ->create([
                        'ppe_type' =>
                            $draftPpe
                                ->ppe_type
                                ->value,

                        'product' =>
                            $draftPpe->product,

                        'beneficiary_count' =>
                            $draftPpe
                                ->beneficiary_count,

                        'unit_amount' =>
                            $draftPpe->unit_amount,

                        'total_amount' =>
                            $draftPpe->total_amount,
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Mark Draft Confirmed
            |--------------------------------------------------------------------------
            */

            $lockedDraft->update([
                'status' =>
                    ProjectDraftStatus::CONFIRMED,

                'reviewed_at' =>
                    now(),

                'reviewed_by' =>
                    $request->user()->id,

                'confirmed_at' =>
                    now(),

                'confirmed_project_id' =>
                    $officialProject->id,

                'tc_review_remarks' =>
                    $request->filled(
                        'tc_review_remarks'
                    )
                    ? trim(
                        $request->input(
                            'tc_review_remarks'
                        )
                    )
                    : null,
            ]);

            return redirect()
                ->route(
                    'projects.show',
                    $officialProject
                )
                ->with(
                    'success',
                    'GIP project draft confirmed and converted into an official project.'
                );
        });
    }

    private function ensureReviewerAccess(
        Request $request,
        ProjectDraft $draft
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Admin may review all drafts
        |--------------------------------------------------------------------------
        */

        if ($request->user()->isAdmin()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | TC may review only assigned drafts
        |--------------------------------------------------------------------------
        */

        if (
            $request->user()->isTc()
            && $draft->assigned_tc_id
            === $request->user()->id
        ) {
            return;
        }

        abort(403);
    }
}