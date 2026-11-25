<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\Project;
use App\Models\ProjectPostDocument;
use Illuminate\Support\Collection;

/**
 * Long-term Direct Administration projects are paid in two or more tranches,
 * each covering part of the work period. For each tranche:
 *
 *   TC   records the post-documentary submission with the tranche's
 *        beneficiaries, female beneficiaries, and coverage dates;
 *   Focal obligates it (beneficiaries prefilled, still editable; payee,
 *        obligation date, and wages) and disburses it;
 *   TC   records its Release of Assistance.
 *
 * This runs while implementation is still ongoing. The project still
 * completes only after the work period ends, the Focal completes the
 * tranches, and every tranche is disbursed and released (status engine).
 *
 * Short-term projects keep the original single-stage flow.
 */
final class LongTermTrancheService
{
    /** Statuses in which a long-term project's tranche flow is open. */
    public const OPEN_STATUSES = [
        ProjectStatus::ONGOING_IMPLEMENTATION,
        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ProjectStatus::FOR_PAYMENT,
    ];

    public function applies(Project $project): bool
    {
        return $project->term === ProjectTerm::LONG_TERM
            && $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION;
    }

    /** TC/Admin may record a post-documentary submission. */
    public function postDocumentsOpen(Project $project): bool
    {
        if ($this->applies($project)) {
            return in_array($project->status, self::OPEN_STATUSES, true)
                && $project->obligations_completed_at === null;
        }

        return $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION
            && $project->status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS;
    }

    /** Focal/Admin may obligate and disburse; TC/Admin may record releases. */
    public function paymentOpen(Project $project): bool
    {
        if ($project->implementation_mode !== ImplementationMode::DIRECT_ADMINISTRATION) {
            return false;
        }

        return $this->applies($project)
            ? in_array($project->status, self::OPEN_STATUSES, true)
            : $project->status === ProjectStatus::FOR_PAYMENT;
    }

    /** The Payment of Wages page (and its project sections) can be viewed. */
    public function paymentVisible(Project $project): bool
    {
        return $this->paymentOpen($project)
            || (
                $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION
                && $project->status === ProjectStatus::COMPLETED
            );
    }

    /** @return Collection<int, ProjectPostDocument> */
    public function trancheDocuments(Project $project): Collection
    {
        $project->loadMissing('postDocuments');

        return $project->postDocuments
            ->filter(fn (ProjectPostDocument $document): bool => $document->isTranche())
            ->sortBy('tranche_number')
            ->values();
    }

    /**
     * Tranche submissions the Focal has not obligated yet.
     *
     * @return Collection<int, ProjectPostDocument>
     */
    public function pendingTranches(Project $project): Collection
    {
        $project->loadMissing('obligations');

        $obligated = $project->obligations
            ->pluck('project_post_document_id')
            ->filter()
            ->all();

        return $this->trancheDocuments($project)
            ->reject(fn (ProjectPostDocument $document): bool => in_array($document->id, $obligated, true))
            ->values();
    }

    /**
     * Next tranche number. Tranche numbers are shared with the obligation
     * tranches, so a legacy obligation without a submission keeps its number.
     */
    public function nextTrancheNumber(Project $project): int
    {
        $lastDocument = (int) $project->postDocuments()->max('tranche_number');
        $lastObligation = (int) $project->obligations()->max('tranche_number');

        return max($lastDocument, $lastObligation) + 1;
    }

    /** Only the TC/Admin edits coverage dates, until the project is completed. */
    public function coverageEditable(Project $project, ProjectPostDocument $document): bool
    {
        return $this->applies($project)
            && $document->isTranche()
            && $project->status !== ProjectStatus::COMPLETED;
    }
}
