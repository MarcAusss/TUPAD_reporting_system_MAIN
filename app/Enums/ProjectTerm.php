<?php

namespace App\Enums;

enum ProjectTerm: string
{
    case SHORT_TERM = 'short_term';
    case LONG_TERM = 'long_term';

    public function label(): string
    {
        return match ($this) {
            self::SHORT_TERM => 'Short-Term',
            self::LONG_TERM => 'Long-Term',
        };
    }

    public static function fromDays(int $days): self
    {
        return match (true) {
            $days >= 10 && $days <= 30 => self::SHORT_TERM,
            $days >= 31 && $days <= 90 => self::LONG_TERM,
            default => throw new \InvalidArgumentException(
                'Project duration must be between 10 and 90 days.'
            ),
        };
    }
}