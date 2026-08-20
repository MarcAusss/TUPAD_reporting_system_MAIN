<?php

namespace App\Enums;

enum ImplementationMode: string
{
    case DIRECT_ADMINISTRATION = 'direct_administration';
    case THROUGH_ACP = 'through_acp';

    public function label(): string
    {
        return match ($this) {
            self::DIRECT_ADMINISTRATION => 'Direct Administration',
            self::THROUGH_ACP => 'Through ACP',
        };
    }
}