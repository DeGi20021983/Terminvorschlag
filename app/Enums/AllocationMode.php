<?php

namespace App\Enums;

enum AllocationMode: string
{
    case Instant = 'instant';
    case Wishes = 'wishes';

    public function label(): string
    {
        return match ($this) {
            self::Instant => 'Sofortbuchung',
            self::Wishes => 'Wunschtermine',
        };
    }
}
