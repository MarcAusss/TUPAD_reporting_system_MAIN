<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Gsis\GpaiEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Request GPAI Enrollment": asks the GSIS Enrollment Officer to enroll a
 * project before its approval. The project moves to the top of the officer's
 * Enroll Projects list and shows in their notifications. At most 3 presses
 * per project within any 3 hours.
 */
class ProjectGpaiEnrollmentRequestController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        GpaiEnrollmentService $gpai,
        ProvinceAccessService $provinceAccess,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(($user->isTc() || $user->isFocal() || $user->isAdmin()) && $provinceAccess->canAccessProject($user, $project), 403);

        if (! $gpai->isEligible($project)) {
            return back()->with('gpai_request_error', 'This project cannot be requested for GPAI enrollment: it is already enrolled, has no insurance budget, or has started work.');
        }

        $allowance = $gpai->requestAllowance($project);

        if ($allowance['remaining'] < 1) {
            return back()->with('gpai_request_error', sprintf(
                'GPAI enrollment was already requested %d times in the last %d hours. You can request again at %s.',
                GpaiEnrollmentService::REQUEST_LIMIT,
                GpaiEnrollmentService::REQUEST_WINDOW_HOURS,
                $allowance['resets_at']->format('g:i A'),
            ));
        }

        $project->gpaiEnrollmentRequests()->create(['requested_by' => $user->id]);

        return back()->with('success', sprintf(
            'The GSIS Enrollment Officer was notified to enroll this project. %d request(s) left in this %d-hour window.',
            $allowance['remaining'] - 1,
            GpaiEnrollmentService::REQUEST_WINDOW_HOURS,
        ));
    }
}
