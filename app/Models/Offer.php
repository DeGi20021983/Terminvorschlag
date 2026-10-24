<?php

namespace App\Models;

use App\Enums\AllocationMode;
use App\Enums\OfferStatus;
use Carbon\CarbonInterface;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Angebot mit Terminen. Der Organisator (Besitzer) ist für das Angebot verantwortlich.
 *
 * @property int $id
 * @property int $owner_user_id
 * @property string $title
 * @property OfferStatus $status
 * @property AllocationMode $allocation_mode
 * @property bool $one_booking_per_person
 * @property bool $reminders_enabled
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'title',
        'description',
        'location_id',
        'online_url',
        'contact_name',
        'target_group',
        'area',
        'allocation_mode',
        'status',
        'bookable_from',
        'bookable_until',
        'wish_deadline',
        'wishes_released_at',
        'one_booking_per_person',
        'reminders_enabled',
        'cancellation_note',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'allocation_mode' => AllocationMode::class,
            'bookable_from' => 'datetime',
            'bookable_until' => 'datetime',
            'wish_deadline' => 'datetime',
            'wishes_released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'one_booking_per_person' => 'boolean',
            'reminders_enabled' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function wishRuns(): HasMany
    {
        return $this->hasMany(WishRun::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === OfferStatus::Cancelled;
    }

    /**
     * Buchbar im Sinne des Buchungszeitraums (ab/bis, jeweils einschließlich).
     */
    public function isInBookingWindow(CarbonInterface $now): bool
    {
        return $this->status === OfferStatus::Published
            && $now->greaterThanOrEqualTo($this->bookable_from)
            && $now->lessThanOrEqualTo($this->bookable_until);
    }

    /**
     * Sofortbuchung ist offen: direkt im Modus „Sofort“, bei Wunschtermin erst nach der Freigabe.
     */
    public function instantBookingOpen(): bool
    {
        return $this->allocation_mode === AllocationMode::Instant
            || $this->wishes_released_at !== null;
    }

    /**
     * Wünsche können bis zum Fristende abgegeben werden, solange noch nichts freigegeben ist.
     */
    public function wishesOpen(CarbonInterface $now): bool
    {
        return $this->allocation_mode === AllocationMode::Wishes
            && $this->wishes_released_at === null
            && $this->wish_deadline !== null
            && $now->lessThan($this->wish_deadline);
    }
}
