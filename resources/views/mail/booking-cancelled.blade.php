<x-mail::message>
# Ihr Termin wurde storniert

Guten Tag {{ $booking->user->name() }},

@if ($byOrganizer)
der Organisator hat Sie aus folgendem Termin ausgetragen:
@else
Ihre Stornierung wurde bestätigt. Der Platz ist wieder frei:
@endif

**{{ $booking->offer->title }}**
{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }} Uhr

<x-mail::button :url="route('offers.show', $booking->offer)">Angebot ansehen</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
