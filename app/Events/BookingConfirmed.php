<?php

namespace App\Events;

use App\Models\Booking;

/**
 * Wird nach dem Commit ausgelöst. Benachrichtigungen hängen sich hier ein (Meilenstein f).
 */
final readonly class BookingConfirmed
{
    public function __construct(public Booking $booking) {}
}
