<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case ONGOING_PROFILING = 'ongoing_profiling';
    case TSSD_EVALUATION = 'tssd_evaluation';
    case FOR_COMPLIANCE = 'for_compliance';
    case FOR_APPROVAL = 'for_approval';
    case APPROVED = 'approved';
    case FOR_IMPLEMENTATION = 'for_implementation';
    case ONGOING_IMPLEMENTATION = 'ongoing_implementation';
    case FOR_SUBMISSION_OF_POST_DOCS = 'for_submission_of_post_docs';
    case FOR_PAYMENT = 'for_payment';
    case FOR_RELEASE_OF_CHECK_TO_PROPONENT = 'for_release_of_check_to_proponent';
    case FOR_LIQUIDATION = 'for_liquidation';
    case PARTIALLY_LIQUIDATED = 'partially_liquidated';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::ONGOING_PROFILING => 'Ongoing Profiling',
            self::TSSD_EVALUATION => 'TSSD Evaluation',
            self::FOR_COMPLIANCE => 'For Compliance',
            self::FOR_APPROVAL => 'For Approval',
            self::APPROVED => 'Approved',
            self::FOR_IMPLEMENTATION => 'For Implementation',
            self::ONGOING_IMPLEMENTATION => 'Ongoing Implementation',
            self::FOR_SUBMISSION_OF_POST_DOCS => 'For Submission of Post-Docs',
            self::FOR_PAYMENT => 'For Payment',
            self::FOR_RELEASE_OF_CHECK_TO_PROPONENT => 'For Release of Check to Proponent',
            self::FOR_LIQUIDATION => 'For Liquidation',
            self::PARTIALLY_LIQUIDATED => 'Partially Liquidated',
            self::COMPLETED => 'Completed',
        };
    }
}
