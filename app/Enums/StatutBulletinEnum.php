<?php

namespace App\Enums;

/**
 * Cycle de vie d'un bulletin.
 *
 * BROUILLON : les valeurs sont recalculables à volonté. Le PDF porte un
 * bandeau « document non définitif » pour qu'un tirage de contrôle ne
 * circule pas comme un bulletin officiel.
 *
 * PUBLIE : le bulletin est figé. Les notes, coefficients et libellés ont
 * été recopiés dans les colonnes du bulletin au moment de la publication ;
 * une correction ultérieure d'une note ne le modifie plus. Pour le faire
 * évoluer il faut le dépublier explicitement, ce qui le ramène à BROUILLON.
 */
enum StatutBulletinEnum: string
{
    case BROUILLON = 'BROUILLON';
    case PUBLIE    = 'PUBLIE';

    public function libelle(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::PUBLIE    => 'Publié',
        };
    }
}
