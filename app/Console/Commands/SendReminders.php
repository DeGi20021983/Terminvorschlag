<?php

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Mail\ReminderMail;
use App\Models\AppSetting;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Erinnerungsmails vor dem Termin. Vorgabe: Stunden vor Terminbeginn (Administration).
 * Je Angebot abschaltbar. Jede Buchung erhält genau eine Erinnerung.
 */
class SendReminders extends Command
{
    protected $signature = 'terminvorschlag:erinnerungen';

    protected $description = 'Verschickt Erinnerungen zu bevorstehenden Terminen';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $hours = (int) AppSetting::get('reminder_hours_before', (string) config('terminvorschlag.defaults.reminder_hours_before'));

        $bookings = Booking::query()
            ->confirmed()
            ->whereNull('reminder_sent_at')
            ->whereHas('offer', fn ($offer) => $offer
                ->where('reminders_enabled', true)
                ->where('status', OfferStatus::Published))
            ->whereHas('slot', fn ($slot) => $slot
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $now->addHours($hours)))
            ->with(['user', 'offer', 'slot'])
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->user === null || blank($booking->user->email)) {
                continue;
            }

            Mail::to($booking->user->email, $booking->user->name())->queue(new ReminderMail($booking));

            $booking->update(['reminder_sent_at' => $now]);
        }

        $this->info('Erinnerungen eingeplant: '.$bookings->count());

        return self::SUCCESS;
    }
}
