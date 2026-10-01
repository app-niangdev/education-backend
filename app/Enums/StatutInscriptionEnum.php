<?php

namespace App\Enums;

enum StatutInscriptionEnum: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case VALIDEE = 'VALIDEE';
    case ANNULEE = 'ANNULEE';
}
