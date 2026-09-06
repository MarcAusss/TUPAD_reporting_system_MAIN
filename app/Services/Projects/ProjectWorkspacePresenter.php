<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

final class ProjectWorkspacePresenter
{
    /**
     * Build the UI-only workspace model used by the official project detail page.
     *
     * @return array<string, mixed>
     */
    public function present(Project $project, User $user, ?string $requestedTab = null): array
    {
        $stages = $this->stagesFor($project);
        $currentStageIndex = $this->currentStageIndex($project);

        foreach ($stages as $index => &$stage) {
            $stage['state'] = match (true) {
                $project->status === ProjectStatus::COMPLETED => 'complete',
                $index < $currentStageIndex => 'complete',
                $index === $currentStageIndex => 'current',
                default => 'upcoming',
            };
        }
        unset($stage);

        $action = $this->actionFor($project, $user);

        $tabs = [
            ['key' => 'overview', 'label' => 'Overview'],
            ['key' => 'beneficiaries', 'label' => 'Beneficiaries'],
            ['key' => 'workflow', 'label' => 'Workflow'],
            ['key' => 'financial', 'label' => 'Financial'],
            ['key' => 'history', 'label' => 'History'],
        ];

        $allowedTabs = array_column($tabs, 'key');
        $defaultTab = in_array($requestedTab, $allowedTabs, true)
            ? $requestedTab
            : ($project->status === ProjectStatus::COMPLETED ? 'overview' : $action['tab']);

        return [
            'status_tone' => $this->statusTone($project->status),
            'default_tab' => $defaultTab,
            'current_stage_index' => $currentStageIndex,
            'progress_percent' => (int) round(
                ($currentStageIndex / max(count($stages) - 1, 1)) * 100
            ),
            'stages' => $stages,
            'action' => $action,
            'tabs' => $tabs,
        ];
    }

    private function stagesFor(Project $project): array
    {
        $labels = $project->implementation_mode === ImplementationMode::THROUGH_ACP
            ? ['Profiling', 'Evaluation', 'Approval', 'ACP Payment', 'Check Release', 'Implementation', 'Liquidation', 'Completed']
            : ['Profiling', 'Evaluation', 'Approval', 'Preparation', 'Implementation', 'Post Documents', 'Payment', 'Completed'];

        return collect($labels)
            ->map(fn (string $label, int $index): array => [
                'key' => str((string) ($index + 1).'-'.$label)->slug()->toString(),
                'label' => $label,
                'state' => 'upcoming',
            ])
            ->all();
    }

    private function currentStageIndex(Project $project): int
    {
        if ($project->implementation_mode === ImplementationMode::THROUGH_ACP) {
            return match ($project->status) {
                ProjectStatus::ONGOING_PROFILING => 0,
                ProjectStatus::TSSD_EVALUATION,
                ProjectStatus::FOR_COMPLIANCE => 1,
                ProjectStatus::FOR_APPROVAL => 2,
                ProjectStatus::APPROVED,
                ProjectStatus::FOR_PAYMENT => 3,
                ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT => 4,
                ProjectStatus::FOR_IMPLEMENTATION,
                ProjectStatus::ONGOING_IMPLEMENTATION => 5,
                ProjectStatus::FOR_LIQUIDATION,
                ProjectStatus::PARTIALLY_LIQUIDATED => 6,
                ProjectStatus::COMPLETED => 7,
                ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => 6,
            };
        }

        return match ($project->status) {
            ProjectStatus::ONGOING_PROFILING => 0,
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_COMPLIANCE => 1,
            ProjectStatus::FOR_APPROVAL => 2,
            ProjectStatus::APPROVED => 3,
            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION => 4,
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => 5,
            ProjectStatus::FOR_PAYMENT => 6,
            ProjectStatus::COMPLETED => 7,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED => 6,
        };
    }

    private function actionFor(Project $project, User $user): array
    {
        $shared = $this->sharedActionFor($project);

        if ($shared !== null) {
            return $shared;
        }

        return $project->implementation_mode === ImplementationMode::THROUGH_ACP
            ? $this->acpActionFor($project, $user)
            : $this->directActionFor($project, $user);
    }

    private function sharedActionFor(Project $project): ?array
    {
        return match ($project->status) {
            ProjectStatus::ONGOING_PROFILING => $this->internalAction(
                'Action Required',
                'Complete profiling and submit for TSSD evaluation',
                'Review the encoded profile first. Submit only when the official project information is complete.',
                'Review & Submit',
                'workflow',
                'evaluation',
            ),
            ProjectStatus::TSSD_EVALUATION => $this->internalAction(
                'Action Required',
                'Record the TSSD evaluation result',
                'Choose whether the project proceeds to approval or requires compliance and supporting documents.',
                'Record Evaluation',
                'workflow',
                'evaluation',
            ),
            ProjectStatus::FOR_COMPLIANCE => $this->internalAction(
                'Action Required',
                'Record project compliance',
                'Enter the compliance date after the required corrective documents or actions have been satisfied.',
                'Record Compliance',
                'workflow',
                'evaluation',
            ),
            ProjectStatus::FOR_APPROVAL => $this->internalAction(
                'Action Required',
                'Complete the project approval',
                'Assign the official project code and record the approval date and remarks.',
                'Approve Project',
                'workflow',
                'evaluation',
            ),
            ProjectStatus::COMPLETED => $this->internalAction(
                'Workflow Complete',
                'Project processing is complete',
                'Review the final project record, financial information, and status history as needed.',
                'Review Project Summary',
                'overview',
                'overview',
            ),
            default => null,
        };
    }

