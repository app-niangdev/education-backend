<?php

namespace App\Enums;

/**
 * Ce qui est relevé pour un élève sur une séance.
 *
 * Seules les anomalies sont enregistrées : un élève présent ne produit aucune
 * ligne. C'est la séance elle-même qui atteste que l'appel a eu lieu, donc
 * l'absence de ligne signifie « présent » sans ambiguïté.
 */
enum StatutPresenceEnum: string
{
    case ABSENT  = 'ABSENT';
    case RETARD  = 'RETARD';
    case RENVOYE = 'RENVOYE';

    public function libelle(): string
    {
        return match ($this) {
            self::ABSENT  => 'Absent',
            self::RETARD  => 'Retard',
            self::RENVOYE => 'Renvoyé de cours',
        };
    }

    /**
     * Compte-t-il dans le volume horaire d'absence du bulletin ?
     *
     * Un élève renvoyé n'a pas suivi l'heure : du point de vue des cours
     * manqués, c'est une absence. Le statut reste distinct en base pour le
     * suivi disciplinaire du surveillant.
     */
    public function compteCommeAbsence(): bool
    {
        return match ($this) {
            self::ABSENT, self::RENVOYE => true,
            self::RETARD                => false,
        };
    }

    /** Les retards se comptent en occurrences, pas en heures. */
    public function compteCommeRetard(): bool
    {
        return $this === self::RETARD;
    }
}
