<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fachlicher Fehler bei Buchung, Stornierung oder Umbuchung. Die Meldung ist für Nutzende bestimmt.
 */
final class BookingException extends RuntimeException
{
    public static function slotTaken(): self
    {
        return new self('Dieser Termin ist gerade vergeben. Bitte wählen Sie einen anderen Termin.');
    }

    public static function alreadyBooked(): self
    {
        return new self('Sie haben in diesem Angebot bereits einen Termin. Bitte stornieren oder buchen Sie um.');
    }

    public static function outsideWindow(): self
    {
        return new self('Dieses Angebot ist außerhalb des Buchungszeitraums nicht buchbar.');
    }

    public static function notBookableNow(): self
    {
        return new self('Dieses Angebot kann im Moment nicht gebucht werden.');
    }

    public static function slotStarted(): self
    {
        return new self('Dieser Termin hat bereits begonnen. Eine Änderung ist nicht mehr möglich.');
    }

    public static function notYourBooking(): self
    {
        return new self('Diese Buchung gehört nicht zu Ihrem Konto.');
    }

    public static function bookingNotActive(): self
    {
        return new self('Diese Buchung ist nicht mehr aktiv.');
    }

    public static function slotNotInOffer(): self
    {
        return new self('Der Termin gehört nicht zu diesem Angebot.');
    }

    public static function slotHasBookings(): self
    {
        return new self('Der Termin ist belegt. Bitte tragen Sie die Person zuerst aus.');
    }

    public static function overlapping(): self
    {
        return new self('Der Termin überschneidet sich mit einem bestehenden Termin dieses Angebots.');
    }

    public static function invalidTime(): self
    {
        return new self('Die Endzeit muss nach der Startzeit liegen.');
    }

    public static function gridNotDivisible(int $minutes): self
    {
        return new self("Der Zeitraum muss ein Vielfaches von {$minutes} Minuten sein.");
    }

    public static function gridTooLarge(int $max): self
    {
        return new self("Ein Zeitraster darf höchstens {$max} Termine umfassen.");
    }

    public static function offerNotPublishable(): self
    {
        return new self('Legen Sie vor der Veröffentlichung mindestens einen Termin an.');
    }

    public static function offerAlreadyCancelled(): self
    {
        return new self('Dieses Angebot ist bereits abgesagt.');
    }
}
