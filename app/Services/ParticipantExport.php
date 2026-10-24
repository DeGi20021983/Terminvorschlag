<?php

namespace App\Services;

use App\Models\Offer;

/**
 * Zeilen der Teilnehmerliste für CSV, Excel und Anwesenheitsliste.
 */
final class ParticipantExport
{
    /**
     * @return list<list<string>>
     */
    public function rows(Offer $offer): array
    {
        $rows = [['Datum', 'Beginn', 'Ende', 'Status', 'Person', 'Organisationseinheit', 'E-Mail']];

        $offer->slots()->with('activeBooking.user')->orderBy('starts_at')->get()->each(function ($slot) use (&$rows) {
            $booking = $slot->activeBooking;
            $user = $booking?->user;

            $rows[] = [
                $slot->starts_at->format('d.m.Y'),
                $slot->starts_at->format('H:i'),
                $slot->ends_at->format('H:i'),
                $booking ? 'Belegt' : 'Frei',
                $this->safe($user?->name() ?? ''),
                $this->safe($user?->department ?? ''),
                $this->safe($user?->email ?? ''),
            ];
        });

        return $rows;
    }

    /**
     * Schützt vor Formelinjektion: Zellen, die wie Formeln beginnen, werden entschärft.
     */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
