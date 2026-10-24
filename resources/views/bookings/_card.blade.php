@php
    $termin = $booking->slot;
    $cancelled = $booking->status === \App\Enums\BookingStatus::Cancelled;
@endphp
<article class="booking-card @if ($past) booking-card--past @endif" aria-labelledby="buchung-{{ $booking->id }}">
    <div class="date-block" aria-hidden="true">
        <span class="date-block__weekday">{{ $termin->starts_at->translatedFormat('D') }}</span>
        <span class="date-block__day">{{ $termin->starts_at->format('d') }}</span>
        <span class="date-block__time">{{ $termin->starts_at->format('H:i') }}</span>
    </div>
    <div>
        <div class="booking-card__head">
            <h2 id="buchung-{{ $booking->id }}">{{ $booking->offer->title }}</h2>
            @if ($cancelled)
                <x-badge variant="danger">Storniert</x-badge>
            @elseif ($past)
                <x-badge variant="booked">Abgeschlossen</x-badge>
            @else
                <x-badge variant="free">Bestätigt</x-badge>
            @endif
        </div>
        <p class="muted">{{ $termin->dateLabel() }}, {{ $termin->timeRange() }} · {{ $booking->offer->locationLabel() }}</p>

        @if (! $past)
            <div class="booking-card__actions">
                <a class="btn btn--small" href="{{ route('offers.show', $booking->offer) }}">Umbuchen</a>
                <form method="post" action="{{ route('bookings.cancel', $booking) }}" class="inline-form"
                      data-confirm="Termin wirklich stornieren? Der Platz wird sofort frei.">
                    @csrf
                    <button class="btn btn--small btn--danger" type="submit">Stornieren</button>
                </form>
            </div>
        @endif
    </div>
</article>
