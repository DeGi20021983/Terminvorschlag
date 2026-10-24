<?php

namespace App\Models;

use App\Enums\WishRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Verteilungslauf. Jede Neuberechnung ersetzt den vorherigen Lauf.
 *
 * @property int $id
 * @property int $offer_id
 * @property WishRunStatus $status
 */
class WishRun extends Model
{
    protected $fillable = [
        'offer_id',
        'status',
        'calculated_by_user_id',
        'calculated_at',
        'released_by_user_id',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WishRunStatus::class,
            'calculated_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WishAssignment::class);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(WishDraw::class);
    }
}
