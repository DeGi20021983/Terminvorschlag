<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Man storniert und bucht nur die eigene Buchung um. Ob der Termin schon begonnen hat,
     * prüft der BookingService, weil das nur zum Zeitpunkt der Aktion gilt.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}
