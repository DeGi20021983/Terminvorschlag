<x-mail::message>
# Ihr Termin hat sich geändert

Guten Tag {{ $booking->user->name() }},

bei **{{ $booking->offer->title }}** wurde etwas geändert.

**Termin:** {{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }} Uhr
**Ort:** {{ $booking->offer->locationLabel() }}
@if ($timeChanged)

Bitte prüfen Sie den Termin und passen Sie Ihren Kalender an. Falls der neue Termin für Sie nicht passt, stornieren Sie ihn bitte oder buchen Sie um.
@endif

<x-mail::button :url="route('bookings.mine')">Meine Termine</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
