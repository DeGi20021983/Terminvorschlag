<x-mail::message>
# Erinnerung an Ihren Termin

Guten Tag {{ $booking->user->name() }},

wir erinnern Sie an Ihren Termin:

**{{ $booking->offer->title }}**
{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }} Uhr
Ort: {{ $booking->offer->locationLabel() }}

<x-mail::button :url="route('bookings.mine')">Meine Termine</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
