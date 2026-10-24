<?php

namespace App\Events;

use App\Models\Offer;
use App\Models\WishRun;

/**
 * Die Wunschverteilung ist freigegeben. Benachrichtigungen (Meilenstein f) hängen sich hier ein.
 */
final readonly class WishesReleased
{
    public function __construct(
        public Offer $offer,
        public WishRun $run,
    ) {}
}
