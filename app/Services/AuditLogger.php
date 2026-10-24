<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Schreibt das Protokoll der Änderungen durch Organisatoren und Administratoren.
 * Details enthalten nur Sachangaben (z. B. Anzahl, geänderte Felder), keine Personendaten.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(User $actor, string $action, ?Model $subject = null, ?Offer $offer = null, array $details = []): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_username' => $actor->username,
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'offer_id' => $offer?->id,
            'details' => $details === [] ? null : $details,
            'created_at' => now(),
        ]);
    }
}
