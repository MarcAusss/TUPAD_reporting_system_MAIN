@extends('layouts.app')

@section('title', $project->project_title)

@section('content')

    @php

        if (auth()->user()->isFocal()) {
            if ($project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP) {
                $backUrl = route('projects.index');
                $backLabel = 'Project Registry';
            } else {
                $backUrl = route('payments.index');
                $backLabel = 'Payment Queue';
            }
        } else {
            $backUrl = route('projects.index');
            $backLabel = 'Project Management';
        }

        $canManageProject = auth()->user()->isAdmin() || auth()->user()->isTc();
        $projectImplementationStarted = $project->implementation()->exists() || $project->acpCheckRelease()->exists();
        $projectEditingLocked = $project->status === \App\Enums\ProjectStatus::COMPLETED;

        $canRecordInsuranceClaim =
            $canManageProject &&
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                    \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                    \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true,
            );

    @endphp

    @php
        $workspaceTabLabel = collect($workspace['tabs'])->firstWhere('key', $workspace['default_tab'])['label'] ?? 'Overview';
        $projectBreadcrumbs = \App\Support\Breadcrumbs::forProject($project, $workspaceTabLabel);
        $projectBreadcrumbs[array_key_last($projectBreadcrumbs)]['data'] = 'workspace-tab';
    @endphp

    <x-page-header eyebrow="Official Project" :title="$project->project_title" :breadcrumbs="$projectBreadcrumbs"
        description="Review the project profile, current workflow status, and the action required to move the project forward.">
        <x-slot:actions>
            <a href="{{ $backUrl }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← {{ $backLabel }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div data-project-workspace data-default-tab="{{ $workspace['default_tab'] }}" class="max-sm:pb-32">
        <x-project-workspace-header :project="$project" :workspace="$workspace" />

        @include('projects.partials.quick-workflow-action')

        @include('projects.partials.show.financial-summary')

        @include('projects.partials.show.financial-chart')

        @include('projects.partials.show.overview')

        {{-- Project & Workflow Records (one editable section per workflow step) --}}
        @include('projects.partials.workflow-records')

        @include('projects.partials.show.beneficiaries-wage')

        @include('projects.partials.show.beneficiary-replacement')

        @include('projects.partials.show.insurance-claim')

        @include('projects.partials.show.beneficiary-summary')

        @include('projects.partials.show.location-coverage')

        @include('projects.partials.show.beneficiary-classification')

        @include('projects.partials.show.evaluation')

        @include('projects.partials.show.evaluation-history')

        {{-- Implementation Preparation (Direct Administration, and Through ACP after the check release) --}}

        @php
            $implementationIsAcp = $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP;
            $acpWorkflowService = app(\App\Services\Projects\AcpWorkflowService::class);
            $acpPreparationComplete = $implementationIsAcp && $acpWorkflowService->preparationComplete($project);
        @endphp

        @include('projects.partials.show.implementation')

        @include('projects.partials.show.acp-workflow')

        @include('projects.partials.show.acp-release')

        @include('projects.partials.show.final-workflow')

        @include('projects.partials.show.post-documents')

        @include('projects.partials.show.release-of-assistance')

        @include('projects.partials.show.payment')
        @include('projects.partials.show.ppe-requirements')

        @include('projects.partials.show.replacement-history')

        @include('projects.partials.show.insurance-claim-history')

        @include('projects.partials.show.status-history')



    </div>

@endsection
