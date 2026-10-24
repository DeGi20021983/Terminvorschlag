<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Protokolleintrag über eine Änderung durch Organisatoren oder Administratoren.
 * Einträge werden nicht bearbeitet, nur nach Ablauf der Löschfrist entfernt.
 *
 * @property int $id
 * @property string $actor_username
 * @property string $action
 * @property array<string, mixed>|null $details
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_username',
        'action',
        'subject_type',
        'subject_id',
        'offer_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
