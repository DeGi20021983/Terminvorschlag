<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Erzeugt eine iCalendar-Datei (RFC 5545) für eine bestätigte Buchung.
 */
final class IcsBuilder
{
    private const MAX_LINE_OCTETS = 75;

    public function forBooking(Booking $booking): string
    {
        $booking->loadMissing(['offer.location', 'slot', 'offer.owner']);

        $offer = $booking->offer;
        $slot = $booking->slot;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'terminbuchung.local';

        $description = implode("\n", array_filter([
            'Ansprechperson: '.$offer->contact_name,
            $offer->online_url !== null ? 'Online: '.$offer->online_url : null,
            'Stornieren oder umbuchen bis Terminbeginn: '.route('bookings.mine'),
        ]));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Terminbuchung//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-'.$booking->id.'@'.$host,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$slot->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$slot->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($offer->title),
            'LOCATION:'.$this->escape($offer->locationLabel() === 'Online' ? (string) $offer->online_url : $offer->locationLabel()),
            'DESCRIPTION:'.$this->escape($description),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode('', array_map(fn (string $line) => $this->fold($line)."\r\n", $lines));
    }

    /**
     * Maskiert Sonderzeichen nach RFC 5545. Zeilenumbrüche werden zu \n.
     */
    private function escape(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\\,'], $value);

        return str_replace(["\r\n", "\n"], '\\n', $value);
    }

    /**
     * Bricht lange Zeilen nach 75 Oktetts um. Umbrüche fallen nie mitten in ein UTF-8-Zeichen.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::MAX_LINE_OCTETS) {
            return $line;
        }

        $chunks = [];
        $first = mb_strcut($line, 0, self::MAX_LINE_OCTETS, 'UTF-8');
        $chunks[] = $first;
        $rest = substr($line, strlen($first));

        while ($rest !== '') {
            $chunk = mb_strcut($rest, 0, self::MAX_LINE_OCTETS - 1, 'UTF-8');
            $chunks[] = ' '.$chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return implode("\r\n", $chunks);
    }
}
