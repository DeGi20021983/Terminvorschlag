<?php

namespace App\Events;

use App\Models\Booking;

final readonly class BookingMoved
{
    public function __construct(
        public Booking $new,
        public Booking $old,
    ) {}
}
