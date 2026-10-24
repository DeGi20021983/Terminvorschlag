<?php

namespace App\Enums;

/**
 * Wie eine Buchung entstanden ist. Für die Anzeige und das Protokoll.
 */
enum BookingSource: string
{
    case Instant = 'instant';
    case Wish = 'wish';
    case Manual = 'manual';
    case Moved = 'moved';
}