    private function directActionFor(Project $project, User $user): array
    {
        return match ($project->status) {
            ProjectStatus::APPROVED => $this->internalAction(
                'Action Required',
                'Complete pre-implementation requirements',
                'Record Insurance, PPE, and Notice to Proceed before scheduling implementation.',
                'Complete Requirements',
                'workflow',
                'implementation',
            ),
            ProjectStatus::FOR_IMPLEMENTATION => $this->internalAction(
                'Action Required',
                'Schedule and start implementation',
                'Record orientation and the official work period so implementation can proceed.',
                'Open Implementation',
                'workflow',
                'implementation',
            ),
            ProjectStatus::ONGOING_IMPLEMENTATION => $this->internalAction(
                'Current Stage',
                'Implementation is in progress',
                'Review the recorded implementation period and complete the project work before post-document submission.',
                'View Implementation',
                'workflow',
                'implementation',
            ),
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => $this->internalAction(
                'Action Required',
                'Submit post-documentary requirements',
                'Record the required completion documents before the project can move to wage payment.',
                'Submit Post Documents',
                'workflow',
                'post-documents',
            ),
            ProjectStatus::FOR_PAYMENT => ($user->isAdmin() || $user->isFocal())
                ? $this->externalAction(
                    'Action Required',
                    'Process payment of wages',
                    'Create or complete wage obligations and their corresponding disbursements.',
                    'Manage Payment of Wages',
                    route('payments.show', $project),
                    'financial',
                )
                : $this->internalAction(
                    'Pending Focal/Admin Action',
                    'Payment of wages is ready for processing',
                    'The project is waiting for a Focal or Administrator account to complete wage obligations and disbursements.',
                    'View Payment Status',
                    'financial',
                    'payment',
                ),
            default => $this->internalAction(
                'Workflow Status',
                $project->status->label(),
                'Review the current project workflow before continuing.',
                'Open Workflow',
                'workflow',
                'final-workflow',
            ),
        };
    }

    private function acpActionFor(Project $project, User $user): array
    {
        return match ($project->status) {
            ProjectStatus::APPROVED,
            ProjectStatus::FOR_PAYMENT,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT => ($user->isAdmin() || $user->isFocal())
                ? $this->externalAction(
                    'Action Required',
                    $project->status === ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT
                        ? 'Record the ACP check release'
                        : 'Process the Through ACP payment',
                    'Use the ACP payment workspace to record payment details and check release to the proponent.',
                    'Open ACP Payment & Check Release',
                    route('acp-payments.show', $project),
                    'financial',
                )
                : $this->internalAction(
                    'Pending Focal/Admin Action',
                    'ACP payment processing is pending',
                    'A Focal or Administrator account must complete the ACP payment and check-release stage.',
                    'View ACP Workflow',
                    'workflow',
                    'implementation',
                ),
            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION => ($user->isAdmin() || $user->isTc())
                ? $this->externalAction(
                    $project->status === ProjectStatus::FOR_IMPLEMENTATION ? 'Action Required' : 'Current Stage',
                    $project->status === ProjectStatus::FOR_IMPLEMENTATION
                        ? 'Record ACP implementation'
                        : 'ACP implementation is in progress',
                    'Open the ACP implementation workspace to record or review the official work period.',
                    'Open ACP Implementation',
                    route('acp-implementation.show', $project),
                    'workflow',
                )
                : $this->internalAction(
                    'Pending Admin/Coordinator Action',
                    'ACP implementation is awaiting processing',
                    'An Administrator or TUPAD Coordinator account must record the implementation details.',
                    'View ACP Workflow',
                    'workflow',
                    'implementation',
                ),
            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED => ($user->isAdmin() || $user->isFocal())
                ? $this->externalAction(
                    'Action Required',
                    $project->status === ProjectStatus::PARTIALLY_LIQUIDATED
                        ? 'Continue ACP liquidation'
                        : 'Process ACP liquidation',
                    'Record the submitted liquidation and validated amount until the project is fully liquidated.',
                    'Open ACP Liquidation',
                    route('acp-liquidations.show', $project),
                    'financial',
                )
                : $this->internalAction(
                    'Pending Focal/Admin Action',
                    'ACP liquidation is pending',
                    'A Focal or Administrator account must complete the liquidation stage.',
                    'View ACP Workflow',
                    'workflow',
                    'implementation',
                ),
            default => $this->internalAction(
                'Workflow Status',
                $project->status->label(),
                'Review the current Through ACP workflow before continuing.',
                'Open Workflow',
                'workflow',
                'final-workflow',
            ),
        };
    }

    private function internalAction(
        string $eyebrow,
        string $title,
        string $description,
        ?string $label,
        string $tab,
        ?string $anchor,
    ): array {
        return [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'description' => $description,
            'label' => $label,
            'href' => $anchor ? '#'.$anchor : null,
            'tab' => $tab,
            'anchor' => $anchor,
            'external' => false,
        ];
    }

    private function externalAction(
        string $eyebrow,
        string $title,
        string $description,
        string $label,
        string $href,
        string $tab,
    ): array {
        return [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'description' => $description,
            'label' => $label,
            'href' => $href,
            'tab' => $tab,
            'anchor' => null,
            'external' => true,
        ];
    }

    private function statusTone(ProjectStatus $status): string
    {
        return match ($status) {
            ProjectStatus::COMPLETED => 'success',
            ProjectStatus::FOR_COMPLIANCE,
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED => 'warning',
            default => 'info',
        };
    }
}
