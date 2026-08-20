<?php

namespace App\Enums;

enum ProjectDraftStatus: string
{
    case DRAFT = 'draft';
    case PENDING_TC_REVIEW = 'pending_tc_review';
    case RETURNED_FOR_CORRECTION = 'returned_for_correction';
    case CONFIRMED = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_TC_REVIEW => 'Pending TC Review',
            self::RETURNED_FOR_CORRECTION => 'Returned for Correction',
            self::CONFIRMED => 'Confirmed',
        };
    }
}