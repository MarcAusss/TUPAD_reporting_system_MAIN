<?php

namespace App\Enums;

enum PpeType: string
{
    case NON_HAZARDOUS = 'non_hazardous';
    case HAZARDOUS = 'hazardous';

    public function label(): string
    {
        return match ($this) {
            self::NON_HAZARDOUS => 'Non-Hazardous',
            self::HAZARDOUS => 'Hazardous',
        };
    }
}