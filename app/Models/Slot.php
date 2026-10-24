<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\SlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ein einzelner Termin eines Angebots. Genau eine Person kann ihn belegen.
 *
 * @property int $id
 * @property int $offer_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $was_booked
 */
class Slot extends Model
{
    /** @use HasFactory<SlotFactory> */
    use HasFactory;

    protected $fillable = ['offer_id', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'was_booked' => 'boolean',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)->where('status', BookingStatus::Confirmed);
    }

    /**
     * Datum für die Anzeige, z. B. „Mo, 03.11.2026“.
     */
    public function dateLabel(): string
    {
        return $this->starts_at->translatedFormat('D, d.m.Y');
    }

    /**
     * Uhrzeit für die Anzeige, z. B. „09:00–09:30“.
     */
    public function timeRange(): string
    {
        return $this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i');
    }

    public function hasEnded(CarbonInterface $now): bool
    {
        return $this->ends_at->lessThanOrEqualTo($now);
    }
}
