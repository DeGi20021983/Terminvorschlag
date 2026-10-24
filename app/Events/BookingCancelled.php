<?php

namespace App\Events;

use App\Models\Booking;

final readonly class BookingCancelled
{
    /**
     * @param  string  $reason  'self', 'organizer' oder 'offer_cancelled'
     */
    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}
}
