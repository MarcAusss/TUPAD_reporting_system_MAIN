<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdlAllocationController;
use App\Http\Controllers\AdlController;
use App\Http\Controllers\AdlRealignmentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDraftController;
use App\Http\Controllers\ProjectDraftReviewController;
use App\Http\Controllers\ProjectApprovalController;
use App\Http\Controllers\ProjectEvaluationController;
use App\Http\Controllers\ProjectImplementationController;


Route::middleware('guest')->group(function () {
    Route::get(
        '/login',
        [AuthenticatedSessionController::class, 'create']
    )->name('login');

    Route::post(
        '/login',
        [AuthenticatedSessionController::class, 'store']
    )->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->name('dashboard');

    Route::post(
        '/logout',
        [AuthenticatedSessionController::class, 'destroy']
    )->name('logout');



    /*
    |--------------------------------------------------------------------------
    | Administrator Only
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', function () {
            return 'User Management - Phase 2';
        })->name('users.index');
    });

    /*
    |--------------------------------------------------------------------------
    | TC Accessible
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc')->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Project Evaluation
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/projects/{project}/evaluation/start',
            [ProjectEvaluationController::class, 'start']
        )->name('projects.evaluation.start');

        Route::post(
            '/projects/{project}/evaluation',
            [ProjectEvaluationController::class, 'store']
        )->name('projects.evaluation.store');

        Route::post(
            '/projects/{project}/evaluation/resubmit',
            [ProjectEvaluationController::class, 'resubmit']
        )->name('projects.evaluation.resubmit');

        /*
        |--------------------------------------------------------------------------
        | Project Approval
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/projects/{project}/approval',
            [ProjectApprovalController::class, 'store']
        )->name('projects.approval.store');

        /*
|--------------------------------------------------------------------------
| Implementation Preparation
|--------------------------------------------------------------------------
*/

        Route::post(
            '/projects/{project}/implementation/insurance',
            [ProjectImplementationController::class, 'insurance']
        )->name('projects.implementation.insurance');

        Route::post(
            '/projects/{project}/implementation/ppe',
            [ProjectImplementationController::class, 'ppe']
        )->name('projects.implementation.ppe');

        Route::post(
            '/projects/{project}/implementation/notice-to-proceed',
            [ProjectImplementationController::class, 'noticeToProceed']
        )->name('projects.implementation.ntp');

        Route::post(
            '/projects/{project}/implementation/orientation',
            [ProjectImplementationController::class, 'orientation']
        )->name('projects.implementation.orientation');

        Route::post(
            '/projects/{project}/implementation/period',
            [ProjectImplementationController::class, 'implementationPeriod']
        )->name('projects.implementation.period');
    });

    /*
|--------------------------------------------------------------------------
| Official Project Management
|--------------------------------------------------------------------------
|
| Only Administrator and TC may create official project records.
| GIP draft encoding will be implemented separately in Phase 5.
|
*/

    Route::middleware('role:admin,tc')->group(function () {
        Route::get(
            '/projects',
            [ProjectController::class, 'index']
        )->name('projects.index');

        Route::get(
            '/projects/create',
            [ProjectController::class, 'create']
        )->name('projects.create');

        Route::post(
            '/projects',
            [ProjectController::class, 'store']
        )->name('projects.store');

        Route::get(
            '/projects/{project}',
            [ProjectController::class, 'show']
        )->name('projects.show');
    });

    /*
|--------------------------------------------------------------------------
| GIP Project Drafts
|--------------------------------------------------------------------------
*/

    Route::middleware('role:gip')->group(function () {

        Route::get(
            '/project-drafts',
            [ProjectDraftController::class, 'index']
        )->name('project-drafts.index');

        Route::get(
            '/project-drafts/create',
            [ProjectDraftController::class, 'create']
        )->name('project-drafts.create');

        Route::post(
            '/project-drafts',
            [ProjectDraftController::class, 'store']
        )->name('project-drafts.store');

        Route::get(
            '/project-drafts/{projectDraft}',
            [ProjectDraftController::class, 'show']
        )->name('project-drafts.show');

        Route::get(
            '/project-drafts/{projectDraft}/edit',
            [ProjectDraftController::class, 'edit']
        )->name('project-drafts.edit');

        Route::put(
            '/project-drafts/{projectDraft}',
            [ProjectDraftController::class, 'update']
        )->name('project-drafts.update');

        Route::post(
            '/project-drafts/{projectDraft}/submit',
            [ProjectDraftController::class, 'submit']
        )->name('project-drafts.submit');

    });

    /*
    |--------------------------------------------------------------------------
    | TC / Administrator Draft Review
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc')->group(function () {

        Route::get(
            '/project-draft-reviews',
            [ProjectDraftReviewController::class, 'index']
        )->name('project-draft-reviews.index');

        Route::get(
            '/project-draft-reviews/{projectDraft}',
            [ProjectDraftReviewController::class, 'show']
        )->name('project-draft-reviews.show');

        Route::post(
            '/project-draft-reviews/{projectDraft}/return',
            [ProjectDraftReviewController::class, 'returnForCorrection']
        )->name('project-draft-reviews.return');

        Route::post(
            '/project-draft-reviews/{projectDraft}/confirm',
            [ProjectDraftReviewController::class, 'confirm']
        )->name('project-draft-reviews.confirm');

    });

    /*
    |--------------------------------------------------------------------------
    | Focal Accessible
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,focal')->group(function () {
        Route::middleware('role:admin,focal')->group(function () {

            Route::get(
                '/adl',
                [AdlController::class, 'index']
            )->name('adl.index');

            Route::get(
                '/adl/create',
                [AdlController::class, 'create']
            )->name('adl.create');

            Route::post(
                '/adl',
                [AdlController::class, 'store']
            )->name('adl.store');

            Route::get(
                '/adl/{adl}',
                [AdlController::class, 'show']
            )->name('adl.show');

            Route::get(
                '/adl/{adl}/edit',
                [AdlController::class, 'edit']
            )->name('adl.edit');

            Route::put(
                '/adl/{adl}',
                [AdlController::class, 'update']
            )->name('adl.update');

            Route::post(
                '/adl/{adl}/realignments',
                [AdlRealignmentController::class, 'store']
            )->name('adl.realignments.store');

            Route::post(
                '/adl/{adl}/allocations',
                [AdlAllocationController::class, 'store']
            )->name('adl.allocations.store');

        });
    });
});