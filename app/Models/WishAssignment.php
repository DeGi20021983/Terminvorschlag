<?php

namespace App\Models;

use App\Enums\AlternativeResponse;
use App\Enums\WishOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ergebnis der Verteilung für eine Person.
 *
 * @property WishOutcome $outcome
 * @property bool $by_lot
 * @property AlternativeResponse|null $alternative_response
 */
class WishAssignment extends Model
{
    protected $fillable = [
        'wish_run_id',
        'offer_id',
        'user_id',
        'slot_id',
        'outcome',
        'by_lot',
        'alternative_response',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => WishOutcome::class,
            'by_lot' => 'boolean',
            'alternative_response' => AlternativeResponse::class,
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WishRun::class, 'wish_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
