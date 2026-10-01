<?php

namespace App\Enums;

/**
 * Lien unissant le tuteur legal a l'eleve.
 */
enum LienParenteEnum: string
{
    case PERE         = 'PERE';
    case MERE         = 'MERE';
    case ONCLE        = 'ONCLE';
    case TANTE        = 'TANTE';
    case GRAND_PARENT = 'GRAND_PARENT';
    case AUTRE        = 'AUTRE';
}
