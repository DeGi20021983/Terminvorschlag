<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Offer;
use Illuminate\Support\Collection;

final readonly class OfferCancelled
{
    /**
     * @param  Collection<int, Booking>  $cancelledBookings  Vor der Absage bestätigte Buchungen
     */
    public function __construct(
        public Offer $offer,
        public Collection $cancelledBookings,
    ) {}
}
