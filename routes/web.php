<?php

use App\Http\Controllers\AdlAllocationController;
use App\Http\Controllers\AdlController;
use App\Http\Controllers\AdlRealignmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProjectApprovalController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDraftController;
use App\Http\Controllers\ProjectDraftReviewController;
use App\Http\Controllers\ProjectEvaluationController;
use App\Http\Controllers\ProjectImplementationController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ProjectPayoutController;
use App\Http\Controllers\ProjectPostDocumentController;
use App\Http\Controllers\ProjectWorkflowQueueController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FundMonitoringController;
use App\Http\Controllers\ProvinceMonitoringController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('dashboard'));

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/search', [GlobalSearchController::class, 'index'])
        ->name('search.index');


    /*
|--------------------------------------------------------------------------
| Fund Monitoring — Admin & Focal
|--------------------------------------------------------------------------
*/

    Route::middleware('role:admin,focal')->group(function () {

        Route::get(
            '/fund-monitoring/per-adl-current',
            [FundMonitoringController::class, 'perAdl']
        )->name('fund-monitoring.per-adl-current');

        Route::get(
            '/fund-monitoring/summary',
            [FundMonitoringController::class, 'summary']
        )->name('fund-monitoring.summary');

        Route::get(
            '/fund-monitoring/summary-current',
            [FundMonitoringController::class, 'summaryCurrent']
        )->name('fund-monitoring.summary-current');

        Route::get(
            '/fund-monitoring/per-province-current',
            [FundMonitoringController::class, 'perProvince']
        )->name('fund-monitoring.per-province-current');
    });


    /*
    |--------------------------------------------------------------------------
    | Provincial Monitoring — Admin & TC
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc')->group(function () {

        Route::get(
            '/project-monitoring/{province}',
            [ProvinceMonitoringController::class, 'index']
        )->name('project-monitoring.province');

        Route::get(
            '/projects/{project}/monitoring/edit',
            [ProvinceMonitoringController::class, 'edit']
        )
            ->whereNumber('project')
            ->name('projects.monitoring.edit');

        Route::put(
            '/projects/{project}/monitoring',
            [ProvinceMonitoringController::class, 'update']
        )
            ->whereNumber('project')
            ->name('projects.monitoring.update');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc,focal')->group(function () {

        Route::get(
            '/reports',
            [ReportController::class, 'index']
        )->name('reports.index');

        Route::get(
            '/reports/export/csv',
            [ReportController::class, 'exportCsv']
        )->name('reports.export.csv');

        Route::get(
            '/reports/print',
            [ReportController::class, 'print']
        )->name('reports.print');
    });

    /*
    |--------------------------------------------------------------------------
    | Geographic Reference Data
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/locations/provinces/{province}/districts',
        [LocationController::class, 'districts']
    )->name('locations.districts');

    Route::get(
        '/locations/provinces/{province}/municipalities',
        [LocationController::class, 'municipalities']
    )->name('locations.municipalities');

    Route::get(
        '/locations/municipalities/{municipality}/barangays',
        [LocationController::class, 'barangays']
    )->name('locations.barangays');

    /*
    |--------------------------------------------------------------------------
    | Administrator
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', fn() => 'User Management')
            ->name('users.index');
    });

    /*
    |--------------------------------------------------------------------------
    | ADL / Payment — Admin & Focal
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,focal')->group(function () {

        Route::get('/adl', [AdlController::class, 'index'])
            ->name('adl.index');

        Route::get('/adl/create', [AdlController::class, 'create'])
            ->name('adl.create');

        Route::post('/adl', [AdlController::class, 'store'])
            ->name('adl.store');

        Route::get('/adl/{adl}', [AdlController::class, 'show'])
            ->name('adl.show');

        Route::get('/adl/{adl}/edit', [AdlController::class, 'edit'])
            ->name('adl.edit');

        Route::put('/adl/{adl}', [AdlController::class, 'update'])
            ->name('adl.update');

        Route::post('/adl/{adl}/realignments', [AdlRealignmentController::class, 'store'])
            ->name('adl.realignments.store');

        Route::post('/adl/{adl}/allocations', [AdlAllocationController::class, 'store'])
            ->name('adl.allocations.store');

        Route::get('/payments', [ProjectController::class, 'paymentQueue'])
            ->name('payments.index');

        Route::post('/projects/{project}/payment', [ProjectPaymentController::class, 'store'])
            ->name('projects.payment.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Official Project Workflow — Admin & TC
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc')->group(function () {

        Route::get('/projects', [ProjectController::class, 'index'])
            ->name('projects.index');

        /*
        |--------------------------------------------------------------------------
        | Discoverable Project Workflow Queues
        |--------------------------------------------------------------------------
        |
        | These are filtered views of the SAME official project records.
        |
        */

        Route::get(
            '/project-workflow/{queue}',
            [ProjectWorkflowQueueController::class, 'index']
        )
            ->whereIn('queue', [
                'tssd-evaluation',
                'for-approval',
                'implementation',
                'post-documents',
                'release-of-assistance',
            ])
            ->name('project-workflow.index');

        /*
        | IMPORTANT:
        | Static routes must always come before /projects/{project}
        */

        Route::get('/projects/create', [ProjectController::class, 'create'])
            ->name('projects.create');

        Route::post('/projects', [ProjectController::class, 'store'])
            ->name('projects.store');

        Route::post('/projects/{project}/evaluation/start', [ProjectEvaluationController::class, 'start'])
            ->name('projects.evaluation.start');

        Route::post('/projects/{project}/evaluation', [ProjectEvaluationController::class, 'store'])
            ->name('projects.evaluation.store');

        Route::post('/projects/{project}/evaluation/resubmit', [ProjectEvaluationController::class, 'resubmit'])
            ->name('projects.evaluation.resubmit');

        Route::post('/projects/{project}/approval', [ProjectApprovalController::class, 'store'])
            ->name('projects.approval.store');

        Route::post('/projects/{project}/implementation/requirements', [ProjectImplementationController::class, 'preparationRequirements'])
            ->name('projects.implementation.requirements');

        Route::post('/projects/{project}/implementation/insurance', [ProjectImplementationController::class, 'insurance'])
            ->name('projects.implementation.insurance');

        Route::post('/projects/{project}/implementation/ppe', [ProjectImplementationController::class, 'ppe'])
            ->name('projects.implementation.ppe');

        Route::post('/projects/{project}/implementation/notice-to-proceed', [ProjectImplementationController::class, 'noticeToProceed'])
            ->name('projects.implementation.ntp');

        Route::post('/projects/{project}/implementation/orientation', [ProjectImplementationController::class, 'orientation'])
            ->name('projects.implementation.orientation');

        Route::post('/projects/{project}/implementation/period', [ProjectImplementationController::class, 'implementationPeriod'])
            ->name('projects.implementation.period');

        Route::post('/projects/{project}/post-documents', [ProjectPostDocumentController::class, 'store'])
            ->name('projects.post-documents.store');

        Route::post('/projects/{project}/payout', [ProjectPayoutController::class, 'store'])
            ->name('projects.payout.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Secure Post-Document Download
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc,focal')->group(function () {
        Route::get(
            '/projects/{project}/post-documents/{projectPostDocument}/download',
            [ProjectPostDocumentController::class, 'download']
        )->name('projects.post-documents.download');
    });

    /*
    |--------------------------------------------------------------------------
    | GIP Draft Encoding
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:gip')->group(function () {

        Route::get('/project-drafts', [ProjectDraftController::class, 'index'])
            ->name('project-drafts.index');

        Route::get('/project-drafts/create', [ProjectDraftController::class, 'create'])
            ->name('project-drafts.create');

        Route::post('/project-drafts', [ProjectDraftController::class, 'store'])
            ->name('project-drafts.store');

        Route::get('/project-drafts/{projectDraft}', [ProjectDraftController::class, 'show'])
            ->name('project-drafts.show');

        Route::get('/project-drafts/{projectDraft}/edit', [ProjectDraftController::class, 'edit'])
            ->name('project-drafts.edit');

        Route::put('/project-drafts/{projectDraft}', [ProjectDraftController::class, 'update'])
            ->name('project-drafts.update');

        Route::post('/project-drafts/{projectDraft}/submit', [ProjectDraftController::class, 'submit'])
            ->name('project-drafts.submit');
    });

    /*
    |--------------------------------------------------------------------------
    | GIP Draft Review — Admin & TC
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,tc')->group(function () {

        Route::get('/project-draft-reviews', [ProjectDraftReviewController::class, 'index'])
            ->name('project-draft-reviews.index');

        Route::get('/project-draft-reviews/{projectDraft}', [ProjectDraftReviewController::class, 'show'])
            ->name('project-draft-reviews.show');

        Route::post('/project-draft-reviews/{projectDraft}/return', [ProjectDraftReviewController::class, 'returnForCorrection'])
            ->name('project-draft-reviews.return');

        Route::post('/project-draft-reviews/{projectDraft}/confirm', [ProjectDraftReviewController::class, 'confirm'])
            ->name('project-draft-reviews.confirm');
    });

    /*
    |--------------------------------------------------------------------------
    | Shared Official Project Detail
    |--------------------------------------------------------------------------
    |
    | This MUST remain after /projects/create.
    | whereNumber prevents "create" from being interpreted as a project ID.
    |
    */

    Route::get('/projects/{project}', [ProjectController::class, 'show'])
        ->whereNumber('project')
        ->name('projects.show');
});