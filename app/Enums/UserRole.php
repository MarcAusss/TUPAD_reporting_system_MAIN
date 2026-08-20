<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TC = 'tc';
    case GIP = 'gip';
    case FOCAL = 'focal';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::TC => 'TUPAD Coordinator',
            self::GIP => 'GIP',
            self::FOCAL => 'Focal',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::TC => 'TC',
            self::GIP => 'GIP',
            self::FOCAL => 'Focal',
        };
    }
}