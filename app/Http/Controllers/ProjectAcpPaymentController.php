<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectAcpPaymentController extends Controller
{
    public function show(Project $project): View
    {
        $this->ensureAcp($project);

        if (! in_array($project->status, [
            ProjectStatus::FOR_PAYMENT,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            ProjectStatus::FOR_IMPLEMENTATION,
        ], true)) {
            abort(403);
        }

        $project->load([
            'allocation.adl',
            'approval',
            'acpPayment.recorder',
            'acpCheckRelease.recorder',
            'acpCheckRelease.attachments',
        ]);

        return view('acp-payments.show', compact('project'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureAcp($project);

        if ($project->status !== ProjectStatus::FOR_PAYMENT) {
            abort(403, 'ACP payment processing is only available for projects with For Payment status.');
        }

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payee' => ['required', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $project, $validated): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);

            if (
                $locked->implementation_mode !== ImplementationMode::THROUGH_ACP
                || $locked->status !== ProjectStatus::FOR_PAYMENT
            ) {
                throw ValidationException::withMessages([
                    'payment_date' => 'This Through ACP project is no longer available for payment processing.',
                ]);
            }

            if ($locked->acpPayment()->exists()) {
                throw ValidationException::withMessages([
                    'payment_date' => 'This project already has a Through ACP payment record.',
                ]);
            }

            $approval = $locked->approval()->first();
            if (
                $approval
                && $approval->approval_date
                && $approval->approval_date->gt($validated['payment_date'])
            ) {
                throw ValidationException::withMessages([
                    'payment_date' => 'The ACP payment date cannot be earlier than the project approval date.',
                ]);
            }

            // Official amount is derived from the approved project record, never from browser input.
            $amount = (string) $locked->total_project_cost;

            if ((float) $amount <= 0) {
                throw ValidationException::withMessages([
                    'payment_date' => 'The approved project cost must be greater than zero before ACP payment can be recorded.',
                ]);
            }

            $locked->acpPayment()->create([
                'amount' => $amount,
                'payment_date' => $validated['payment_date'],
                'payee' => trim($validated['payee']),
                'payment_reference' => filled($validated['payment_reference'] ?? null)
                    ? trim($validated['payment_reference'])
                    : null,
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            $locked->setStatusTransitionContext(
                actorId: (int) $request->user()->id,
                remarks: 'Through ACP payment was recorded using the approved project cost. Project moved to For Release of Check to Proponent.',
            )->update([
                'status' => ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                'updated_by' => $request->user()->id,
            ]);

            $locked->clearStatusTransitionContext();
        });

        return redirect()
            ->route('acp-payments.show', $project)
            ->with('success', 'Through ACP payment recorded. Project moved to For Release of Check to Proponent.');
    }

    private function ensureAcp(Project $project): void
    {
        if ($project->implementation_mode !== ImplementationMode::THROUGH_ACP) {
            abort(403, 'This interface applies only to Through ACP projects.');
        }
    }
}
