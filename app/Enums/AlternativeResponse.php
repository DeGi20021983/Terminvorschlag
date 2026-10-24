<?php

namespace App\Enums;

enum AlternativeResponse: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
