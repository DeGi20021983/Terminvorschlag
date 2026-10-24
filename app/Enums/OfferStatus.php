<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Published => 'Veröffentlicht',
            self::Cancelled => 'Abgesagt',
        };
    }
}
