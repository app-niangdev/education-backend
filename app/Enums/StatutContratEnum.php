<?php

namespace App\Enums;

/**
 * Ou en est le contrat dans son cycle de vie.
 *
 * EXPIRE se deduit de la date de fin et n'est donc jamais choisi a la main :
 * c'est le service qui bascule les contrats echus. Les autres statuts
 * resultent d'une decision explicite de l'etablissement.
 */
enum StatutContratEnum: string
{
    case BROUILLON = 'BROUILLON';
    case ACTIF     = 'ACTIF';
    case EXPIRE    = 'EXPIRE';
    case RESILIE   = 'RESILIE';
    case SUSPENDU  = 'SUSPENDU';

    public function libelle(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::ACTIF     => 'Actif',
            self::EXPIRE    => 'Expiré',
            self::RESILIE   => 'Résilié',
            self::SUSPENDU  => 'Suspendu',
        };
    }

    /**
     * Un contrat « en cours » lie encore l'employe a l'etablissement : c'est
     * lui qui porte les conditions de travail affichees sur la fiche.
     *
     * Un contrat suspendu compte comme en cours : la relation de travail
     * subsiste, seule son execution est interrompue.
     */
    public function estEnCours(): bool
    {
        return in_array($this, [self::ACTIF, self::SUSPENDU], true);
    }

    /**
     * Un contrat clos ne peut plus etre modifie ni redevenir actif : seul un
     * nouveau contrat (renouvellement) prend le relais.
     */
    public function estClos(): bool
    {
        return in_array($this, [self::EXPIRE, self::RESILIE], true);
    }
}
