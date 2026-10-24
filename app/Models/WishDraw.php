<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll einer Auslosung bei Gleichstand um einen Termin.
 *
 * @property int $round Wunschstufe (1, 2 oder 3), in der gelost wurde
 * @property array<int, int> $candidate_user_ids
 */
class WishDraw extends Model
{
    protected $fillable = ['wish_run_id', 'slot_id', 'round', 'candidate_user_ids', 'winner_user_id'];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'candidate_user_ids' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WishRun::class, 'wish_run_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
