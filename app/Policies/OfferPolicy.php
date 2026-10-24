<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

/**
 * Organisatoren verwalten nur die eigenen Angebote. Administratoren verwalten keine
 * Angebote, damit die Rollen getrennt bleiben.
 */
class OfferPolicy
{
    public function create(User $user): bool
    {
        return $user->is_organizer;
    }

    /**
     * Veröffentlichte und abgesagte Angebote sind für alle Angemeldeten sichtbar, Entwürfe nur für den Verantwortlichen.
     */
    public function view(User $user, Offer $offer): bool
    {
        return $offer->status !== OfferStatus::Draft || $this->manage($user, $offer);
    }

    public function manage(User $user, Offer $offer): bool
    {
        return $user->is_organizer && $offer->owner_user_id === $user->id;
    }

    public function viewParticipants(User $user, Offer $offer): bool
    {
        return $this->manage($user, $offer);
    }
}
