<x-mail::message>
# Ihre Buchung ist bestätigt

Guten Tag {{ $booking->user->name() }},

Ihr Termin ist gebucht:

**{{ $booking->offer->title }}**
{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }} Uhr
Ort: {{ $booking->offer->locationLabel() }}
@if ($booking->offer->online_url)
Online: {{ $booking->offer->online_url }}
@endif
Ansprechperson: {{ $booking->offer->contact_name }}

Die Datei **termin.ics** im Anhang können Sie in Ihren Kalender übernehmen.

<x-mail::button :url="route('bookings.mine')">Meine Termine</x-mail::button>

Sie können Ihren Termin bis zum Terminbeginn stornieren oder auf einen anderen freien Termin umbuchen.

{{ $confirmationText }}

Viele Grüße
{{ config('app.name') }}
</x-mail::message>
