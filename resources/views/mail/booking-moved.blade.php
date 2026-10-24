<x-mail::message>
# Ihr Termin wurde umgebucht

Guten Tag {{ $new->user->name() }},

Ihr Termin bei **{{ $new->offer->title }}** wurde umgebucht.

Bisher: {{ $old->slot->dateLabel() }}, {{ $old->slot->timeRange() }} Uhr
Neu: **{{ $new->slot->dateLabel() }}, {{ $new->slot->timeRange() }} Uhr**
Ort: {{ $new->offer->locationLabel() }}

Die Datei **termin.ics** im Anhang zeigt den neuen Termin. Bitte ersetzen Sie den alten Eintrag in Ihrem Kalender.

<x-mail::button :url="route('bookings.mine')">Meine Termine</x-mail::button>

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
