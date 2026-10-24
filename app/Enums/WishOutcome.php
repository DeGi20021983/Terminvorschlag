<?php

namespace App\Enums;

enum WishOutcome: string
{
    case Wish1 = 'wish1';
    case Wish2 = 'wish2';
    case Wish3 = 'wish3';
    case Alternative = 'alternative';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Wish1 => 'Erstwunsch',
            self::Wish2 => 'Zweitwunsch',
            self::Wish3 => 'Drittwunsch',
            self::Alternative => 'Alternative',
            self::None => 'Kein Termin',
        };
    }
}
