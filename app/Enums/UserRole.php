<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TC = 'tc';
    case FOCAL = 'focal';
    case RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::TC => 'TUPAD Coordinator',
            self::FOCAL => 'Focal',
            self::RETIRED => 'Retired Account',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::TC => 'TC',
            self::FOCAL => 'Focal',
            self::RETIRED => 'Retired',
        };
    }

    /** @return array<int,self> */
    public static function assignable(): array
    {
        return [self::ADMIN, self::FOCAL, self::TC];
    }
}
