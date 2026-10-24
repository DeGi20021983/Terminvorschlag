<?php

namespace App\Console\Commands;

use App\Enums\AllocationMode;
use App\Mail\OrganizerWishDueMail;
use App\Models\Offer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Erinnert den Organisator einmal, wenn die Wunschfrist abgelaufen ist.
 * Die Verteilung selbst startet nicht automatisch, sondern auf Knopfdruck.
 */
class NotifyWishDeadlines extends Command
{
    protected $signature = 'terminvorschlag:wunschfristen';

    protected $description = 'Benachrichtigt Organisatoren nach Ablauf der Wunschfrist';

    public function handle(): int
    {
        $now = now();

        $offers = Offer::query()
            ->where('allocation_mode', AllocationMode::Wishes)
            ->whereNull('wishes_released_at')
            ->whereNull('wish_due_notified_at')
            ->whereNotNull('wish_deadline')
            ->where('wish_deadline', '<=', $now)
            ->with('owner')
            ->get();

        foreach ($offers as $offer) {
            if ($offer->owner !== null && filled($offer->owner->email)) {
                Mail::to($offer->owner->email, $offer->owner->name())->queue(new OrganizerWishDueMail($offer));
            }

            $offer->update(['wish_due_notified_at' => $now]);
        }

        $this->info('Hinweise zu abgelaufenen Wunschfristen: '.$offers->count());

        return self::SUCCESS;
    }
}
