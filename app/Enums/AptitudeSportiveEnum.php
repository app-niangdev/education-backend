<?php

namespace App\Enums;

/**
 * Aptitude de l'eleve a la pratique de l'EPS, telle qu'attestee par le
 * certificat medical.
 */
enum AptitudeSportiveEnum: string
{
    case APTE          = 'APTE';
    case APTE_PARTIEL  = 'APTE_PARTIEL';
    case INAPTE        = 'INAPTE';
}
