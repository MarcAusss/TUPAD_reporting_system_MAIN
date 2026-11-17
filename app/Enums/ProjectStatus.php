<?php

namespace App\Enums;

enum ProjectStatus: string
{
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

    /**
     * Pill colours per workflow phase (Tailwind classes), used by <x-project-status>.
     */
    public function pillClasses(): string
    {
        return match ($this) {
            self::TSSD_EVALUATION, self::FOR_APPROVAL => 'bg-indigo-50 text-indigo-700 ring-indigo-600/15',
            self::FOR_COMPLIANCE => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::APPROVED, self::FOR_IMPLEMENTATION => 'bg-sky-50 text-sky-700 ring-sky-600/15',
            self::ONGOING_IMPLEMENTATION => 'bg-violet-50 text-violet-700 ring-violet-600/15',
            self::FOR_SUBMISSION_OF_POST_DOCS => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            self::FOR_PAYMENT, self::FOR_RELEASE_OF_CHECK_TO_PROPONENT => 'bg-teal-50 text-teal-700 ring-teal-600/15',
            self::FOR_LIQUIDATION, self::PARTIALLY_LIQUIDATED => 'bg-rose-50 text-rose-700 ring-rose-600/15',
            self::COMPLETED => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::TSSD_EVALUATION, self::FOR_APPROVAL => 'bg-indigo-500',
            self::FOR_COMPLIANCE => 'bg-amber-500',
            self::APPROVED, self::FOR_IMPLEMENTATION => 'bg-sky-500',
            self::ONGOING_IMPLEMENTATION => 'bg-violet-500',
            self::FOR_SUBMISSION_OF_POST_DOCS => 'bg-orange-500',
            self::FOR_PAYMENT, self::FOR_RELEASE_OF_CHECK_TO_PROPONENT => 'bg-teal-500',
            self::FOR_LIQUIDATION, self::PARTIALLY_LIQUIDATED => 'bg-rose-500',
            self::COMPLETED => 'bg-emerald-500',
        };
    }
}