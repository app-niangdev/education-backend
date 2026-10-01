<?php

namespace App\Enums;

/**
 * Bloc de droite du bulletin papier : la distinction ou la sanction
 * prononcée par le conseil des professeurs. Une seule case est cochée.
 *
 * Comme pour DecisionConseilEnum, les libellés sont ceux du document
 * original — « Blame » y est écrit sans accent.
 */
enum DistinctionEnum: string
{
    case FELICITATIONS   = 'FELICITATIONS';
    case ENCOURAGEMENT   = 'ENCOURAGEMENT';
    case TABLEAU_HONNEUR = 'TABLEAU_HONNEUR';
    case AVERTISSEMENT   = 'AVERTISSEMENT';
    case BLAME           = 'BLAME';

    public function libelle(): string
    {
        return match ($this) {
            self::FELICITATIONS   => 'Félicitations',
            self::ENCOURAGEMENT   => 'Encouragement',
            self::TABLEAU_HONNEUR => "Tableau d'honneur",
            self::AVERTISSEMENT   => 'Avertissement',
            self::BLAME           => 'Blame',
        };
    }
}
