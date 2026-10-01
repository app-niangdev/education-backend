<?php

namespace App\Enums;

/**
 * Le cycle de vie d'une année scolaire.
 *
 * CLOTURER est plus qu'un libellé : tant qu'une année n'a pas ce statut,
 * aucun de ses élèves ne peut être inscrit ailleurs (voir
 * InscriptionRepository::inscriptionActiveNonCloturee).
 */
enum StatutAnneeScolaire: string
{
    case AVENIR = 'AVENIR';
    case ENCOURS = 'ENCOURS';
    case CLOTURER = 'CLOTURER';

    public function libelle(): string
    {
        return match ($this) {
            self::AVENIR   => 'À venir',
            self::ENCOURS  => 'En cours',
            self::CLOTURER => 'Clôturée',
        };
    }
}
