<?php

namespace App\Enums;

/**
 * Situation de l'eleve vis-a-vis de l'etablissement. A ne pas confondre avec
 * StatutInscriptionEnum, qui decrit le cycle de vie administratif d'une
 * inscription (EN_ATTENTE / VALIDEE / ANNULEE).
 */
enum StatutInscriptionEleveEnum: string
{
    case NOUVEAU    = 'NOUVEAU';
    case REDOUBLANT = 'REDOUBLANT';
    case REINSCRIT  = 'REINSCRIT';
    case TRANSFERE  = 'TRANSFERE';
}
