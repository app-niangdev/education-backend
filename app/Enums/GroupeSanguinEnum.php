<?php

namespace App\Enums;

/**
 * Systeme ABO + rhesus. Les valeurs servent aussi de libelle d'affichage.
 */
enum GroupeSanguinEnum: string
{
    case A_POSITIF  = 'A+';
    case A_NEGATIF  = 'A-';
    case B_POSITIF  = 'B+';
    case B_NEGATIF  = 'B-';
    case AB_POSITIF = 'AB+';
    case AB_NEGATIF = 'AB-';
    case O_POSITIF  = 'O+';
    case O_NEGATIF  = 'O-';
}
