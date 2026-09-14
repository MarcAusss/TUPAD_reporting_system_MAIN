<?php

namespace App\Enums;

enum BeneficiaryReplacementRole: string
{
    case REMOVED = 'removed';
    case ADDED = 'added';

    public function label(): string
    {
        return match ($this) {
            self::REMOVED => 'Removed',
            self::ADDED => 'Added',
        };
    }
}
