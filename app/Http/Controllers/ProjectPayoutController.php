<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectPayoutController extends Controller
{
    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_PAYMENT
        ) {
            abort(
                403,
                'Payout may only be recorded for projects with For Payment status.'
            );
        }

        if (!$project->obligation()->exists()) {
            return back()->withErrors([
                'payout_date' =>
                    'Payment/obligation information must be recorded before payout.',
            ]);
        }

        if ($project->payout()->exists()) {
            return back()->withErrors([
                'payout_date' =>
                    'This project already has a payout record.',
            ]);
        }

        $validated = $request->validate([
            'payout_date' => [
                'required',
                'date',
            ],

            'payout_mode' => [
                'required',
                'string',
                'max:100',
            ],

            'venue' => [
                'required',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        return DB::transaction(function () use ($request, $project, $validated) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail(
                    $project->id
                );

            if (
                $lockedProject->status
                !== ProjectStatus::FOR_PAYMENT
            ) {
                return back()->withErrors([
                    'payout_date' =>
                        'The project is no longer available for payout.',
                ]);
            }

            if (!$lockedProject->obligation()->exists()) {
                return back()->withErrors([
                    'payout_date' =>
                        'Payment/obligation information must exist before payout.',
                ]);
            }

            if ($lockedProject->payout()->exists()) {
                return back()->withErrors([
                    'payout_date' =>
                        'This project already has a payout record.',
                ]);
            }

            $lockedProject->payout()->create([
                'payout_date' =>
                    $validated['payout_date'],

                'payout_mode' =>
                    trim($validated['payout_mode']),

                'venue' =>
                    trim($validated['venue']),

                'remarks' =>
                    $validated['remarks'] ?? null,

                'recorded_by' =>
                    $request->user()->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Final Completion
            |--------------------------------------------------------------------------
            |
            | Initial rule:
            |
            | Post-docs completed
            | + Obligation/payment exists
            | + Payout exists
            | = Completed
            |
            */

            $lockedProject->update([
                'status' =>
                    ProjectStatus::COMPLETED,

                'updated_by' =>
                    $request->user()->id,
            ]);

            return redirect()
                ->route(
                    'projects.show',
                    $lockedProject
                )
                ->with(
                    'success',
                    'Payout recorded successfully. Project marked as Completed.'
                );
        });
    }
}