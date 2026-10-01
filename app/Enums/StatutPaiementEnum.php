<?php

namespace App\Enums;

enum StatutPaiementEnum: string
{
    case NON_PAYE = 'NON_PAYE';
    case PARTIEL = 'PARTIEL';
    case PAYE = 'PAYE';
}
