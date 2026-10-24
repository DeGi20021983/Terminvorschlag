<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ergebnis eines Löschlaufs. Zeigt der Administration, wann und mit welchem Ergebnis gelöscht wurde.
 */
class PurgeRun extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'anonymized_slots',
        'deleted_bookings',
        'deleted_wish_records',
        'deleted_audit_entries',
        'deleted_users',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
