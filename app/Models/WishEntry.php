<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Wunschtermin einer Person in der festen Reihenfolge (1 bis 3).
 *
 * @property int $offer_id
 * @property int|null $user_id
 * @property int $slot_id
 * @property int $priority
 */
class WishEntry extends Model
{
    protected $fillable = ['offer_id', 'user_id', 'slot_id', 'priority', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
