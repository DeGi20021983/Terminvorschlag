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

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Hinweis für Organisator und Person, z. B. „Erstwunsch erfüllt“ oder „Per Los“.
     */
    public function hint(): string
    {
        if ($this->outcome === WishOutcome::None) {
            return 'Kein freier Termin';
        }

        if ($this->outcome === WishOutcome::Alternative) {
            return match ($this->alternative_response) {
                AlternativeResponse::Pending => 'Alternative vorgeschlagen',
                AlternativeResponse::Accepted => 'Alternative angenommen',
                AlternativeResponse::Declined => 'Alternative abgelehnt',
                null => 'Alternative zugeteilt',
            };
        }

        $label = $this->outcome->label();

        return $this->by_lot ? 'Per Los · '.$label : $label.' erfüllt';
    }

    /**
     * Direkte Zuteilung: wird bei der Freigabe gebucht.
     */
    public function isDirect(): bool
    {
        return $this->outcome !== WishOutcome::None && $this->alternative_response === null;
    }

    /**
     * Vorschlag, den die Person annehmen oder ablehnen kann.
     */
    public function isPendingAlternative(): bool
    {
        return $this->alternative_response === AlternativeResponse::Pending;
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
