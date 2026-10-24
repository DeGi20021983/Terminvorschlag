<?php

namespace App\Events;

use App\Models\Offer;

/**
 * Angebot geändert oder Termin verlegt. $changes enthält nur Attributnamen, keine Personendaten.
 */
final readonly class OfferChanged
{
    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public Offer $offer,
        public array $changes,
    ) {}
}
