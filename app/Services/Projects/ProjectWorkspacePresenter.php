<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\Payments\ProjectPaymentService;

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

    /**
     * Compact progress snapshot used outside the project page (e.g. notifications).
     *
     * @return array{percent:int,stage_label:string,stage_number:int,stage_count:int}
     */
    public function progressSummary(Project $project): array
    {
        $stages = $this->stagesFor($project);
        $currentStageIndex = $this->currentStageIndex($project);

        return [
            'percent' => (int) round(($currentStageIndex / max(count($stages) - 1, 1)) * 100),
            'stage_label' => (string) ($stages[$currentStageIndex]['label'] ?? ''),
            'stage_number' => $currentStageIndex + 1,
            'stage_count' => count($stages),
        ];
    }

    private function stagesFor(Project $project): array
    {
        $definitions = $project->implementation_mode === ImplementationMode::THROUGH_ACP
            ? [
                ['label' => 'Evaluation', 'tab' => 'workflow', 'anchor' => 'evaluation'],
                ['label' => 'Approval', 'tab' => 'workflow', 'anchor' => 'evaluation'],
                ['label' => 'ACP Payment', 'href' => route('acp-payments.show', $project)],
                ['label' => 'Check Release', 'href' => route('acp-payments.show', $project)],
                ['label' => 'Implementation', 'href' => route('acp-implementation.show', $project)],
                ['label' => 'Liquidation', 'href' => route('acp-liquidations.show', $project)],
                ['label' => 'Completed', 'tab' => 'overview'],
            ]
            : [
                ['label' => 'Evaluation', 'tab' => 'workflow', 'anchor' => 'evaluation'],
                ['label' => 'Approval', 'tab' => 'workflow', 'anchor' => 'evaluation'],
                ['label' => 'Preparation', 'tab' => 'workflow', 'anchor' => 'implementation', 'step' => 'insurance'],
                ['label' => 'Implementation', 'tab' => 'workflow', 'anchor' => 'implementation', 'step' => 'orientation'],
                ['label' => 'Post Documents', 'tab' => 'workflow', 'anchor' => 'post-documents'],
                ['label' => 'Payment', 'tab' => 'financial', 'anchor' => 'payment'],
                ['label' => 'Completed', 'tab' => 'overview'],
            ];

        return collect($definitions)
            ->map(fn (array $definition, int $index): array => [
                'key' => str((string) ($index + 1).'-'.$definition['label'])->slug()->toString(),
                'label' => $definition['label'],
                'state' => 'upcoming',
                'tab' => $definition['tab'] ?? null,
                'anchor' => $definition['anchor'] ?? null,
                'step' => $definition['step'] ?? null,
                'href' => $definition['href'] ?? null,
            ])
            ->all();
    }

    private function currentStageIndex(Project $project): int
    {
        if ($project->implementation_mode === ImplementationMode::THROUGH_ACP) {
            return match ($project->status) {
                ProjectStatus::TSSD_EVALUATION,
                ProjectStatus::FOR_COMPLIANCE => 0,
                ProjectStatus::FOR_APPROVAL => 1,
                ProjectStatus::APPROVED,
                ProjectStatus::FOR_PAYMENT => 2,
                ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT => 3,
                ProjectStatus::FOR_IMPLEMENTATION,
                ProjectStatus::ONGOING_IMPLEMENTATION => 4,
                ProjectStatus::FOR_LIQUIDATION,
                ProjectStatus::PARTIALLY_LIQUIDATED => 5,
                ProjectStatus::COMPLETED => 6,
                ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => 5,
            };
        }

        return match ($project->status) {
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_COMPLIANCE => 0,
            ProjectStatus::FOR_APPROVAL => 1,
            ProjectStatus::APPROVED => 2,
            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION => 3,
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => 4,
            ProjectStatus::FOR_PAYMENT => 5,
            ProjectStatus::COMPLETED => 6,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED => 5,
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
            ProjectStatus::FOR_PAYMENT => $this->directPaymentActionFor($project, $user),
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

    /**
     * For Payment has three sub-steps: Focal/Admin obligation tranches
     * (ending with Complete), the TUPAD Coordinator's Release of Assistance,
     * then waiting for full disbursement and the payout date.
     */
    private function directPaymentActionFor(Project $project, User $user): array
    {
        $canManagePayment = $user->isAdmin() || $user->isFocal();
        $canRecordRelease = $user->isAdmin() || $user->isTc();

        if ($project->obligations_completed_at === null) {
            return $canManagePayment
                ? $this->externalAction(
                    'Action Required',
                    'Process payment of wages',
                    'Encode the obligation tranches, then click Complete once they equal the Total Project Cost.',
                    'Manage Payment of Wages',
                    route('payments.show', $project),
                    'financial',
                )
                : $this->internalAction(
                    'Pending Focal/Admin Action',
                    'Obligation tranches are being processed',
                    'The project is waiting for a Focal or Administrator account to complete the obligation tranches.',
                    'View Payment Status',
                    'financial',
                    'payment',
                );
        }

        $project->loadMissing('payout');

        if ($project->payout === null) {
            return $canRecordRelease
                ? $this->internalAction(
                    'Action Required',
                    'Record the Release of Assistance',
                    'Obligation tranches are complete. Record the mode of payment, date of payout, and venue.',
                    'Record Release of Assistance',
                    'workflow',
                    'release-of-assistance',
                )
                : $this->internalAction(
                    'Pending TUPAD Coordinator Action',
                    'Waiting for the Release of Assistance',
                    'Obligation tranches are complete. The TUPAD Coordinator records the mode of payment, date of payout, and venue.',
                    'View Release of Assistance',
                    'workflow',
                    'release-of-assistance',
                );
        }

        $paymentService = app(ProjectPaymentService::class);

        if (! $paymentService->summary($project)['is_fully_paid']) {
            return $canManagePayment
                ? $this->externalAction(
                    'Action Required',
                    'Record the remaining disbursements',
                    'The project completes once the full project cost is disbursed and the payout date is reached.',
                    'Manage Payment of Wages',
                    route('payments.show', $project),
                    'financial',
                )
                : $this->internalAction(
                    'Pending Focal/Admin Action',
                    'Waiting for full disbursement',
                    'The Release of Assistance is recorded. The project completes once the full project cost is disbursed and the payout date is reached.',
                    'View Payment Status',
                    'financial',
                    'payment',
                );
        }

        return $this->internalAction(
            'Current Stage',
            'Waiting for the payout date',
            sprintf(
                'All requirements are recorded. The project completes automatically on the payout date (%s).',
                $project->payout->payout_date->format('F d, Y'),
            ),
            'View Release of Assistance',
            'workflow',
            'release-of-assistance',
        );
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
            ProjectStatus::PARTIALLY_LIQUIDATED => ($user->isAdmin() || $user->isFocal() || $user->isTc())
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
                    'Pending Coordinator/Focal/Admin Action',
                    'ACP liquidation is pending',
                    'A TUPAD Coordinator, Focal, or Administrator account must complete the liquidation stage.',
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
