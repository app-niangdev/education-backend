<?php

namespace App\Enums;

/**
 * L'état d'un créneau à une date donnée.
 *
 * Distinguer ces trois cas est la raison d'être de la table `seances_appel` :
 * sans elle, on ne saurait jamais si une classe sans absence signalée était
 * assidue ou si personne n'avait fait l'appel.
 */
enum StatutSeanceEnum: string
{
    case PLANIFIEE   = 'PLANIFIEE';
    case FAITE       = 'FAITE';
    case NON_ASSUREE = 'NON_ASSUREE';

    public function libelle(): string
    {
        return match ($this) {
            self::PLANIFIEE   => 'Appel non fait',
            self::FAITE       => 'Appel fait',
            self::NON_ASSUREE => 'Cours non assuré',
        };
    }

    /**
     * La séance compte-t-elle dans les statistiques et le bulletin ?
     *
     * Un cours non assuré (enseignant absent, jour férié) ne doit jamais
     * pénaliser les élèves ni faire baisser le taux d'appels du surveillant.
     */
    public function estComptabilisee(): bool
    {
        return $this === self::FAITE;
    }
}
