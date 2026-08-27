<?php

namespace App\Providers;

use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AdlRealignment;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectBeneficiarySector;
use App\Models\ProjectDraft;
use App\Models\ProjectDisbursement;
use App\Models\ProjectEvaluation;
use App\Models\ProjectImplementation;
use App\Models\ProjectInsuranceEnrollment;
use App\Models\ProjectLaborMarketReferral;
use App\Models\ProjectNoticeToProceed;
use App\Models\ProjectObligation;
use App\Models\ProjectOrientation;
use App\Models\ProjectPayout;
use App\Models\ProjectPostDocument;
use App\Models\ProjectPpeDelivery;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\ProjectObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\ProjectBeneficiary;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Project Status History
        |--------------------------------------------------------------------------
        */

        Project::observe(
            ProjectObserver::class
        );

        /*
        |--------------------------------------------------------------------------
        | System Audit Logging
        |--------------------------------------------------------------------------
        */

        $auditedModels = [
            Adl::class,
            AdlAllocation::class,
            AdlRealignment::class,

            Project::class,
            ProjectDraft::class,
            ProjectEvaluation::class,
            ProjectApproval::class,

            ProjectInsuranceEnrollment::class,
            ProjectPpeDelivery::class,
            ProjectNoticeToProceed::class,
            ProjectOrientation::class,
            ProjectImplementation::class,

            ProjectPostDocument::class,
            ProjectObligation::class,
            ProjectDisbursement::class,
            ProjectPayout::class,

            ProjectBeneficiarySector::class,
            ProjectLaborMarketReferral::class,

            User::class,
            ProjectBeneficiary::class,
        ];

        foreach ($auditedModels as $model) {
            $model::observe(
                AuditObserver::class
            );
        }
    }
}
